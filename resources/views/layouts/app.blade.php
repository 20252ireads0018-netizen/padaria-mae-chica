<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel') · Mãe Chica</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
@php
    $u = auth()->user();
    $nav = [
        ['sec' => 'Operação'],
        ['r' => 'dashboard', 'm' => 'dashboard', 'i' => 'home', 'l' => 'Painel', 'roles' => ['caixa', 'producao']],
        ['r' => 'cash.index', 'm' => 'cash.*', 'i' => 'cash', 'l' => 'Caixa', 'roles' => ['caixa']],
        ['r' => 'orders.index', 'm' => 'orders.*', 'i' => 'bag', 'l' => 'Encomendas', 'roles' => ['caixa', 'producao'], 'b' => $badges['orders'] ?? 0],
        ['r' => 'schools.index', 'm' => 'schools.*', 'i' => 'school', 'l' => 'Escolas', 'roles' => ['caixa', 'producao'], 'b' => $badges['schools'] ?? 0],
        ['sec' => 'Gestão'],
        ['r' => 'products.index', 'm' => 'products.*', 'i' => 'bread', 'l' => 'Produtos', 'roles' => ['caixa', 'producao']],
        ['r' => 'inventory.index', 'm' => 'inventory.*', 'i' => 'box', 'l' => 'Estoque & Compras', 'roles' => ['producao'], 'b' => ($badges['low'] ?? 0) + ($badges['po'] ?? 0)],
        ['r' => 'documents.index', 'm' => 'documents.*', 'i' => 'file', 'l' => 'Documentos', 'roles' => ['caixa'], 'b' => $badges['docs'] ?? 0],
        ['r' => 'schedule.index', 'm' => 'schedule.*', 'i' => 'cal', 'l' => 'Escala', 'roles' => ['caixa', 'producao']],
        ['sec' => 'Gerência', 'roles' => ['gerencia']],
        ['r' => 'accounting.index', 'm' => 'accounting.*', 'i' => 'chart', 'l' => 'Contabilidade', 'roles' => ['gerencia']],
        ['r' => 'users.index', 'm' => 'users.*', 'i' => 'users', 'l' => 'Usuários & Ajustes', 'roles' => ['gerencia']],
    ];
@endphp
<div class="app">
    <aside class="side">
        <a class="side-brand" href="{{ route('dashboard') }}">
            <img src="{{ asset('img/logo-icon.png') }}" alt="Mãe Chica">
            <div><b>Mãe Chica</b><span>Padaria e Delicatessen</span></div>
        </a>
        <nav class="nav">
            @foreach ($nav as $n)
                @continue(isset($n['roles']) && ! $u->hasRole(...$n['roles']))
                @if (isset($n['sec']))
                    <div class="nav-sec">{{ $n['sec'] }}</div>
                @else
                    <a href="{{ route($n['r']) }}" class="{{ request()->routeIs($n['m']) ? 'on' : '' }}">
                        <x-icon :n="$n['i']" /> {{ $n['l'] }}
                        @if (! empty($n['b']))<span class="badge">{{ $n['b'] }}</span>@endif
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="side-user">
            <div class="av">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</div>
            <div style="min-width:0">
                <div class="b" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $u->name }}</div>
                <div style="opacity:.75;font-size:.72rem">{{ $u->role_label }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button title="Sair"><x-icon n="logout" /></button>
            </form>
        </div>
    </aside>

    <div class="main">
        <header class="top">
            <button class="menu" data-menu aria-label="Menu"><x-icon n="menu" /></button>
            <span class="date">{{ ucfirst(now()->translatedFormat('l, d \d\e F')) }}</span>
            <span class="stripe"></span>
            @if (($badges['late'] ?? 0) > 0)
                <a class="pill alert" href="{{ route('orders.index', ['ver' => 'atrasadas']) }}"><x-icon n="alert" /> {{ $badges['late'] }} encomenda(s) atrasada(s)</a>
            @endif
            @if (($badges['orders'] ?? 0) > 0)
                <a class="pill" href="{{ route('orders.index', ['ver' => 'semana']) }}"><x-icon n="bell" /> {{ $badges['orders'] }} p/ entregar em breve</a>
            @endif
            @if (($badges['low'] ?? 0) > 0 && $u->hasRole('producao'))
                <a class="pill" href="{{ route('inventory.index') }}"><x-icon n="box" /> {{ $badges['low'] }} c/ estoque baixo</a>
            @endif
        </header>

        <main class="content">
            @if (session('ok'))<div class="alert-box ok"><x-icon n="check" /> {{ session('ok') }}</div>@endif
            @if (session('error'))<div class="alert-box err"><x-icon n="alert" /> {{ session('error') }}</div>@endif
            @if ($errors->any())
                <div class="alert-box err"><x-icon n="alert" /> {{ $errors->first() }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('js/app.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
