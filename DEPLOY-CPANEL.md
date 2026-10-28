# Publicar no cPanel (diretório próprio)

O sistema fica **em sua própria pasta, fora do `public_html`**: `~/padaria-mae-chica`.
Só a pasta `public/` fica exposta na web, apontada por um subdomínio (ex.: `padaria.seudominio.com.br`).

```
/home/SUA_CONTA/
├── public_html/              ← site atual (não é tocado)
└── padaria-mae-chica/        ← sistema
    ├── app, config, vendor...
    ├── .env                  ← configuração de produção (não vai para o GitHub)
    └── public/               ← raiz do subdomínio
```

## 1. Banco de dados (cPanel → Bancos de dados MySQL)
1. Crie o banco `conta_padaria` e o usuário `conta_padaria` com senha forte.
2. Adicione o usuário ao banco com **Todos os privilégios**.

## 2. Subdomínio (cPanel → Domínios / Subdomínios)
- Subdomínio: `padaria` · **Raiz do documento:** `padaria-mae-chica/public`
- Ative o SSL (cPanel → SSL/TLS Status → Run AutoSSL).
- Versão do PHP (MultiPHP Manager): **8.2 ou superior**, com `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `intl`.

## 3a. Enviar pelo GitHub (recomendado — atualizações com um clique)
1. cPanel → **Git™ Version Control** → *Create* → *Clone a Repository*.
   - URL: a do repositório no GitHub (se for privado, use a URL SSH e cadastre a chave do cPanel em *SSH Access* como **Deploy key** no GitHub).
   - Caminho: `repositories/padaria-mae-chica`.
2. Crie o arquivo `~/padaria-mae-chica/.env` (passo 4) **antes** do primeiro deploy.
3. Em *Manage → Pull or Deploy* clique **Deploy HEAD Commit**. O `.cpanel.yml` copia o código para `~/padaria-mae-chica`, instala as dependências, roda as migrations e cria o usuário da gerência.
4. Para atualizar depois: `git push` no computador → no cPanel, *Update from Remote* → *Deploy HEAD Commit*.

## 3b. Enviar por arquivo (sem Git)
1. Gere o pacote no computador (já inclui `vendor/`): `dist/padaria-mae-chica-cpanel.zip`.
2. cPanel → **Gerenciador de Arquivos** → pasta `/home/SUA_CONTA` → *Carregar* o zip → *Extrair*. Isso cria `~/padaria-mae-chica`.
3. Crie o `.env` (passo 4) e rode o passo 5.

## 4. Arquivo `.env`
Copie `.env.production.example` para `.env` dentro de `~/padaria-mae-chica` e preencha:
- `APP_URL` (https://padaria.seudominio.com.br)
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `ADMIN_EMAIL` e `ADMIN_PASSWORD` (primeiro acesso da gerência — pode apagar depois)
- `APP_KEY`: gere no computador com `php artisan key:generate --show` e cole o valor.

## 5. Comandos (cPanel → Terminal), na pasta `~/padaria-mae-chica`
```bash
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force
php artisan config:cache && php artisan view:cache
chmod -R 775 storage bootstrap/cache
```
> Sem Terminal no plano? Use o caminho 3a: o `.cpanel.yml` executa esses comandos no deploy.

## Alternativa sem DNS: pasta no domínio principal (em uso: websight.com.br/padaria)
Quando não há acesso ao DNS (ex.: domínio no Cloudflare de outra conta), o código continua em `~/padaria-mae-chica`
e só uma entrada fica em `public_html/padaria`:
- `index.php` que carrega `~/padaria-mae-chica/vendor` e `bootstrap/app.php` e chama `$app->usePublicPath(__DIR__)`;
- `.htaccess` do Laravel com `DirectoryIndex index.php` e `RewriteRule ^$ index.php [L]`;
- cópia de `public/css`, `public/js`, `public/img` e `favicon.ico` (copie de novo quando esses arquivos mudarem).
- No `.env`: `APP_URL=https://websight.com.br/padaria`.
- Não use `php artisan route:cache` nessa configuração (quebra a rota raiz dentro da subpasta).

## Observações
- Se não puder escolher a raiz do subdomínio, aponte para `padaria-mae-chica`: o `.htaccess` da raiz redireciona para `public/` e bloqueia arquivos sensíveis.
- Documentos enviados ficam em `storage/app/documentos` (fora da web). Inclua essa pasta e o banco no backup do cPanel.
- Nunca rode `php artisan db:seed` sem `--class`, em produção: ele cria os dados e usuários de demonstração.
