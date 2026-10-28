# Mãe Chica — Sistema de Gestão da Padaria

Sistema em **Laravel 12 / PHP 8.2+** para a Padaria e Delicatessen Mãe Chica. Tema vermelho e amarelo, sem build de front-end (CSS/JS puros em `public/`).

## Como rodar (Laragon / Windows)

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Acesse http://localhost:8000. O banco padrão é SQLite (`database/database.sqlite`). Para MySQL, ajuste `DB_*` no `.env` e rode `php artisan migrate --seed`.

### Usuários de demonstração (senha `maechica123` — troque no primeiro acesso)

| Perfil | E-mail |
|---|---|
| Gerenciamento | gerencia@maechica.com |
| Caixa | caixa@maechica.com |
| Produção | producao@maechica.com |

## Módulos

1. **Caixa e fechamento** — lançamentos do dia; o fechamento puxa tudo automaticamente (entradas por forma de pagamento, saídas), calcula o resultado, a gaveta esperada x contada (sobra/falta), **deduz a reserva para o fluxo de caixa** (% configurável com valor mínimo) e mostra o restante disponível. Encomendas pagas, escolas faturadas e compras recebidas viram lançamentos automáticos.
2. **Encomendas** — itens, quantidades, valores, sinal e saldo; alertas de prazo (atrasadas / hoje / próximas N horas) no menu, na barra superior e no painel; avanço de status em um clique (“Entregar + receber”).
3. **Perfis** — Gerenciamento (tudo), Caixa, Produção. A matriz de acesso fica em *Usuários & Ajustes*.
4. **Escala** — calendário mensal com feriados e fins de semana destacados; escala padrão semanal por funcionário (“Gerar mês”); **rodízio automático** de sábados/domingos/feriados com N pessoas por dia; linha do tempo do dia mostrando quem trabalha junto; horas por funcionário.
5. **Produtos** — cadastro de produtos de venda e insumos, preço, custo, margem, estoque mínimo e movimentações (entrada, saída, contagem).
6. **Estoque, pedidos e documentos** — lista de estoque baixo com sugestão de reposição, geração de pedido de compra, recebimento (atualiza estoque, custo e despesa), fornecedores; arquivo de documentos com upload e alerta de vencimento.
7. **Contabilidade** — hoje, semana, mês, trimestre, ano, 12 meses ou período livre; comparação com o período anterior, margem, categorias, formas de pagamento, demonstrativo mês a mês e exportação CSV (Excel).
8. **Escolas** — pedidos por dia/turno, lista de produção da semana, repetir pedido em dias úteis ou copiar a semana, relatório mensal por escola e faturamento do mês.

## Publicação

Veja [DEPLOY-CPANEL.md](DEPLOY-CPANEL.md) para colocar no cPanel em diretório próprio (via GitHub ou arquivo zip).

## Testes

```bash
php artisan test
```
