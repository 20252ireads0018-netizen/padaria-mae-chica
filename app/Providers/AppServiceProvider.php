<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SchoolOrder;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('pt_BR');
        Paginator::defaultView('partials.pager');

        // Nomes curtos para a origem dos lançamentos automáticos
        Relation::enforceMorphMap([
            'order' => Order::class,
            'school_order' => SchoolOrder::class,
            'purchase_order' => PurchaseOrder::class,
        ]);

        // Contadores de alerta no menu lateral
        View::composer('layouts.app', function ($view) {
            if (! auth()->check()) {
                return;
            }
            $view->with('badges', [
                'orders' => Order::open()->where('due_at', '<=', now()->addHours((int) Setting::get('order_alert_hours')))->count(),
                'late' => Order::open()->where('due_at', '<', now())->count(),
                'schools' => SchoolOrder::whereDate('delivery_date', now()->toDateString())->whereIn('status', ['pendente', 'producao'])->count(),
                'low' => Product::lowStock()->count(),
                'po' => PurchaseOrder::where('status', 'a_fazer')->count(),
                'docs' => Document::whereNotNull('due_date')->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])->count(),
            ]);
        });
    }
}
