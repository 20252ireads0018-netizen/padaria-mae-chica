<?php

namespace App\Http\Controllers;

use App\Models\CashClosing;
use App\Models\Document;
use App\Models\FinancialEntry;
use App\Models\Holiday;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SchoolOrder;
use App\Models\Setting;
use App\Models\Shift;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = now()->toDateString();
        $alertUntil = now()->addHours((int) Setting::get('order_alert_hours'));

        $todayTotals = FinancialEntry::whereDate('date', $today)
            ->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type');

        $orders = Order::open()->with('items')->where('due_at', '<=', $alertUntil)->orderBy('due_at')->get();

        $schoolOrders = SchoolOrder::with(['school', 'items'])
            ->whereBetween('delivery_date', [$today, now()->addDay()->toDateString()])
            ->where('status', '!=', 'cancelada')->orderBy('delivery_date')->orderBy('shift')->get();

        // Produção consolidada (encomendas + escolas) para hoje e amanhã
        $production = [];
        foreach ($orders->where('due_at', '<=', now()->addDay()->endOfDay()) as $o) {
            foreach ($o->items as $it) {
                $production[$it->description] = ($production[$it->description] ?? 0) + $it->quantity;
            }
        }
        foreach ($schoolOrders->where('status', '!=', 'entregue') as $so) {
            foreach ($so->items as $it) {
                $production[$it->description] = ($production[$it->description] ?? 0) + $it->quantity;
            }
        }
        arsort($production);

        $last14 = FinancialEntry::where('date', '>=', now()->subDays(13)->toDateString())
            ->selectRaw('date, type, SUM(amount) as total')->groupBy('date', 'type')->get()
            ->groupBy(fn ($r) => $r->date->toDateString());

        $chart = collect(range(13, 0))->map(function ($i) use ($last14) {
            $d = now()->subDays($i);
            $rows = $last14->get($d->toDateString(), collect());

            return [
                'label' => $d->format('d/m'),
                'in' => (float) $rows->firstWhere('type', 'receita')?->total,
                'out' => (float) $rows->firstWhere('type', 'despesa')?->total,
            ];
        });

        return view('dashboard', [
            'income' => (float) ($todayTotals['receita'] ?? 0),
            'expense' => (float) ($todayTotals['despesa'] ?? 0),
            'closedToday' => CashClosing::whereDate('date', $today)->exists(),
            'orders' => $orders,
            'schoolOrders' => $schoolOrders,
            'production' => array_slice($production, 0, 10, true),
            'lowStock' => Product::lowStock()->orderByRaw('stock / min_stock')->limit(8)->get(),
            'shiftsToday' => Shift::with('employee')->whereDate('date', $today)->orderBy('start_time')->get(),
            'holiday' => Holiday::whereDate('date', $today)->first(),
            'nextHoliday' => Holiday::whereDate('date', '>', $today)->orderBy('date')->first(),
            'docsDue' => Document::whereNotNull('due_date')->whereBetween('due_date', [now()->subDays(30)->toDateString(), now()->addDays(15)->toDateString()])->orderBy('due_date')->limit(6)->get(),
            'poTodo' => PurchaseOrder::where('status', 'a_fazer')->count(),
            'chart' => $chart,
        ]);
    }
}
