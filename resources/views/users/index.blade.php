@extends('layouts.app')
@section('title', 'Usuários & ajustes')
@section('content')
@php use App\Models\User; $roleTag = ['gerencia' => 'red', 'caixa' => 'yel', 'producao' => 'blue']; @endphp
<div class="page-head">
    <div><h1>Usuários & ajustes</h1><div class="sub">Perfis de acesso e regras do caixa.</div></div>
    <div class="actions"><button class="btn btn-p" data-open="dlg-user"><x-icon n="plus" /> Novo usuário</button></div>
</div>

<div class="grid g-side">
    <div class="stack">
        <div class="card">
            <div class="card-h"><x-icon n="users" /><h2>Usuários</h2></div>
            <table class="t">
                <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($users as $user)
                    <tr style="{{ $user->active ? '' : 'opacity:.55' }}">
                        <td class="b">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td><span class="tag {{ $roleTag[$user->role] ?? '' }}">{{ $user->role_label }}</span></td>
                        <td>{!! $user->active ? '<span class="tag green">Ativo</span>' : '<span class="tag">Inativo</span>' !!}</td>
                        <td class="act"><button class="btn btn-sm btn-ghost btn-ico" data-open="dlg-u{{ $user->id }}"><x-icon n="edit" /></button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-h"><x-icon n="lock" /><h2>O que cada perfil acessa</h2></div>
            <table class="t">
                <thead><tr><th>Área</th><th class="center">Gerência</th><th class="center">Caixa</th><th class="center">Produção</th></tr></thead>
                <tbody>
                @foreach ([
                    ['Painel', 1, 1, 1], ['Caixa e fechamento', 1, 1, 0], ['Encomendas (criar/editar)', 1, 1, 0], ['Encomendas (ver e mudar status)', 1, 1, 1],
                    ['Escolas (criar/faturar)', 1, 1, 0], ['Escolas (ver e mudar status)', 1, 1, 1], ['Produtos (cadastro)', 1, 0, 0], ['Movimentar estoque', 1, 1, 1],
                    ['Estoque & compras', 1, 0, 1], ['Documentos', 1, 1, 0], ['Escala (ver)', 1, 1, 1], ['Escala (editar)', 1, 0, 0], ['Contabilidade', 1, 0, 0], ['Usuários e ajustes', 1, 0, 0],
                ] as [$a, $g, $c, $p])
                    <tr><td>{{ $a }}</td>@foreach ([$g, $c, $p] as $v)<td class="center">{!! $v ? '<span class="pos b">✓</span>' : '<span class="muted">—</span>' !!}</td>@endforeach</tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" id="ajustes">
        <div class="card-h"><x-icon n="settings" /><h2>Regras do caixa</h2></div>
        <form class="card-b" method="POST" action="{{ route('settings.update') }}">
            @csrf
            <label class="f"><span>% do resultado diário reservado para o fluxo de caixa</span><input type="number" step="0.5" min="0" max="100" name="reserve_percent" value="{{ $settings['reserve_percent'] }}"></label>
            <label class="f"><span>Reserva mínima por dia (R$)</span><input type="text" name="reserve_minimum" value="{{ str_replace('.', ',', $settings['reserve_minimum']) }}" inputmode="decimal"></label>
            <label class="f"><span>Troco que fica na gaveta (fundo de caixa, R$)</span><input type="text" name="cash_float" value="{{ str_replace('.', ',', $settings['cash_float']) }}" inputmode="decimal"></label>
            <label class="f"><span>Avisar encomendas com antecedência de (horas)</span><input type="number" min="1" max="720" name="order_alert_hours" value="{{ $settings['order_alert_hours'] }}"></label>
            <button class="btn btn-p w100" style="justify-content:center"><x-icon n="check" /> Salvar ajustes</button>
            <p class="muted mb0 mt-s" style="font-size:.78rem">Reserva = maior valor entre o % e o mínimo, nunca maior que o resultado do dia.</p>
        </form>
    </div>
</div>

<dialog id="dlg-user">
    <div class="card-h"><h2>Novo usuário</h2><div class="r"><button class="btn btn-ghost btn-ico" data-close><x-icon n="x" /></button></div></div>
    <form class="card-b" method="POST" action="{{ route('users.store') }}">
        @csrf
        <label class="f"><span>Nome</span><input type="text" name="name" required></label>
        <label class="f"><span>E-mail (login)</span><input type="email" name="email" required></label>
        <div class="row">
            <label class="f"><span>Perfil</span><select name="role">@foreach (User::ROLES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label class="f"><span>Senha inicial</span><input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
        </div>
        <button class="btn btn-p w100" style="justify-content:center"><x-icon n="check" /> Criar</button>
    </form>
</dialog>

@foreach ($users as $user)
<dialog id="dlg-u{{ $user->id }}">
    <div class="card-h"><h2>{{ $user->name }}</h2><div class="r"><button class="btn btn-ghost btn-ico" data-close><x-icon n="x" /></button></div></div>
    <form class="card-b" method="POST" action="{{ route('users.update', $user) }}">
        @csrf @method('PUT')
        <label class="f"><span>Nome</span><input type="text" name="name" value="{{ $user->name }}" required></label>
        <label class="f"><span>E-mail</span><input type="email" name="email" value="{{ $user->email }}" required></label>
        <div class="row">
            <label class="f"><span>Perfil</span><select name="role">@foreach (User::ROLES as $k => $l)<option value="{{ $k }}" @selected($user->role === $k)>{{ $l }}</option>@endforeach</select></label>
            <label class="f"><span>Nova senha (opcional)</span><input type="password" name="password" minlength="6" autocomplete="new-password"></label>
        </div>
        <label class="check mb"><input type="checkbox" name="active" value="1" @checked($user->active)> Acesso ativo</label>
        <button class="btn btn-p w100" style="justify-content:center"><x-icon n="check" /> Salvar</button>
    </form>
</dialog>
@endforeach
@endsection
