@extends('layouts.app')
@section('title', $product->exists ? 'Editar produto' : 'Novo produto')
@section('content')
@php use App\Models\Product; $f = fn ($v) => $v !== null && $v !== '' ? str_replace('.', ',', (string) (float) $v) : ''; @endphp
<div class="page-head">
    <div><h1>{{ $product->exists ? $product->name : 'Novo produto' }}</h1><div class="sub">Produtos de venda aparecem em encomendas e escolas; insumos aparecem nas compras.</div></div>
    <div class="actions">
        <a class="btn" href="{{ route('products.index') }}"><x-icon n="left" /> Produtos</a>
        @if ($product->exists)
            <form class="inline" method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="Desativar este produto?">@csrf @method('DELETE')<button class="btn btn-danger"><x-icon n="trash" /> Desativar</button></form>
        @endif
    </div>
</div>

<form class="card" style="max-width:760px" method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
    @csrf @if ($product->exists) @method('PUT') @endif
    <div class="card-b">
        <div class="row">
            <label class="f" style="flex:2"><span>Nome *</span><input type="text" name="name" value="{{ old('name', $product->name) }}" required></label>
            <label class="f"><span>Código / SKU</span><input type="text" name="sku" value="{{ old('sku', $product->sku) }}"></label>
        </div>
        <div class="row">
            <label class="f"><span>Categoria</span><select name="category">@foreach (Product::CATEGORIES as $c)<option @selected(old('category', $product->category) === $c)>{{ $c }}</option>@endforeach</select></label>
            <label class="f"><span>Unidade</span><select name="unit">@foreach (Product::UNITS as $c)<option @selected(old('unit', $product->unit) === $c)>{{ $c }}</option>@endforeach</select></label>
        </div>
        <div class="row">
            <label class="f"><span>Preço de venda (R$)</span><input type="text" name="price" value="{{ old('price', $f($product->price)) }}" inputmode="decimal"></label>
            <label class="f"><span>Custo (R$)</span><input type="text" name="cost" value="{{ old('cost', $f($product->cost)) }}" inputmode="decimal"></label>
        </div>
        <div class="row">
            <label class="f"><span>Estoque atual</span><input type="text" name="stock" value="{{ old('stock', $f($product->stock)) }}" inputmode="decimal"></label>
            <label class="f"><span>Estoque mínimo (alerta)</span><input type="text" name="min_stock" value="{{ old('min_stock', $f($product->min_stock)) }}" inputmode="decimal"></label>
        </div>
        <div class="flex wrap" style="gap:18px">
            <label class="check"><input type="checkbox" name="is_supply" value="1" @checked(old('is_supply', $product->is_supply))> É insumo / embalagem (não vendido)</label>
            <label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $product->active))> Ativo</label>
        </div>
    </div>
    <div class="card-b" style="border-top:1px solid var(--line)"><button class="btn btn-p"><x-icon n="check" /> Salvar</button></div>
</form>
@endsection
