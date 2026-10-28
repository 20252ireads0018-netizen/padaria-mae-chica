@extends('layouts.app')
@section('title', $school->name)
@section('content')
@php
    use App\Models\SchoolOrder;
    $canEdit = auth()->user()->hasRole('caixa');
    $valid = $orders->where('status', '!=', 'cancelada');
    $delivered = $valid->where('status', 'entregue');
    $toBill = $delivered->where('paid', false);
    $qtyByItem = $valid->flatMap->items->groupBy('description')->map->sum('quantity');
@endphp
<div class="page-head">
    <div><h1>{{ $school->name }}</h1><div class="sub">{{ $school->contact_name }} · {{ $school->phone }} · {{ $school->address }} · faturamento {{ strtolower(\App\Models\School::BILLING[$school->billing]) }}</div></div>
    <div class="actions">
        <a class="btn" href="{{ route('schools.index') }}"><x-icon n="left" /> Escolas</a>
        <form class="filters inline"><input type="month" name="mes" value="{{ $month->format('Y-m') }}" data-autosubmit></form>
        <button class="btn" onclick="window.print()"><x-icon n="print" /> Relatório</button>
        @if ($canEdit)
            <button class="btn btn-y" data-open="dlg-edit"><x-icon n="edit" /> Editar</button>
            <a class="btn btn-p" href="{{ route('schools.orders.create', ['escola' => $school->id]) }}"><x-icon n="plus" /> Pedido</a>
        @endif
    </div>
</div>

<div class="grid g4 mb">
    <div class="card kpi"><div class="l">Entregas no mês</div><div class="v">{{ $valid->count() }}</div><div class="s">{{ $delivered->count() }} entregues</div></div>
    <div class="card kpi blue"><div class="l">Valor do mês</div><div class="v">{{ brl($valid->sum('total')) }}</div></div>
    <div class="card kpi green"><div class="l">Recebido</div><div class="v">{{ brl($valid->where('paid', true)->sum('total')) }}</div></div>
    <div class="card kpi red"><div class="l">A faturar (entregue)</div><div class="v">{{ brl($toBill->sum('total')) }}</div>
        @if ($canEdit && $toBill->count())
            <form method="POST" action="{{ route('schools.pay', $school) }}" class="mt-s" data-confirm="Marcar {{ $toBill->count() }} entregas como pagas?">@csrf<input type="hidden" name="mes" value="{{ $month->format('Y-m') }}"><button class="btn btn-sm btn-g"><x-icon n="check" /> Faturar mês</button></form>
        @endif
    </div>
</div>

<div class="grid g-side">
    <div class="card">
        <div class="card-h"><x-icon n="list" /><h2>{{ month_name($month->month) }} de {{ $month->year }}</h2></div>
        <table class="t">
            <thead><tr><th>Data</th><th>Turno</th><th>Itens</th><th class="num">Valor</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($orders as $o)
                <tr>
                    <td class="nowrap">{{ $o->delivery_date->format('d/m') }} <small class="muted">{{ weekday_name($o->delivery_date->dayOfWeek, true) }}</small></td>
                    <td>{{ SchoolOrder::SHIFTS[$o->shift] }}</td>
                    <td><small>{{ $o->items->map(fn ($i) => qty($i->quantity).' '.$i->description)->join(' · ') }}</small></td>
                    <td class="num b">{{ brl($o->total) }}</td>
                    <td>@include('partials.status', ['s' => $o->status]) @if ($o->paid)<span class="tag green">Pago</span>@endif</td>
                    <td class="act">@if ($canEdit)<a class="btn btn-sm btn-ghost btn-ico" href="{{ route('schools.orders.edit', $o) }}"><x-icon n="edit" /></a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty">Nenhum pedido neste mês.</div></td></tr>
            @endforelse
            </tbody>
            @if ($orders->count())<tfoot><tr><td colspan="3">Total do mês</td><td class="num">{{ brl($valid->sum('total')) }}</td><td colspan="2"></td></tr></tfoot>@endif
        </table>
    </div>
    <div class="card">
        <div class="card-h"><x-icon n="bread" /><h2>Quantidades no mês</h2></div>
        <table class="t">
            @foreach ($qtyByItem as $d => $q)<tr><td>{{ $d }}</td><td class="num b">{{ qty($q) }}</td></tr>@endforeach
        </table>
    </div>
</div>

@if ($canEdit)
<dialog id="dlg-edit">
    <div class="card-h"><h2>Editar escola</h2><div class="r"><button class="btn btn-ghost btn-ico" data-close><x-icon n="x" /></button></div></div>
    <form class="card-b" method="POST" action="{{ route('schools.update', $school) }}">
        @csrf @method('PUT')
        @include('schools.partials.fields', ['s' => $school])
        <label class="check mb"><input type="checkbox" name="active" value="1" @checked($school->active)> Escola ativa</label>
        <div class="flex"><button class="btn btn-p" style="flex:1;justify-content:center"><x-icon n="check" /> Salvar</button></div>
    </form>
    <form class="card-b" style="padding-top:0" method="POST" action="{{ route('schools.destroy', $school) }}" data-confirm="Remover esta escola?">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><x-icon n="trash" /> Remover / desativar</button></form>
</dialog>
@endif
@endsection
