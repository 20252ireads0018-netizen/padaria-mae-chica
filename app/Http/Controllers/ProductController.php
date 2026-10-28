<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = Product::query();

        if ($s = $request->input('busca')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%"));
        }
        if ($c = $request->input('categoria')) {
            $q->where('category', $c);
        }
        match ($request->input('tipo')) {
            'venda' => $q->where('is_supply', false),
            'insumo' => $q->where('is_supply', true),
            'baixo' => $q->lowStock(),
            'inativo' => $q->where('active', false),
            default => $q->where('active', true),
        };

        return view('products.index', [
            'products' => $q->orderBy('category')->orderBy('name')->paginate(40)->withQueryString(),
            'stockValue' => Product::where('active', true)->selectRaw('SUM(stock * cost) as v')->value('v'),
            'lowCount' => Product::lowStock()->count(),
        ]);
    }

    public function create()
    {
        return view('products.form', ['product' => new Product(['active' => true, 'unit' => 'un', 'category' => 'Pães'])]);
    }

    public function store(Request $request)
    {
        $product = Product::create($this->validated($request));

        return redirect()->route('products.show', $product)->with('ok', 'Produto cadastrado.');
    }

    public function show(Product $product)
    {
        return view('products.show', ['product' => $product, 'movements' => $product->movements()->with('user')->limit(30)->get()]);
    }

    public function edit(Product $product)
    {
        return view('products.form', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request);
        $diff = $data['stock'] - $product->stock;
        unset($data['stock']);
        $product->update($data);
        if (abs($diff) > 0.0001) {
            $product->adjustStock($diff, 'Ajuste na edição', $request->user()->id);
        }

        return redirect()->route('products.show', $product)->with('ok', 'Produto atualizado.');
    }

    public function destroy(Product $product)
    {
        $product->update(['active' => false]);

        return redirect()->route('products.index')->with('ok', 'Produto desativado (histórico preservado).');
    }

    /** Entrada/saída rápida de estoque (produção, perda, consumo, contagem). */
    public function adjust(Request $request, Product $product)
    {
        $data = $request->validate(['mode' => ['required', Rule::in(['entrada', 'saida', 'contagem'])], 'quantity' => 'required', 'reason' => 'nullable|string|max:60']);
        $qty = money_in($data['quantity']);

        $delta = match ($data['mode']) {
            'entrada' => $qty,
            'saida' => -$qty,
            'contagem' => $qty - $product->stock,
        };
        $product->adjustStock($delta, $data['reason'] ?: ucfirst($data['mode']), $request->user()->id);

        return back()->with('ok', "{$product->name}: estoque agora ".qty($product->fresh()->stock)." {$product->unit}.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'sku' => 'nullable|string|max:40',
            'category' => ['required', Rule::in(Product::CATEGORIES)],
            'unit' => ['required', Rule::in(Product::UNITS)],
            'price' => 'nullable', 'cost' => 'nullable', 'stock' => 'nullable', 'min_stock' => 'nullable',
        ]);

        foreach (['price', 'cost', 'stock', 'min_stock'] as $f) {
            $data[$f] = money_in($data[$f] ?? 0);
        }
        $data['is_supply'] = $request->boolean('is_supply');
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
