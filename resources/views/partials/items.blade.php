{{-- Editor de itens. Parâmetros: $items, $products, $priceField (unit_price|unit_cost), $priceAttr (price|cost) --}}
@php
    $priceField ??= 'unit_price';
    $priceAttr ??= 'price';
    $rows = old('items', $items->map(fn ($i) => ['product_id' => $i->product_id, 'description' => $i->description, 'quantity' => qty($i->quantity), $priceField => number_format($i->{$priceField}, 2, ',', '')])->all());
    if (empty($rows)) $rows = [['product_id' => '', 'description' => '', 'quantity' => '', $priceField => '']];
    $opts = function ($sel) use ($products, $priceAttr) {
        $h = '<option value="">— item avulso —</option>';
        foreach ($products as $p) {
            $h .= '<option value="'.$p->id.'" data-'.$priceAttr.'="'.$p->{$priceAttr}.'" '.((string) $sel === (string) $p->id ? 'selected' : '').'>'.e($p->name).'</option>';
        }
        return $h;
    };
@endphp
<div class="items" data-items data-price="{{ $priceAttr }}">
    <div class="it head"><span>Produto</span><span>Descrição</span><span>Qtd.</span><span>{{ $priceField === 'unit_cost' ? 'Custo un.' : 'Preço un.' }}</span><span class="right">Subtotal</span><span></span></div>
    <div class="rows">
        @foreach ($rows as $i => $r)
            <div class="it r">
                <select name="items[{{ $i }}][product_id]">{!! $opts($r['product_id'] ?? '') !!}</select>
                <input type="text" name="items[{{ $i }}][description]" value="{{ $r['description'] ?? '' }}" placeholder="Descrição" data-d>
                <input type="text" name="items[{{ $i }}][quantity]" value="{{ $r['quantity'] ?? '' }}" placeholder="0" inputmode="decimal" data-q>
                <input type="text" name="items[{{ $i }}][{{ $priceField }}]" value="{{ $r[$priceField] ?? '' }}" placeholder="0,00" inputmode="decimal" data-p>
                <span class="sub">R$ 0,00</span>
                <button type="button" class="btn btn-ico btn-ghost btn-danger" data-del title="Remover"><x-icon n="x" /></button>
            </div>
        @endforeach
    </div>
    <template>
        <div class="it r">
            <select name="items[__i__][product_id]">{!! $opts('') !!}</select>
            <input type="text" name="items[__i__][description]" placeholder="Descrição" data-d>
            <input type="text" name="items[__i__][quantity]" placeholder="0" inputmode="decimal" data-q>
            <input type="text" name="items[__i__][{{ $priceField }}]" placeholder="0,00" inputmode="decimal" data-p>
            <span class="sub">R$ 0,00</span>
            <button type="button" class="btn btn-ico btn-ghost btn-danger" data-del title="Remover"><x-icon n="x" /></button>
        </div>
    </template>
    <div class="total-bar">
        <button type="button" class="btn btn-sm" data-add style="margin-right:auto"><x-icon n="plus" /> Adicionar item</button>
        <span class="muted">Total</span><span class="big" data-total>R$ 0,00</span>
    </div>
</div>
