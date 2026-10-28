<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · Mãe Chica</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<div class="login">
    <div class="art">
        <img src="{{ asset('img/logo-full.png') }}" alt="Mãe Chica — Padaria e Delicatessen">
        <p>Sabor que acolhe, qualidade que você confia!</p>
    </div>
    <div class="frm">
        <form class="box" method="POST" action="{{ url('/login') }}">
            @csrf
            <h1>Bem-vindo(a) de volta</h1>
            <div class="sub">Acesse o sistema de gestão da padaria.</div>
            @if ($errors->any())<div class="alert-box err">{{ $errors->first() }}</div>@endif
            <label class="f"><span>E-mail</span><input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
            <label class="f"><span>Senha</span><input type="password" name="password" required></label>
            <div class="flex between mb">
                <label class="check"><input type="checkbox" name="remember" value="1"> Manter conectado</label>
            </div>
            <button class="btn btn-p btn-lg w100" style="justify-content:center"><x-icon n="lock" /> Entrar</button>
            <div class="roles">
                <span class="tag red">Gerenciamento</span><span class="tag yel">Caixa</span><span class="tag">Produção</span>
            </div>
            <p class="muted" style="font-size:.78rem;margin-top:10px">Cada perfil enxerga apenas as áreas do seu trabalho.</p>
        </form>
    </div>
</div>
</body>
</html>
