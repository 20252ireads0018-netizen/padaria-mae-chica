@extends('layouts.app')
@section('title', 'Estoque & Compras')
@section('content')
<div class="page-head">
    <div><h1>Estoque & pedidos de compra</h1><div class="sub">Itens abaixo do mínimo, pedidos a fazer e fornecedores.</div></div>
    <div class="actions"><a class="btn btn-p" href="{{ route('inventory.po.create') }}"><x-icon n="plus" /> Novo pedido de compra</a></div>
</div>

<div class="grid g-main">
    <div class="stack">
        <form class="card" method="POST" action="{{ route('inventory.po.generate') }}">
            @csrf
            <div class="card-h"><x-icon n="alert" /><h2>Estoque baixo ({{ $low->count() }})</h2>
                @if ($low->count())
                    <div class="r">
                        <select name="supplier_id" style="width:auto;padding:4px 8px"><option value="">Fornecedor (opcional)</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
                        <button class="btn btn-sm btn-p"><x-icon n="wand" /> Gerar pedido com selecionados</button>
                    </div>
                @endif
            </div>
            <div class="table-wrap">
                <table class="t">
                    <thead><tr><th style="width:30px"><a href="#" data-checkall="product_ids[]" title="Marcar todos"><x-icon n="check" /></a></th><th>Produto</th><th class="num">Atual</th><th class="num">Mínimo</th><th style="width:22%">Nível</th><th class="num">Sugestão</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($low as $p)
                        @php $inPo = in_array($p->id, $pendingIds); @endphp
                        <tr>
                            <td><input type="checkbox" name="product_ids[]" value="{{ $p->id }}" @checked(! $inPo)></td>
                            <td><a class="b" href="{{ route('products.show', $p) }}">{{ $p->name }}</a><br><small class="muted">{{ $p->category }}</small></td>
                            <td class="num b neg">{{ qty($p->stock) }} {{ $p->unit }}</td>
                            <td class="num">{{ qty($p->min_stock) }}</td>
                            <td><div class="hbar red"><i style="width: {{ min(100, $p->stock / $p->min_stock * 100) }}%"></i></div></td>
                            <td class="num">{{ qty(max(1, $p->suggested_order)) }} {{ $p->unit }}<br><small class="muted">≈ {{ brl(max(1, $p->suggested_order) * $p->cost) }}</small></td>
                            <td>@if ($inPo)<span class="tag blue">já pedido</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><x-icon n="check" />Nenhum item abaixo do mínimo.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div class="card">
            <div class="card-h"><x-icon n="truck" /><h2>Pedidos de compra</h2></div>
            <div class="table-wrap">
                <table class="t">
                    <thead><tr><th>#</th><th>Fornecedor</th><th>Previsão</th><th class="num">Itens</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($orders as $po)
                        <tr>
                            <td><a class="b" href="{{ route('inventory.po.show', $po) }}">#{{ $po->id }}</a></td>
                            <td>{{ $po->supplier?->name ?? '—' }}</td>
                            <td>{{ $po->expected_date?->format('d/m') ?? '—' }}@if ($po->expected_date && $po->expected_date->isPast() && in_array($po->status, ['a_fazer', 'enviado'])) <span class="tag red">atrasado</span>@endif</td>
                            <td class="num">{{ $po->items_count }}</td>
                            <td class="num b">{{ brl($po->total) }}</td>
                            <td>@include('partials.status', ['s' => $po->status])</td>
                            <td class="act">
                                @if ($po->status === 'a_fazer')
                                    <form class="inline" method="POST" action="{{ route('inventory.po.status', $po) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="enviado"><button class="btn btn-sm btn-y">Marcar enviado</button></form>
                                @elseif ($po->status === 'enviado')
                                    <form class="inline" method="POST" action="{{ route('inventory.po.status', $po) }}" data-confirm="Confirmar recebimento? O estoque será atualizado e a despesa lançada no caixa.">@csrf @method('PATCH')<input type="hidden" name="status" value="recebido"><button class="btn btn-sm btn-g">Receber</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">Nenhum pedido de compra.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-h"><x-icon n="truck" /><h2>Fornecedores</h2></div>
            <form class="card-b" method="POST" action="{{ route('inventory.suppliers.store') }}">
                @csrf
                <label class="f"><span>Nome</span><input type="text" name="name" required></label>
                <div class="row"><label class="f"><span>Telefone</span><input type="tel" name="phone"></label><label class="f"><span>E-mail</span><input type="email" name="email"></label></div>
                <button class="btn btn-sm btn-p"><x-icon n="plus" /> Cadastrar</button>
            </form>
            <div class="list">
                @foreach ($suppliers as $s)
                    <div>
                        <div class="grow"><div class="t1">{{ $s->name }}</div><div class="t2">{{ $s->phone }} {{ $s->email }} · {{ $s->purchase_orders_count }} pedido(s)</div></div>
                        @if (! $s->purchase_orders_count)<form class="inline" method="POST" action="{{ route('inventory.suppliers.destroy', $s) }}" data-confirm="Remover fornecedor?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost btn-danger btn-ico"><x-icon n="x" /></button></form>@endif
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card"><div class="card-b muted" style="font-size:.82rem">
            <b>Fluxo:</b> selecione os itens em falta → <b>Gerar pedido</b> → revise e envie ao fornecedor → ao <b>Receber</b>, o estoque sobe, o custo do produto é atualizado e a despesa entra no caixa do dia.
        </div></div>
    </div>
</div>
@endsection
