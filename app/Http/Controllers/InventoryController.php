<?php

namespace App\Http\Controllers;

use App\Models\FinancialEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index()
    {
        $low = Product::lowStock()->orderByRaw('stock / min_stock')->get();

        // Produtos baixos que ainda não estão em um pedido aberto
        $pendingIds = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->whereIn('purchase_orders.status', ['a_fazer', 'enviado'])->pluck('product_id')->filter()->all();

        return view('inventory.index', [
            'low' => $low,
            'pendingIds' => $pendingIds,
            'orders' => PurchaseOrder::with('supplier')->withCount('items')
                ->orderByRaw("CASE status WHEN 'a_fazer' THEN 0 WHEN 'enviado' THEN 1 WHEN 'recebido' THEN 2 ELSE 3 END")
                ->latest()->limit(40)->get(),
            'suppliers' => Supplier::withCount('purchaseOrders')->orderBy('name')->get(),
        ]);
    }

    /** Gera um pedido "a fazer" com os itens em estoque baixo selecionados (quantidade sugerida = 2× mínimo − atual). */
    public function generate(Request $request)
    {
        $ids = (array) $request->input('product_ids', []);
        $products = Product::whereIn('id', $ids)->get();
        if ($products->isEmpty()) {
            return back()->with('error', 'Selecione ao menos um produto.');
        }

        $po = DB::transaction(function () use ($products, $request) {
            $po = PurchaseOrder::create(['supplier_id' => $request->input('supplier_id') ?: null, 'status' => 'a_fazer', 'user_id' => $request->user()->id, 'notes' => 'Gerado automaticamente a partir do estoque baixo', 'expected_date' => now()->addDays(2)]);
            foreach ($products as $p) {
                $po->items()->create(['product_id' => $p->id, 'description' => $p->name, 'quantity' => max(1, $p->suggested_order), 'unit_cost' => $p->cost]);
            }
            $po->update(['total' => $po->items()->get()->sum(fn ($i) => $i->quantity * $i->unit_cost)]);

            return $po;
        });

        return redirect()->route('inventory.po.edit', $po)->with('ok', 'Pedido gerado. Revise quantidades e fornecedor.');
    }

    public function create()
    {
        return view('inventory.form', ['po' => new PurchaseOrder(['status' => 'a_fazer']), 'products' => Product::where('active', true)->orderBy('name')->get(), 'suppliers' => Supplier::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $po = DB::transaction(fn () => $this->persist(new PurchaseOrder(['user_id' => $request->user()->id]), $request));

        return redirect()->route('inventory.po.show', $po)->with('ok', 'Pedido de compra criado.');
    }

    public function show(PurchaseOrder $po)
    {
        return view('inventory.show', ['po' => $po->load('items.product', 'supplier')]);
    }

    public function edit(PurchaseOrder $po)
    {
        return view('inventory.form', ['po' => $po->load('items'), 'products' => Product::where('active', true)->orderBy('name')->get(), 'suppliers' => Supplier::orderBy('name')->get()]);
    }

    public function update(Request $request, PurchaseOrder $po)
    {
        abort_if($po->status === 'recebido', 422, 'Pedido já recebido.');
        DB::transaction(fn () => $this->persist($po, $request));

        return redirect()->route('inventory.po.show', $po)->with('ok', 'Pedido atualizado.');
    }

    /** Ao marcar como recebido: dá entrada no estoque e lança a despesa no caixa. */
    public function status(Request $request, PurchaseOrder $po)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(PurchaseOrder::STATUSES))],
            'payment_method' => ['nullable', Rule::in(array_keys(FinancialEntry::METHODS))],
        ]);

        DB::transaction(function () use ($po, $data, $request) {
            $wasReceived = $po->status === 'recebido';
            $po->update(['status' => $data['status']]);

            if ($data['status'] === 'recebido' && ! $wasReceived) {
                foreach ($po->items()->with('product')->get() as $it) {
                    $it->product?->adjustStock($it->quantity, "Pedido de compra #{$po->id}", $request->user()->id);
                    if ($it->product && $it->unit_cost > 0) {
                        $it->product->update(['cost' => $it->unit_cost]);
                    }
                }
                FinancialEntry::syncFrom($po, 'despesa', 'Fornecedores', $po->total, "Compra #{$po->id}".($po->supplier ? " - {$po->supplier->name}" : ''), $data['payment_method'] ?? 'boleto');
            }
        });

        return back()->with('ok', "Pedido #{$po->id}: ".PurchaseOrder::STATUSES[$data['status']].($data['status'] === 'recebido' ? ' · estoque e despesa atualizados' : ''));
    }

    public function destroy(PurchaseOrder $po)
    {
        abort_if($po->status === 'recebido', 422, 'Pedidos recebidos não podem ser excluídos.');
        $po->delete();

        return redirect()->route('inventory.index')->with('ok', 'Pedido excluído.');
    }

    public function storeSupplier(Request $request)
    {
        Supplier::create($request->validate(['name' => 'required|string|max:120', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email']));

        return back()->with('ok', 'Fornecedor cadastrado.');
    }

    public function destroySupplier(Supplier $supplier)
    {
        $supplier->delete();

        return back()->with('ok', 'Fornecedor removido.');
    }

    private function persist(PurchaseOrder $po, Request $request): PurchaseOrder
    {
        $data = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity' => 'nullable',
            'items.*.unit_cost' => 'nullable',
        ]);

        $items = collect($data['items'])->map(function ($it) {
            $p = ! empty($it['product_id']) ? Product::find($it['product_id']) : null;

            return [
                'product_id' => $p?->id,
                'description' => trim($it['description'] ?? '') ?: ($p?->name ?? ''),
                'quantity' => money_in($it['quantity'] ?? 0),
                'unit_cost' => filled($it['unit_cost'] ?? null) ? money_in($it['unit_cost']) : ($p?->cost ?? 0),
            ];
        })->filter(fn ($i) => $i['description'] !== '' && $i['quantity'] > 0)->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Adicione ao menos um item.']);
        }

        $po->fill([
            'supplier_id' => $data['supplier_id'] ?? null,
            'expected_date' => $data['expected_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'total' => round($items->sum(fn ($i) => $i['quantity'] * $i['unit_cost']), 2),
        ])->save();
        $po->items()->delete();
        $po->items()->createMany($items->all());

        return $po;
    }
}
