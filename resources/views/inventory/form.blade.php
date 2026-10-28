@extends('layouts.app')
@section('title', $po->exists ? 'Pedido #'.$po->id : 'Novo pedido de compra')
@section('content')
<div class="page-head">
    <div><h1>{{ $po->exists ? 'Editar pedido de compra #'.$po->id : 'Novo pedido de compra' }}</h1></div>
    <div class="actions"><a class="btn" href="{{ route('inventory.index') }}"><x-icon n="left" /> Voltar</a></div>
</div>
<form method="POST" action="{{ $po->exists ? route('inventory.po.update', $po) : route('inventory.po.store') }}">
    @csrf @if ($po->exists) @method('PUT') @endif
    <div class="grid g-side">
        <div class="card">
            <div class="card-h"><x-icon n="list" /><h2>Itens</h2></div>
            <div class="card-b">@include('partials.items', ['items' => $po->items ?? collect(), 'products' => $products, 'priceField' => 'unit_cost', 'priceAttr' => 'cost'])</div>
        </div>
        <div class="stack">
            <div class="card"><div class="card-b">
                <label class="f"><span>Fornecedor</span><select name="supplier_id"><option value="">—</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id', $po->supplier_id) == $s->id)>{{ $s->name }}</option>@endforeach</select></label>
                <label class="f"><span>Previsão de entrega</span><input type="date" name="expected_date" value="{{ old('expected_date', $po->expected_date?->toDateString()) }}"></label>
                <label class="f"><span>Observações</span><textarea name="notes">{{ old('notes', $po->notes) }}</textarea></label>
            </div></div>
            <button class="btn btn-p btn-lg w100" style="justify-content:center"><x-icon n="check" /> Salvar pedido</button>
        </div>
    </div>
</form>
@endsection
