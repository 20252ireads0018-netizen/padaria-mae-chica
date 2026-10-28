<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'role:caixa,producao'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    // 1. Caixa: lançamentos e fechamento
    Route::middleware('role:caixa')->prefix('caixa')->name('cash.')->group(function () {
        Route::get('/', [CashController::class, 'index'])->name('index');
        Route::post('/lancamentos', [CashController::class, 'storeEntry'])->name('entries.store');
        Route::delete('/lancamentos/{entry}', [CashController::class, 'destroyEntry'])->name('entries.destroy');
        Route::get('/fechamento', [CashController::class, 'create'])->name('closing.create');
        Route::post('/fechamento', [CashController::class, 'store'])->name('closing.store');
        Route::get('/fechamento/{closing}', [CashController::class, 'show'])->name('closing.show');
        Route::delete('/fechamento/{closing}', [CashController::class, 'destroy'])->name('closing.destroy')->middleware('role:gerencia');
    });

    // 2. Encomendas
    Route::patch('/encomendas/{order}/status', [OrderController::class, 'status'])->name('orders.status');
    Route::resource('encomendas', OrderController::class)->parameters(['encomendas' => 'order'])->names('orders')
        ->except(['index', 'show'])->middleware('role:caixa');
    Route::get('/encomendas', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/encomendas/{order}', [OrderController::class, 'show'])->name('orders.show');

    // 4. Escalas
    Route::prefix('escala')->name('schedule.')->group(function () {
        Route::get('/', [ScheduleController::class, 'index'])->name('index');
        Route::middleware('role:gerencia')->group(function () {
            Route::post('/turnos', [ScheduleController::class, 'storeShift'])->name('shifts.store');
            Route::delete('/turnos/{shift}', [ScheduleController::class, 'destroyShift'])->name('shifts.destroy');
            Route::post('/gerar', [ScheduleController::class, 'generate'])->name('generate');
            Route::post('/rodizio', [ScheduleController::class, 'rotation'])->name('rotation');
            Route::post('/limpar', [ScheduleController::class, 'clear'])->name('clear');
            Route::get('/equipe', [ScheduleController::class, 'team'])->name('team');
            Route::post('/equipe', [ScheduleController::class, 'storeEmployee'])->name('employees.store');
            Route::put('/equipe/{employee}', [ScheduleController::class, 'updateEmployee'])->name('employees.update');
            Route::delete('/equipe/{employee}', [ScheduleController::class, 'destroyEmployee'])->name('employees.destroy');
            Route::post('/feriados', [ScheduleController::class, 'storeHoliday'])->name('holidays.store');
            Route::delete('/feriados/{holiday}', [ScheduleController::class, 'destroyHoliday'])->name('holidays.destroy');
        });
    });

    // 5. Produtos
    Route::get('/produtos', [ProductController::class, 'index'])->name('products.index');
    Route::post('/produtos/{product}/estoque', [ProductController::class, 'adjust'])->name('products.adjust');
    Route::resource('produtos', ProductController::class)->parameters(['produtos' => 'product'])->names('products')
        ->except(['index', 'show'])->middleware('role:gerencia');
    Route::get('/produtos/{product}', [ProductController::class, 'show'])->name('products.show');

    // 6. Estoque, pedidos de compra e documentos
    Route::middleware('role:producao')->prefix('estoque')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::post('/pedidos/gerar', [InventoryController::class, 'generate'])->name('po.generate');
        Route::get('/pedidos/novo', [InventoryController::class, 'create'])->name('po.create');
        Route::post('/pedidos', [InventoryController::class, 'store'])->name('po.store');
        Route::get('/pedidos/{po}', [InventoryController::class, 'show'])->name('po.show');
        Route::get('/pedidos/{po}/editar', [InventoryController::class, 'edit'])->name('po.edit');
        Route::put('/pedidos/{po}', [InventoryController::class, 'update'])->name('po.update');
        Route::patch('/pedidos/{po}/status', [InventoryController::class, 'status'])->name('po.status');
        Route::delete('/pedidos/{po}', [InventoryController::class, 'destroy'])->name('po.destroy');
        Route::post('/fornecedores', [InventoryController::class, 'storeSupplier'])->name('suppliers.store');
        Route::delete('/fornecedores/{supplier}', [InventoryController::class, 'destroySupplier'])->name('suppliers.destroy');
    });
    Route::middleware('role:caixa')->prefix('documentos')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::post('/', [DocumentController::class, 'store'])->name('store');
        Route::get('/{document}/arquivo', [DocumentController::class, 'download'])->name('download');
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
    });

    // 7. Contabilidade
    Route::middleware('role:gerencia')->group(function () {
        Route::get('/contabilidade', [AccountingController::class, 'index'])->name('accounting.index');
        Route::get('/contabilidade/exportar', [AccountingController::class, 'export'])->name('accounting.export');
    });

    // 8. Escolas
    Route::prefix('escolas')->name('schools.')->group(function () {
        Route::get('/', [SchoolController::class, 'index'])->name('index');
        Route::patch('/pedidos/{schoolOrder}/status', [SchoolController::class, 'status'])->name('orders.status');
        Route::middleware('role:caixa')->group(function () {
            Route::post('/', [SchoolController::class, 'storeSchool'])->name('store');
            Route::put('/{school}', [SchoolController::class, 'updateSchool'])->name('update');
            Route::delete('/{school}', [SchoolController::class, 'destroySchool'])->name('destroy');
            Route::get('/pedidos/novo', [SchoolController::class, 'create'])->name('orders.create');
            Route::post('/pedidos', [SchoolController::class, 'store'])->name('orders.store');
            Route::get('/pedidos/{schoolOrder}/editar', [SchoolController::class, 'edit'])->name('orders.edit');
            Route::put('/pedidos/{schoolOrder}', [SchoolController::class, 'update'])->name('orders.update');
            Route::delete('/pedidos/{schoolOrder}', [SchoolController::class, 'destroy'])->name('orders.destroy');
            Route::post('/repetir-semana', [SchoolController::class, 'repeatWeek'])->name('repeat');
            Route::post('/{school}/faturar', [SchoolController::class, 'markPaid'])->name('pay');
        });
        Route::get('/{school}', [SchoolController::class, 'show'])->name('show');
    });

    // 3. Usuários e configurações
    Route::middleware('role:gerencia')->group(function () {
        Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->names('users')->except(['show', 'create', 'edit']);
        Route::post('/configuracoes', [UserController::class, 'settings'])->name('settings.update');
    });
});
