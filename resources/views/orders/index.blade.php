@extends('layouts.app')
@section('title', 'Encomendas')
@section('content')
@php use App\Models\Order; $canEdit = auth()->user()->hasRole('caixa'); @endphp
<div class="page-head">
    <div><h1>Encomendas</h1><div class="sub">Pães, tortas, salgados e bolos sob encomenda — com prazos, quantidades e valores.</div></div>
    <div class="actions">@if ($canEdit)<a class="btn btn-p" href="{{ route('orders.create') }}"><x-icon n="plus" /> Nova encomenda</a>@endif</div>
</div>

<div class="grid g4 mb">
    <div class="card kpi"><div class="ic"><x-icon n="bag" /></div><div class="l">Em aberto</div><div class="v">{{ $stats['abertas'] }}</div></div>
    <div class="card kpi red"><div class="ic"><x-icon n="alert" /></div><div class="l">Atrasadas</div><div class="v">{{ $stats['atrasadas'] }}</div></div>
    <div class="card kpi blue"><div class="ic"><x-icon n="clock" /></div><div class="l">Para hoje</div><div class="v">{{ $stats['hoje'] }}</div></div>
    <div class="card kpi green"><div class="ic"><x-icon n="wallet" /></div><div class="l">A receber</div><div class="v">{{ brl($stats['a_receber']) }}</div></div>
</div>

<div class="card">
    <div class="card-h wrap">
        <div class="seg">
            @foreach (['abertas' => 'Abertas', 'hoje' => 'Hoje', 'semana' => '7 dias', 'atrasadas' => 'Atrasadas', 'concluidas' => 'Concluídas'] as $k => $l)
                <a class="{{ $view === $k ? 'on' : '' }}" href="?ver={{ $k }}">{{ $l }}@if ($k === 'atrasadas' && $stats['atrasadas'])<span class="n">{{ $stats['atrasadas'] }}</span>@endif</a>
            @endforeach
        </div>
        <form class="r filters"><input type="hidden" name="ver" value="{{ $view }}"><input type="search" name="busca" value="{{ request('busca') }}" placeholder="Buscar cliente ou telefone"></form>
    </div>
    <div class="table-wrap">
        <table class="t">
            <thead><tr><th>Prazo</th><th>Cliente</th><th>Itens</th><th class="num">Total</th><th class="num">Sinal</th><th class="num">Falta</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($orders as $o)
                <tr class="{{ $o->urgency === 'atrasada' ? 'row-red' : ($o->urgency === 'hoje' ? 'row-yel' : '') }}">
                    <td class="nowrap">
                        <b>{{ $o->due_at->format('d/m H:i') }}</b><br>
                        <small class="{{ $o->urgency === 'atrasada' ? 'neg b' : 'muted' }}">{{ $o->urgency === 'atrasada' ? 'atrasada '.$o->due_at->diffForHumans(null, true) : $o->due_at->diffForHumans() }}</small>
                    </td>
                    <td><a class="b" href="{{ route('orders.show', $o) }}">{{ $o->customer_name }}</a><br><small class="muted">#{{ $o->id }} · {{ $o->customer_phone }}</small></td>
                    <td style="max-width:320px"><small>{{ $o->items->map(fn ($i) => qty($i->quantity).'× '.$i->description)->join(' · ') }}</small></td>
                    <td class="num b">{{ brl($o->total) }}</td>
                    <td class="num">{{ $o->deposit > 0 ? brl($o->deposit) : '—' }}</td>
                    <td class="num">{!! $o->paid ? '<span class="tag green">Pago</span>' : brl($o->balance) !!}</td>
                    <td>@include('partials.status', ['s' => $o->status])</td>
                    <td class="act">
                        @include('orders.partials.quick', ['o' => $o])
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><x-icon n="bag" />Nenhuma encomenda neste filtro.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $orders->links() }}
</div>
@endsection
