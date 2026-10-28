<?php

namespace App\Http\Controllers;

use App\Models\FinancialEntry;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->input('ver', 'abertas');
        $q = Order::with('items')->withCount('items');

        match ($view) {
            'hoje' => $q->open()->whereDate('due_at', now()->toDateString()),
            'semana' => $q->open()->whereBetween('due_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()]),
            'atrasadas' => $q->open()->where('due_at', '<', now()),
            'concluidas' => $q->whereIn('status', ['entregue', 'cancelada']),
            default => $q->open(),
        };

        if ($s = $request->input('busca')) {
            $q->where(fn ($w) => $w->where('customer_name', 'like', "%$s%")->orWhere('customer_phone', 'like', "%$s%"));
        }

        $orders = $q->orderBy('due_at', $view === 'concluidas' ? 'desc' : 'asc')->paginate(25)->withQueryString();

        $open = Order::open();

        return view('orders.index', [
            'orders' => $orders,
            'view' => $view,
            'stats' => [
                'abertas' => (clone $open)->count(),
                'atrasadas' => (clone $open)->where('due_at', '<', now())->count(),
                'hoje' => (clone $open)->whereDate('due_at', now()->toDateString())->count(),
                'a_receber' => (clone $open)->where('paid', false)->get()->sum('balance'),
            ],
        ]);
    }

    public function create()
    {
        return view('orders.form', ['order' => new Order(['due_at' => now()->addDay()->setTime(9, 0)]), 'products' => $this->products()]);
    }

    public function store(Request $request)
    {
        $order = DB::transaction(function () use ($request) {
            $order = new Order(['user_id' => $request->user()->id]);

            return $this->save($order, $request);
        });

        return redirect()->route('orders.show', $order)->with('ok', 'Encomenda registrada.');
    }

    public function show(Order $order)
    {
        return view('orders.show', ['order' => $order->load('items.product', 'user')]);
    }

    public function edit(Order $order)
    {
        return view('orders.form', ['order' => $order->load('items'), 'products' => $this->products()]);
    }

    public function update(Request $request, Order $order)
    {
        DB::transaction(fn () => $this->save($order, $request));

        return redirect()->route('orders.show', $order)->with('ok', 'Encomenda atualizada.');
    }

    public function destroy(Order $order)
    {
        FinancialEntry::where('source_type', $order->getMorphClass())->where('source_id', $order->id)->delete();
        $order->delete();

        return redirect()->route('orders.index')->with('ok', 'Encomenda excluída.');
    }

    /** Produção e caixa mudam o andamento; ao entregar, pode-se marcar como pago. */
    public function status(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'paid' => 'nullable|boolean',
            'payment_method' => ['nullable', Rule::in(array_keys(FinancialEntry::METHODS))],
        ]);

        $order->status = $data['status'];
        if ($request->has('paid') && $request->user()->hasRole('caixa')) {
            $order->paid = (bool) $data['paid'];
            $order->payment_method = $data['payment_method'] ?? $order->payment_method ?? 'dinheiro';
        }
        $order->save();
        $order->syncFinance();

        return back()->with('ok', "Encomenda #{$order->id}: ".Order::STATUSES[$order->status].($order->paid ? ' · paga' : ''));
    }

    private function save(Order $order, Request $request): Order
    {
        $data = $request->validate([
            'customer_name' => 'required|string|max:120',
            'customer_phone' => 'nullable|string|max:30',
            'due_date' => 'required|date',
            'due_time' => 'required',
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'deposit' => 'nullable',
            'paid' => 'nullable|boolean',
            'payment_method' => ['nullable', Rule::in(array_keys(FinancialEntry::METHODS))],
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity' => 'nullable',
            'items.*.unit_price' => 'nullable',
        ]);

        $items = collect($data['items'])->map(function ($it) {
            $product = ! empty($it['product_id']) ? Product::find($it['product_id']) : null;

            return [
                'product_id' => $product?->id,
                'description' => trim($it['description'] ?? '') ?: ($product?->name ?? ''),
                'quantity' => money_in($it['quantity'] ?? 0),
                'unit_price' => filled($it['unit_price'] ?? null) ? money_in($it['unit_price']) : ($product?->price ?? 0),
            ];
        })->filter(fn ($it) => $it['description'] !== '' && $it['quantity'] > 0)->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Adicione ao menos um item com quantidade.']);
        }

        $order->fill([
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'due_at' => $data['due_date'].' '.$data['due_time'],
            'status' => $data['status'],
            'deposit' => money_in($data['deposit'] ?? 0),
            'paid' => (bool) ($data['paid'] ?? false),
            'payment_method' => $data['payment_method'] ?? 'dinheiro',
            'notes' => $data['notes'] ?? null,
            'total' => round($items->sum(fn ($i) => $i['quantity'] * $i['unit_price']), 2),
        ])->save();

        $order->items()->delete();
        $order->items()->createMany($items->all());
        $order->syncFinance();

        return $order;
    }

    private function products()
    {
        return Product::where('active', true)->where('is_supply', false)->orderBy('category')->orderBy('name')->get(['id', 'name', 'price', 'unit', 'category']);
    }
}
