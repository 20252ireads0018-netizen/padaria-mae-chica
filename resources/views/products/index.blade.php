@extends('layouts.app')
@section('title', 'Produtos')
@section('content')
@php use App\Models\Product; $u = auth()->user(); @endphp
<div class="page-head">
    <div><h1>Produtos e insumos</h1><div class="sub">Valor em estoque (custo): <b>{{ brl($stockValue) }}</b> · {{ $lowCount }} item(ns) abaixo do mínimo</div></div>
    <div class="actions">@if ($u->hasRole())<a class="btn btn-p" href="{{ route('products.create') }}"><x-icon n="plus" /> Novo produto</a>@endif</div>
</div>

<div class="card">
    <div class="card-h wrap">
        <div class="seg">
            @foreach (['' => 'Ativos', 'venda' => 'Venda', 'insumo' => 'Insumos', 'baixo' => 'Estoque baixo', 'inativo' => 'Inativos'] as $k => $l)
                <a class="{{ request('tipo', '') === $k ? 'on' : '' }}" href="?{{ http_build_query(array_filter(['tipo' => $k, 'categoria' => request('categoria')])) }}">{{ $l }}@if ($k === 'baixo' && $lowCount)<span class="n">{{ $lowCount }}</span>@endif</a>
            @endforeach
        </div>
        <form class="r filters">
            <input type="hidden" name="tipo" value="{{ request('tipo') }}">
            <select name="categoria" data-autosubmit><option value="">Todas as categorias</option>@foreach (Product::CATEGORIES as $c)<option @selected(request('categoria') === $c)>{{ $c }}</option>@endforeach</select>
            <input type="search" name="busca" value="{{ request('busca') }}" placeholder="Nome ou código">
        </form>
    </div>
    <div class="table-wrap">
        <table class="t">
            <thead><tr><th>Produto</th><th>Categoria</th><th class="num">Preço</th><th class="num">Custo</th><th class="num">Margem</th><th class="num">Estoque</th><th>Movimentar</th><th></th></tr></thead>
            <tbody>
            @forelse ($products as $p)
                <tr class="{{ $p->isLow() ? 'row-red' : '' }}">
                    <td><a class="b" href="{{ route('products.show', $p) }}">{{ $p->name }}</a><br><small class="muted">{{ $p->sku }} @if ($p->is_supply)· insumo @endif</small></td>
                    <td><span class="tag {{ $p->is_supply ? 'blue' : 'yel' }}">{{ $p->category }}</span></td>
                    <td class="num">{{ $p->price > 0 ? brl($p->price) : '—' }}</td>
                    <td class="num muted">{{ brl($p->cost) }}</td>
                    <td class="num">{{ $p->margin !== null ? qty($p->margin).'%' : '—' }}</td>
                    <td class="num"><b class="{{ $p->isLow() ? 'neg' : '' }}">{{ qty($p->stock) }}</b> <small class="muted">{{ $p->unit }}</small>@if ($p->min_stock > 0)<br><small class="muted">mín. {{ qty($p->min_stock) }}</small>@endif</td>
                    <td>
                        <form class="flex" method="POST" action="{{ route('products.adjust', $p) }}" style="gap:4px">
                            @csrf
                            <select name="mode" style="width:auto;padding:4px 6px"><option value="entrada">+ Entrada</option><option value="saida">− Saída</option><option value="contagem">= Contagem</option></select>
                            <input type="text" name="quantity" placeholder="qtd" inputmode="decimal" style="width:64px;padding:4px 6px" required>
                            <input type="hidden" name="reason" value="">
                            <button class="btn btn-sm btn-ico" title="Aplicar"><x-icon n="check" /></button>
                        </form>
                    </td>
                    <td class="act">@if ($u->hasRole())<a class="btn btn-sm btn-ghost btn-ico" href="{{ route('products.edit', $p) }}"><x-icon n="edit" /></a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><x-icon n="bread" />Nenhum produto encontrado.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $products->links() }}
</div>
@endsection
