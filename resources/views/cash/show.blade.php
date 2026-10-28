@extends('layouts.app')
@section('title', 'Fechamento '.$c->date->format('d/m'))
@section('content')
@php use App\Models\FinancialEntry; $result = $c->total_income - $c->total_expense; @endphp
<div class="page-head">
    <div>
        <h1>Fechamento · {{ $c->date->format('d/m/Y') }}</h1>
        <div class="sub">Fechado por {{ $c->user?->name ?? '—' }} em {{ $c->created_at->format('d/m/Y H:i') }}</div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('cash.index', ['data' => $c->date->toDateString()]) }}"><x-icon n="left" /> Caixa do dia</a>
        <button class="btn" onclick="window.print()"><x-icon n="print" /> Imprimir</button>
        @if (auth()->user()->hasRole())
            <form class="inline" method="POST" action="{{ route('cash.closing.destroy', $c) }}" data-confirm="Reabrir o caixa deste dia? O fechamento será removido.">@csrf @method('DELETE')<button class="btn btn-danger"><x-icon n="repeat" /> Reabrir</button></form>
        @endif
    </div>
</div>

<div class="flow mb">
    <div class="res"><div class="l">Resultado do dia</div><div class="v">{{ brl($result) }}</div></div>
    <div class="rsv"><div class="l">Reservado p/ fluxo de caixa</div><div class="v">{{ brl($c->reserve_amount) }}</div></div>
    <div class="avl"><div class="l">Disponível</div><div class="v">{{ brl($c->available_balance) }}</div></div>
</div>

<div class="grid g3">
    <div class="card"><div class="card-h"><h2>Entradas</h2><div class="r b pos">{{ brl($c->total_income) }}</div></div>
        <div class="card-b ledger">
            <div class="ln"><span class="k">Dinheiro</span><span>{{ brl($c->income_cash) }}</span></div>
            <div class="ln"><span class="k">PIX</span><span>{{ brl($c->income_pix) }}</span></div>
            <div class="ln"><span class="k">Cartão</span><span>{{ brl($c->income_card) }}</span></div>
            <div class="ln"><span class="k">Boleto / outros</span><span>{{ brl($c->income_other) }}</span></div>
        </div>
    </div>
    <div class="card"><div class="card-h"><h2>Gaveta</h2></div>
        <div class="card-b ledger">
            <div class="ln"><span class="k">Troco inicial</span><span>{{ brl($c->opening_balance) }}</span></div>
            <div class="ln"><span class="k">Esperado</span><span>{{ brl($c->expected_balance) }}</span></div>
            <div class="ln"><span class="k">Contado</span><span>{{ $c->counted_cash !== null ? brl($c->counted_cash) : '—' }}</span></div>
            <div class="ln sum"><span>Diferença</span><span class="{{ $c->difference < 0 ? 'neg' : 'pos' }}">{{ brl($c->difference) }}</span></div>
        </div>
    </div>
    <div class="card"><div class="card-h"><h2>Saídas</h2><div class="r b neg">{{ brl($c->total_expense) }}</div></div>
        <div class="card-b">{{ $c->notes ?: 'Sem observações.' }}</div>
    </div>
</div>

<div class="card mt">
    <div class="card-h"><x-icon n="list" /><h2>Lançamentos do dia</h2></div>
    <table class="t">
        <thead><tr><th>Tipo</th><th>Categoria</th><th>Descrição</th><th>Forma</th><th class="num">Valor</th></tr></thead>
        <tbody>
        @foreach ($entries as $e)
            <tr><td>{{ ucfirst($e->type) }}</td><td>{{ $e->category }}</td><td>{{ $e->description }}</td><td>{{ FinancialEntry::METHODS[$e->payment_method] ?? '' }}</td><td class="num b {{ $e->type === 'receita' ? 'pos' : 'neg' }}">{{ brl($e->amount) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
