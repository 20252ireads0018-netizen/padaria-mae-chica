@extends('layouts.app')
@section('title', $order->exists ? 'Editar pedido' : 'Novo pedido de escola')
@section('content')
@php use App\Models\SchoolOrder; @endphp
<div class="page-head">
    <div><h1>{{ $order->exists ? 'Editar pedido de escola' : 'Novo pedido de escola' }}</h1><div class="sub">Quantidades de pão e lanches por entrega.</div></div>
    <div class="actions"><a class="btn" href="{{ route('schools.index') }}"><x-icon n="left" /> Voltar</a></div>
</div>

<form method="POST" action="{{ $order->exists ? route('schools.orders.update', $order) : route('schools.orders.store') }}">
    @csrf @if ($order->exists) @method('PUT') @endif
    <div class="grid g-side">
        <div class="card">
            <div class="card-h"><x-icon n="bread" /><h2>Itens da entrega</h2>
                @if (! $order->exists && $order->school_id && $order->items?->count())<div class="r"><span class="tag blue">Itens sugeridos do último pedido</span></div>@endif
            </div>
            <div class="card-b">@include('partials.items', ['items' => $order->items ?? collect(), 'products' => $products])</div>
        </div>
        <div class="stack">
            <div class="card">
                <div class="card-h"><x-icon n="school" /><h2>Entrega</h2></div>
                <div class="card-b">
                    <label class="f"><span>Escola *</span>
                        <select name="school_id" required @unless ($order->exists) onchange="if(this.value) location='?escola='+this.value+'&data='+this.form.delivery_date.value" @endunless>
                            <option value="">Selecione...</option>
                            @foreach ($schools as $s)<option value="{{ $s->id }}" @selected(old('school_id', $order->school_id) == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </label>
                    <div class="row">
                        <label class="f"><span>Data *</span><input type="date" name="delivery_date" value="{{ old('delivery_date', $order->delivery_date?->toDateString()) }}" required></label>
                        <label class="f"><span>Turno</span><select name="shift">@foreach (SchoolOrder::SHIFTS as $k => $l)<option value="{{ $k }}" @selected(old('shift', $order->shift) === $k)>{{ $l }}</option>@endforeach</select></label>
                    </div>
                    @unless ($order->exists)
                        <label class="f"><span>Repetir em todos os dias úteis até (opcional)</span><input type="date" name="repeat_until" value="{{ old('repeat_until') }}"></label>
                    @endunless
                    <label class="f"><span>Status</span><select name="status">@foreach (SchoolOrder::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $order->status) === $k)>{{ $l }}</option>@endforeach</select></label>
                    <label class="check"><input type="hidden" name="paid" value="0"><input type="checkbox" name="paid" value="1" @checked(old('paid', $order->paid))> Pago</label>
                    <label class="f mt"><span>Observações</span><textarea name="notes">{{ old('notes', $order->notes) }}</textarea></label>
                </div>
            </div>
            <button class="btn btn-p btn-lg w100" style="justify-content:center"><x-icon n="check" /> Salvar pedido</button>
        </div>
    </div>
</form>
@endsection
