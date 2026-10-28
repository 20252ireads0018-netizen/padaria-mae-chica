<?php

namespace App\Http\Controllers;

use App\Models\FinancialEntry;
use App\Models\Product;
use App\Models\School;
use App\Models\SchoolOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolController extends Controller
{
    public function index(Request $request)
    {
        $start = Carbon::parse($request->input('semana', now()->toDateString()))->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $orders = SchoolOrder::with(['school', 'items'])
            ->whereBetween('delivery_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('delivery_date')->orderBy('shift')->get();

        // Quantidade total por produto em cada dia (lista de produção)
        $byDay = $orders->where('status', '!=', 'cancelada')->groupBy(fn ($o) => $o->delivery_date->toDateString())
            ->map(fn ($day) => $day->flatMap->items->groupBy('description')->map->sum('quantity'));

        $schools = School::withCount('orders')
            ->withSum(['orders as open_amount' => fn ($q) => $q->where('paid', false)->where('status', 'entregue')], 'total')
            ->orderBy('name')->get();

        return view('schools.index', compact('start', 'end', 'orders', 'byDay', 'schools'));
    }

    public function show(Request $request, School $school)
    {
        $month = Carbon::parse($request->input('mes', now()->format('Y-m')).'-01');
        $orders = $school->orders()->with('items')
            ->whereBetween('delivery_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('delivery_date')->get();

        return view('schools.show', compact('school', 'orders', 'month'));
    }

    public function storeSchool(Request $request)
    {
        School::create($this->validateSchool($request));

        return back()->with('ok', 'Escola cadastrada.');
    }

    public function updateSchool(Request $request, School $school)
    {
        $school->update($this->validateSchool($request) + ['active' => $request->boolean('active')]);

        return back()->with('ok', 'Escola atualizada.');
    }

    public function destroySchool(School $school)
    {
        if ($school->orders()->exists()) {
            $school->update(['active' => false]);

            return back()->with('ok', 'A escola possui histórico e foi apenas desativada.');
        }
        $school->delete();

        return redirect()->route('schools.index')->with('ok', 'Escola removida.');
    }

    public function create(Request $request)
    {
        $order = new SchoolOrder(['school_id' => $request->input('escola'), 'delivery_date' => $request->input('data', now()->addDay()->toDateString()), 'shift' => 'manha', 'status' => 'pendente']);

        // Sugere os itens do último pedido da escola
        if ($order->school_id && $last = SchoolOrder::with('items')->where('school_id', $order->school_id)->latest('delivery_date')->first()) {
            $order->setRelation('items', $last->items);
        }

        return view('schools.form', ['order' => $order, 'schools' => School::where('active', true)->orderBy('name')->get(), 'products' => $this->products()]);
    }

    public function store(Request $request)
    {
        $data = $this->validateOrder($request);
        $dates = collect([$data['delivery_date']]);

        // Repetir nos dias úteis seguintes, se pedido
        if ($until = $request->input('repeat_until')) {
            $dates = collect();
            for ($d = Carbon::parse($data['delivery_date']); $d->lte(Carbon::parse($until)); $d->addDay()) {
                if (! $d->isWeekend()) {
                    $dates->push($d->toDateString());
                }
            }
        }

        DB::transaction(function () use ($data, $dates) {
            foreach ($dates as $date) {
                $this->persist(new SchoolOrder, [...$data, 'delivery_date' => $date]);
            }
        });

        return redirect()->route('schools.index', ['semana' => $data['delivery_date']])->with('ok', $dates->count() > 1 ? "{$dates->count()} pedidos criados." : 'Pedido da escola registrado.');
    }

    public function edit(SchoolOrder $schoolOrder)
    {
        return view('schools.form', ['order' => $schoolOrder->load('items'), 'schools' => School::orderBy('name')->get(), 'products' => $this->products()]);
    }

    public function update(Request $request, SchoolOrder $schoolOrder)
    {
        DB::transaction(fn () => $this->persist($schoolOrder, $this->validateOrder($request)));

        return redirect()->route('schools.index', ['semana' => $schoolOrder->delivery_date->toDateString()])->with('ok', 'Pedido atualizado.');
    }

    public function destroy(SchoolOrder $schoolOrder)
    {
        FinancialEntry::where('source_type', $schoolOrder->getMorphClass())->where('source_id', $schoolOrder->id)->delete();
        $schoolOrder->delete();

        return back()->with('ok', 'Pedido excluído.');
    }

    public function status(Request $request, SchoolOrder $schoolOrder)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(SchoolOrder::STATUSES))]]);
        $schoolOrder->update($data);
        $schoolOrder->syncFinance();

        return back()->with('ok', $schoolOrder->school->name.': '.SchoolOrder::STATUSES[$data['status']]);
    }

    /** Copia os pedidos de uma semana para a semana seguinte. */
    public function repeatWeek(Request $request)
    {
        $start = Carbon::parse($request->input('semana'))->startOfWeek();
        $source = SchoolOrder::with('items')->whereBetween('delivery_date', [$start->toDateString(), $start->copy()->endOfWeek()->toDateString()])
            ->where('status', '!=', 'cancelada')->get();
        $created = 0;

        DB::transaction(function () use ($source, &$created) {
            foreach ($source as $o) {
                $date = $o->delivery_date->copy()->addWeek()->toDateString();
                if (SchoolOrder::where('school_id', $o->school_id)->whereDate('delivery_date', $date)->where('shift', $o->shift)->exists()) {
                    continue;
                }
                $copy = SchoolOrder::create(['school_id' => $o->school_id, 'delivery_date' => $date, 'shift' => $o->shift, 'status' => 'pendente', 'total' => $o->total, 'notes' => $o->notes]);
                $copy->items()->createMany($o->items->map->only(['product_id', 'description', 'quantity', 'unit_price'])->all());
                $created++;
            }
        });

        return redirect()->route('schools.index', ['semana' => $start->copy()->addWeek()->toDateString()])->with('ok', "$created pedidos copiados para a próxima semana.");
    }

    /** Marca como pagos os pedidos entregues do mês (faturamento). */
    public function markPaid(Request $request, School $school)
    {
        $month = Carbon::parse($request->input('mes').'-01');
        $orders = $school->orders()->where('status', 'entregue')->where('paid', false)
            ->whereBetween('delivery_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get();

        foreach ($orders as $o) {
            $o->update(['paid' => true]);
            $o->syncFinance();
        }

        return back()->with('ok', $orders->count().' entregas faturadas ('.brl($orders->sum('total')).').');
    }

    private function persist(SchoolOrder $order, array $data): void
    {
        $items = collect($data['items'])->map(function ($it) {
            $p = ! empty($it['product_id']) ? Product::find($it['product_id']) : null;

            return [
                'product_id' => $p?->id,
                'description' => trim($it['description'] ?? '') ?: ($p?->name ?? ''),
                'quantity' => money_in($it['quantity'] ?? 0),
                'unit_price' => filled($it['unit_price'] ?? null) ? money_in($it['unit_price']) : ($p?->price ?? 0),
            ];
        })->filter(fn ($i) => $i['description'] !== '' && $i['quantity'] > 0)->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Adicione ao menos um item com quantidade.']);
        }

        $order->fill([
            'school_id' => $data['school_id'], 'delivery_date' => $data['delivery_date'], 'shift' => $data['shift'],
            'status' => $data['status'], 'paid' => (bool) ($data['paid'] ?? false), 'notes' => $data['notes'] ?? null,
            'total' => round($items->sum(fn ($i) => $i['quantity'] * $i['unit_price']), 2),
        ])->save();
        $order->items()->delete();
        $order->items()->createMany($items->all());
        $order->load('school')->syncFinance();
    }

    private function validateOrder(Request $request): array
    {
        return $request->validate([
            'school_id' => 'required|exists:schools,id',
            'delivery_date' => 'required|date',
            'shift' => ['required', Rule::in(array_keys(SchoolOrder::SHIFTS))],
            'status' => ['required', Rule::in(array_keys(SchoolOrder::STATUSES))],
            'paid' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity' => 'nullable',
            'items.*.unit_price' => 'nullable',
        ]);
    }

    private function validateSchool(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'contact_name' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'billing' => ['required', Rule::in(array_keys(School::BILLING))],
        ]);
    }

    private function products()
    {
        return Product::where('active', true)->where('is_supply', false)->orderBy('name')->get(['id', 'name', 'price', 'unit']);
    }
}
