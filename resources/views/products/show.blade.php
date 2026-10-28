@extends('layouts.app')
@section('title', $product->name)
@section('content')
<div class="page-head">
    <div><h1>{{ $product->name }}</h1><div class="sub">{{ $product->category }} · {{ $product->sku }} · {{ $product->is_supply ? 'Insumo' : 'Produto de venda' }} @unless ($product->active)<span class="tag">inativo</span>@endunless</div></div>
    <div class="actions">
        <a class="btn" href="{{ route('products.index') }}"><x-icon n="left" /> Produtos</a>
        @if (auth()->user()->hasRole())<a class="btn btn-y" href="{{ route('products.edit', $product) }}"><x-icon n="edit" /> Editar</a>@endif
    </div>
</div>

<div class="grid g4 mb">
    <div class="card kpi {{ $product->isLow() ? 'red' : 'green' }}"><div class="l">Estoque</div><div class="v">{{ qty($product->stock) }} {{ $product->unit }}</div><div class="s">mínimo {{ qty($product->min_stock) }}</div></div>
    <div class="card kpi"><div class="l">Preço</div><div class="v">{{ brl($product->price) }}</div></div>
    <div class="card kpi blue"><div class="l">Custo</div><div class="v">{{ brl($product->cost) }}</div><div class="s">em estoque: {{ brl($product->cost * $product->stock) }}</div></div>
    <div class="card kpi green"><div class="l">Margem</div><div class="v">{{ $product->margin !== null ? qty($product->margin).'%' : '—' }}</div></div>
</div>

<div class="grid g-side">
    <div class="card">
        <div class="card-h"><x-icon n="list" /><h2>Movimentações recentes</h2></div>
        <table class="t">
            <thead><tr><th>Quando</th><th>Motivo</th><th>Por</th><th class="num">Qtd.</th></tr></thead>
            <tbody>
            @forelse ($movements as $m)
                <tr><td>{{ $m->created_at->format('d/m/Y H:i') }}</td><td>{{ $m->reason }}</td><td class="muted">{{ $m->user?->name }}</td><td class="num b {{ $m->quantity >= 0 ? 'pos' : 'neg' }}">{{ $m->quantity >= 0 ? '+' : '' }}{{ qty($m->quantity) }}</td></tr>
            @empty
                <tr><td colspan="4"><div class="empty">Sem movimentações registradas.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card">
        <div class="card-h"><x-icon n="box" /><h2>Movimentar estoque</h2></div>
        <form class="card-b" method="POST" action="{{ route('products.adjust', $product) }}">
            @csrf
            <label class="f"><span>Tipo</span><select name="mode"><option value="entrada">Entrada (produção / compra)</option><option value="saida">Saída (venda / perda / consumo)</option><option value="contagem">Contagem (define o valor exato)</option></select></label>
            <label class="f"><span>Quantidade ({{ $product->unit }})</span><input type="text" name="quantity" inputmode="decimal" required></label>
            <label class="f"><span>Motivo</span><input type="text" name="reason" placeholder="Ex.: fornada das 6h, perda"></label>
            <button class="btn btn-p w100" style="justify-content:center"><x-icon n="check" /> Registrar</button>
        </form>
    </div>
</div>
@endsection
