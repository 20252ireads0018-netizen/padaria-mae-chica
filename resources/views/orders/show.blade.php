@extends('layouts.app')
@section('title', 'Encomenda #'.$order->id)
@section('content')
@php use App\Models\Order; use App\Models\FinancialEntry; $u = auth()->user(); @endphp
<div class="page-head">
    <div>
        <h1>Encomenda #{{ $order->id }} · {{ $order->customer_name }}</h1>
        <div class="sub">Entrega {{ ucfirst($order->due_at->translatedFormat('l, d/m \à\s H:i')) }} ({{ $order->due_at->diffForHumans() }}) @include('partials.status', ['s' => $order->status])
            @if ($order->urgency === 'atrasada')<span class="tag red">Atrasada</span>@endif</div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('orders.index') }}"><x-icon n="left" /> Lista</a>
        <button class="btn" onclick="window.print()"><x-icon n="print" /> Imprimir</button>
        @if ($u->hasRole('caixa'))
            <a class="btn btn-y" href="{{ route('orders.edit', $order) }}"><x-icon n="edit" /> Editar</a>
            <form class="inline" method="POST" action="{{ route('orders.destroy', $order) }}" data-confirm="Excluir esta encomenda? Os lançamentos automáticos também serão removidos.">@csrf @method('DELETE')<button class="btn btn-danger"><x-icon n="trash" /></button></form>
        @endif
    </div>
</div>

<div class="grid g-side">
    <div class="card">
        <div class="card-h"><x-icon n="list" /><h2>Itens</h2></div>
        <table class="t">
            <thead><tr><th>Item</th><th class="num">Qtd.</th><th class="num">Preço</th><th class="num">Subtotal</th></tr></thead>
            <tbody>
            @foreach ($order->items as $i)
                <tr><td>{{ $i->description }}</td><td class="num b">{{ qty($i->quantity) }} {{ $i->product?->unit }}</td><td class="num">{{ brl($i->unit_price) }}</td><td class="num">{{ brl($i->subtotal) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot><tr><td colspan="3">Total</td><td class="num">{{ brl($order->total) }}</td></tr></tfoot>
        </table>
        @if ($order->notes)<div class="card-b"><b>Observações:</b> {{ $order->notes }}</div>@endif
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-h"><x-icon n="wallet" /><h2>Pagamento</h2></div>
            <div class="card-b ledger">
                <div class="ln"><span class="k">Total</span><span class="b">{{ brl($order->total) }}</span></div>
                <div class="ln"><span class="k">Sinal</span><span>{{ brl($order->deposit) }}</span></div>
                <div class="ln sum"><span>{{ $order->paid ? 'Pago' : 'Falta receber' }}</span><span class="{{ $order->paid ? 'pos' : 'neg' }}">{{ $order->paid ? brl($order->total) : brl($order->balance) }}</span></div>
            </div>
        </div>
        <div class="card">
            <div class="card-h"><x-icon n="right" /><h2>Andamento</h2></div>
            <form class="card-b" method="POST" action="{{ route('orders.status', $order) }}">
                @csrf @method('PATCH')
                <label class="f"><span>Status</span><select name="status">@foreach (Order::STATUSES as $k => $l)<option value="{{ $k }}" @selected($order->status === $k)>{{ $l }}</option>@endforeach</select></label>
                @if ($u->hasRole('caixa'))
                    <div class="row">
                        <label class="check auto"><input type="hidden" name="paid" value="0"><input type="checkbox" name="paid" value="1" @checked($order->paid)> Pago</label>
                        <select name="payment_method">@foreach (FinancialEntry::METHODS as $k => $l)<option value="{{ $k }}" @selected($order->payment_method === $k)>{{ $l }}</option>@endforeach</select>
                    </div>
                @endif
                <button class="btn btn-p w100 mt" style="justify-content:center"><x-icon n="check" /> Atualizar</button>
            </form>
        </div>
        <div class="card"><div class="card-b">
            <div class="flex"><x-icon n="phone" /> {{ $order->customer_phone ?: 'Sem telefone' }}</div>
            @if ($order->customer_phone)
                <a class="btn btn-sm mt-s" target="_blank" rel="noopener" href="https://wa.me/55{{ preg_replace('/\D/', '', $order->customer_phone) }}">Abrir WhatsApp</a>
            @endif
            <p class="muted mb0 mt-s" style="font-size:.78rem">Registrada por {{ $order->user?->name ?? '—' }} em {{ $order->created_at->format('d/m/Y H:i') }}</p>
        </div></div>
    </div>
</div>
@endsection
