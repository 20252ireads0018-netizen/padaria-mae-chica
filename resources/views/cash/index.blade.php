@extends('layouts.app')
@section('title', 'Caixa')
@section('content')
@php use App\Models\FinancialEntry; $d = \Illuminate\Support\Carbon::parse($date); @endphp
<div class="page-head">
    <div>
        <h1>Caixa do dia</h1>
        <div class="sub">{{ ucfirst($d->translatedFormat('l, d \d\e F \d\e Y')) }} · {{ $summary['count'] }} lançamentos ({{ $summary['auto'] }} automáticos)</div>
    </div>
    <div class="actions">
        <form class="filters" method="GET">
            <a class="btn btn-ico" href="?data={{ $d->copy()->subDay()->toDateString() }}"><x-icon n="left" /></a>
            <input type="date" name="data" value="{{ $date }}" data-autosubmit>
            <a class="btn btn-ico" href="?data={{ $d->copy()->addDay()->toDateString() }}"><x-icon n="right" /></a>
        </form>
        @if ($closing)
            <a class="btn btn-g" href="{{ route('cash.closing.show', $closing) }}"><x-icon n="check" /> Caixa fechado</a>
        @else
            <a class="btn btn-p" href="{{ route('cash.closing.create', ['data' => $date]) }}"><x-icon n="lock" /> Fechar caixa</a>
        @endif
    </div>
</div>

<div class="flow mb">
    <div class="res"><div class="l">Resultado do dia</div><div class="v {{ $summary['result'] >= 0 ? '' : 'neg' }}">{{ brl($summary['result']) }}</div><small>{{ brl($summary['income']) }} entradas − {{ brl($summary['expense']) }} saídas</small></div>
    <div class="rsv"><div class="l">Reserva p/ fluxo de caixa</div><div class="v">{{ brl($summary['reserve']) }}</div><small>{{ qty($summary['percent']) }}% do resultado (mín. {{ brl($summary['minimum']) }})</small></div>
    <div class="avl"><div class="l">Disponível</div><div class="v">{{ brl($summary['available']) }}</div><small>Gaveta esperada: <b>{{ brl($summary['drawer']) }}</b></small></div>
</div>

<div class="grid g-side">
    <div class="card">
        <div class="card-h"><x-icon n="list" /><h2>Lançamentos</h2>
            <div class="r">
                @foreach (FinancialEntry::METHODS as $k => $m)
                    @if ($summary['in'][$k] > 0)<span class="tag yel">{{ $m }} {{ brl($summary['in'][$k]) }}</span>@endif
                @endforeach
            </div>
        </div>
        <div class="table-wrap">
            <table class="t">
                <thead><tr><th>Hora</th><th>Categoria</th><th>Descrição</th><th>Forma</th><th class="num">Valor</th><th></th></tr></thead>
                <tbody>
                @forelse ($entries as $e)
                    <tr>
                        <td class="muted">{{ $e->created_at->format('H:i') }}</td>
                        <td><span class="dot" style="background: {{ $e->type === 'receita' ? 'var(--yel)' : 'var(--red)' }}"></span> {{ $e->category }}</td>
                        <td>{{ $e->description }} @if ($e->isAutomatic())<span class="tag blue" title="Gerado por encomenda, escola ou compra">auto</span>@endif</td>
                        <td>{{ FinancialEntry::METHODS[$e->payment_method] ?? $e->payment_method }}</td>
                        <td class="num b {{ $e->type === 'receita' ? 'pos' : 'neg' }}">{{ $e->type === 'receita' ? '+' : '−' }} {{ brl($e->amount) }}</td>
                        <td class="act">
                            @unless ($e->isAutomatic() || ($closing && ! auth()->user()->hasRole()))
                                <form class="inline" method="POST" action="{{ route('cash.entries.destroy', $e) }}" data-confirm="Excluir este lançamento?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost btn-danger btn-ico"><x-icon n="trash" /></button></form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty"><x-icon n="cash" />Nenhum lançamento neste dia.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-h"><x-icon n="plus" /><h2>Novo lançamento</h2></div>
            <form class="card-b" method="POST" action="{{ route('cash.entries.store') }}" data-entry-form data-categories='@json(FinancialEntry::CATEGORIES)'>
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <div class="row">
                    <label class="f"><span>Tipo</span><select name="type"><option value="receita">Receita (entrada)</option><option value="despesa">Despesa (saída)</option></select></label>
                    <label class="f"><span>Categoria</span><select name="category"></select></label>
                </div>
                <div class="row">
                    <label class="f"><span>Valor (R$)</span><input type="text" name="amount" inputmode="decimal" placeholder="0,00" required></label>
                    <label class="f"><span>Forma</span><select name="payment_method">@foreach (FinancialEntry::METHODS as $k => $m)<option value="{{ $k }}">{{ $m }}</option>@endforeach</select></label>
                </div>
                <label class="f"><span>Descrição</span><input type="text" name="description" placeholder="Ex.: vendas do balcão manhã"></label>
                <button class="btn btn-p w100" style="justify-content:center" @disabled($closing && ! auth()->user()->hasRole())><x-icon n="check" /> Lançar</button>
                <p class="muted mb0 mt-s" style="font-size:.76rem">Encomendas pagas, pedidos de escolas faturados e compras recebidas entram aqui automaticamente.</p>
            </form>
        </div>

        <div class="card">
            <div class="card-h"><x-icon n="lock" /><h2>Fechamentos recentes</h2></div>
            <div class="list">
                @forelse ($closings as $c)
                    <a href="{{ route('cash.closing.show', $c) }}">
                        <div class="grow"><div class="t1">{{ $c->date->format('d/m/Y') }} <small class="muted">{{ weekday_name($c->date->dayOfWeek, true) }}</small></div><div class="t2">Reserva {{ brl($c->reserve_amount) }} · {{ $c->user?->name }}</div></div>
                        <div class="right"><div class="b {{ $c->total_income - $c->total_expense >= 0 ? 'pos' : 'neg' }}">{{ brl($c->total_income - $c->total_expense) }}</div>
                            @if ($c->counted_cash !== null && abs($c->difference) >= 0.01)<div class="t2 {{ $c->difference > 0 ? 'pos' : 'neg' }}">dif. {{ brl($c->difference) }}</div>@endif
                        </div>
                    </a>
                @empty
                    <div class="empty">Nenhum fechamento ainda.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
