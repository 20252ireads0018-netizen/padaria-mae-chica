@extends('layouts.app')
@section('title', 'Pedido de compra #'.$po->id)
@section('content')
@php use App\Models\PurchaseOrder; use App\Models\FinancialEntry; @endphp
<div class="page-head">
    <div><h1>Pedido de compra #{{ $po->id }}</h1><div class="sub">{{ $po->supplier?->name ?? 'Sem fornecedor' }} · previsão {{ $po->expected_date?->format('d/m/Y') ?? '—' }} @include('partials.status', ['s' => $po->status])</div></div>
    <div class="actions">
        <a class="btn" href="{{ route('inventory.index') }}"><x-icon n="left" /> Voltar</a>
        <button class="btn" onclick="window.print()"><x-icon n="print" /> Imprimir</button>
        @if ($po->status !== 'recebido')
            <a class="btn btn-y" href="{{ route('inventory.po.edit', $po) }}"><x-icon n="edit" /> Editar</a>
            <form class="inline" method="POST" action="{{ route('inventory.po.destroy', $po) }}" data-confirm="Excluir este pedido?">@csrf @method('DELETE')<button class="btn btn-danger"><x-icon n="trash" /></button></form>
        @endif
    </div>
</div>
<div class="grid g-side">
    <div class="card">
        <table class="t">
            <thead><tr><th>Item</th><th class="num">Qtd.</th><th class="num">Custo un.</th><th class="num">Subtotal</th></tr></thead>
            <tbody>
            @foreach ($po->items as $i)
                <tr><td>{{ $i->description }}</td><td class="num b">{{ qty($i->quantity) }} {{ $i->product?->unit }}</td><td class="num">{{ brl($i->unit_cost) }}</td><td class="num">{{ brl($i->quantity * $i->unit_cost) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot><tr><td colspan="3">Total</td><td class="num">{{ brl($po->total) }}</td></tr></tfoot>
        </table>
        @if ($po->notes)<div class="card-b muted">{{ $po->notes }}</div>@endif
    </div>
    @if ($po->status !== 'recebido')
        <div class="card">
            <div class="card-h"><h2>Atualizar status</h2></div>
            <form class="card-b" method="POST" action="{{ route('inventory.po.status', $po) }}">
                @csrf @method('PATCH')
                <label class="f"><span>Status</span><select name="status">@foreach (PurchaseOrder::STATUSES as $k => $l)<option value="{{ $k }}" @selected($po->status === $k)>{{ $l }}</option>@endforeach</select></label>
                <label class="f"><span>Pagamento (ao receber)</span><select name="payment_method">@foreach (FinancialEntry::METHODS as $k => $l)<option value="{{ $k }}" @selected($k === 'boleto')>{{ $l }}</option>@endforeach</select></label>
                <button class="btn btn-p w100" style="justify-content:center"><x-icon n="check" /> Salvar</button>
                <p class="muted mb0 mt-s" style="font-size:.78rem">Ao marcar “Recebido”, o estoque e a despesa são lançados automaticamente.</p>
            </form>
        </div>
    @endif
</div>
@endsection
