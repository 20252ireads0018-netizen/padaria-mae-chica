@extends('layouts.app')
@section('title', $order->exists ? 'Editar encomenda' : 'Nova encomenda')
@section('content')
@php use App\Models\Order; use App\Models\FinancialEntry; @endphp
<div class="page-head">
    <div><h1>{{ $order->exists ? 'Editar encomenda #'.$order->id : 'Nova encomenda' }}</h1><div class="sub">Os valores pagos (sinal ou total) entram no caixa automaticamente.</div></div>
    <div class="actions"><a class="btn" href="{{ $order->exists ? route('orders.show', $order) : route('orders.index') }}"><x-icon n="left" /> Voltar</a></div>
</div>

<form method="POST" action="{{ $order->exists ? route('orders.update', $order) : route('orders.store') }}">
    @csrf @if ($order->exists) @method('PUT') @endif
    <div class="grid g-side">
        <div class="card">
            <div class="card-h"><x-icon n="list" /><h2>Itens</h2></div>
            <div class="card-b">@include('partials.items', ['items' => $order->items ?? collect(), 'products' => $products])</div>
        </div>
        <div class="stack">
            <div class="card">
                <div class="card-h"><x-icon n="users" /><h2>Cliente e prazo</h2></div>
                <div class="card-b">
                    <label class="f"><span>Nome do cliente *</span><input type="text" name="customer_name" value="{{ old('customer_name', $order->customer_name) }}" required></label>
                    <label class="f"><span>Telefone / WhatsApp</span><input type="tel" name="customer_phone" value="{{ old('customer_phone', $order->customer_phone) }}"></label>
                    <div class="row">
                        <label class="f"><span>Data de entrega *</span><input type="date" name="due_date" value="{{ old('due_date', $order->due_at?->toDateString()) }}" required></label>
                        <label class="f"><span>Hora *</span><input type="time" name="due_time" value="{{ old('due_time', $order->due_at?->format('H:i')) }}" required></label>
                    </div>
                    <label class="f"><span>Status</span><select name="status">@foreach (Order::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $order->status ?? 'pendente') === $k)>{{ $l }}</option>@endforeach</select></label>
                </div>
            </div>
            <div class="card">
                <div class="card-h"><x-icon n="wallet" /><h2>Pagamento</h2></div>
                <div class="card-b">
                    <div class="row">
                        <label class="f"><span>Sinal recebido (R$)</span><input type="text" name="deposit" value="{{ old('deposit', $order->deposit ? number_format($order->deposit, 2, ',', '') : '') }}" inputmode="decimal" placeholder="0,00"></label>
                        <label class="f"><span>Forma</span><select name="payment_method">@foreach (FinancialEntry::METHODS as $k => $l)<option value="{{ $k }}" @selected(old('payment_method', $order->payment_method ?? 'pix') === $k)>{{ $l }}</option>@endforeach</select></label>
                    </div>
                    <label class="check"><input type="hidden" name="paid" value="0"><input type="checkbox" name="paid" value="1" @checked(old('paid', $order->paid))> Totalmente pago</label>
                    <label class="f mt"><span>Observações (recheio, decoração, retirada/entrega...)</span><textarea name="notes">{{ old('notes', $order->notes) }}</textarea></label>
                </div>
            </div>
            <button class="btn btn-p btn-lg w100" style="justify-content:center"><x-icon n="check" /> Salvar encomenda</button>
        </div>
    </div>
</form>
@endsection
