<?php

namespace Tests\Feature;

use App\Models\CashClosing;
use App\Models\FinancialEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PadariaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function user(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    public function test_every_page_renders_for_management(): void
    {
        $this->actingAs($this->user('gerencia'));
        $order = Order::first();
        $school = School::first();
        $po = PurchaseOrder::first();
        $product = Product::first();

        foreach ([
            '/', '/caixa', '/caixa/fechamento', '/encomendas', '/encomendas?ver=atrasadas', '/encomendas/create',
            "/encomendas/{$order->id}", "/encomendas/{$order->id}/edit", '/escolas', "/escolas/{$school->id}", '/escolas/pedidos/novo',
            "/escolas/pedidos/novo?escola={$school->id}", '/escala', '/escala?mes=2026-12&dia=2026-12-25', '/escala/equipe',
            '/produtos', '/produtos?tipo=baixo', '/produtos/create', "/produtos/{$product->id}", "/produtos/{$product->id}/edit",
            '/estoque', '/estoque/pedidos/novo', "/estoque/pedidos/{$po->id}", "/estoque/pedidos/{$po->id}/editar",
            '/documentos', '/contabilidade', '/contabilidade?periodo=12m', '/contabilidade?periodo=ano', '/contabilidade?periodo=personalizado&inicio=2026-01-01&fim=2026-01-31',
            '/usuarios',
        ] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/contabilidade/exportar?periodo=mes')->assertOk();
    }

    public function test_roles_are_enforced(): void
    {
        $this->actingAs($this->user('producao'));
        $this->get('/')->assertOk();
        $this->get('/estoque')->assertOk();
        $this->get('/encomendas')->assertOk();
        $this->get('/caixa')->assertForbidden();
        $this->get('/contabilidade')->assertForbidden();
        $this->get('/encomendas/create')->assertForbidden();

        $this->actingAs($this->user('caixa'));
        $this->get('/caixa')->assertOk();
        $this->get('/documentos')->assertOk();
        $this->get('/estoque')->assertForbidden();
        $this->get('/usuarios')->assertForbidden();
    }

    public function test_cash_closing_collects_entries_and_reserves_cash_flow(): void
    {
        $this->actingAs($this->user('caixa'));
        $date = '2030-01-10';

        $this->post('/caixa/lancamentos', ['date' => $date, 'type' => 'receita', 'category' => 'Vendas balcão', 'amount' => '1.000,00', 'payment_method' => 'dinheiro']);
        $this->post('/caixa/lancamentos', ['date' => $date, 'type' => 'receita', 'category' => 'Vendas balcão', 'amount' => '500', 'payment_method' => 'pix']);
        $this->post('/caixa/lancamentos', ['date' => $date, 'type' => 'despesa', 'category' => 'Insumos', 'amount' => '200,00', 'payment_method' => 'dinheiro']);

        $this->post('/caixa/fechamento', ['date' => $date, 'opening_balance' => '150,00', 'counted_cash' => '940,00'])->assertRedirect();

        $c = CashClosing::whereDate('date', $date)->firstOrFail();
        $this->assertEquals(1500, $c->total_income);
        $this->assertEquals(200, $c->total_expense);
        $this->assertEquals(950, $c->expected_balance);  // 150 + 1000 − 200
        $this->assertEquals(-10, $c->difference);        // contou 940
        $this->assertEquals(130, $c->reserve_amount);    // 10% de 1300
        $this->assertEquals(1170, $c->available_balance);
    }

    public function test_order_payment_flows_into_cash_on_payment_day(): void
    {
        $this->actingAs($this->user('caixa'));
        $p = Product::where('is_supply', false)->first();

        $this->post('/encomendas', [
            'customer_name' => 'Cliente Teste', 'due_date' => now()->addDay()->toDateString(), 'due_time' => '10:00', 'status' => 'pendente',
            'deposit' => '20,00', 'payment_method' => 'pix', 'items' => [['product_id' => $p->id, 'quantity' => '10', 'unit_price' => '5,00']],
        ])->assertRedirect();

        $o = Order::where('customer_name', 'Cliente Teste')->firstOrFail();
        $this->assertEquals(50, $o->total);
        $this->assertEquals(20, FinancialEntry::where('source_type', 'order')->where('source_id', $o->id)->sum('amount'));

        $this->travel(2)->days();
        $this->patch("/encomendas/{$o->id}/status", ['status' => 'entregue', 'paid' => 1])->assertRedirect();

        $rest = FinancialEntry::where('source_type', 'order')->where('source_id', $o->id)->where('source_slot', 'saldo')->firstOrFail();
        $this->assertEquals(30, $rest->amount);
        $this->assertEquals(now()->toDateString(), $rest->date->toDateString());
    }

    public function test_receiving_purchase_order_updates_stock_and_expense(): void
    {
        $this->actingAs($this->user('producao'));
        $po = PurchaseOrder::with('items.product')->first();
        $item = $po->items->first();
        $before = $item->product->stock;

        $this->patch("/estoque/pedidos/{$po->id}/status", ['status' => 'recebido', 'payment_method' => 'boleto'])->assertRedirect();

        $this->assertEquals($before + $item->quantity, $item->product->fresh()->stock);
        $this->assertDatabaseHas('financial_entries', ['source_type' => 'purchase_order', 'source_id' => $po->id, 'type' => 'despesa']);
    }

    public function test_weekend_rotation_spreads_shifts(): void
    {
        $this->actingAs($this->user('gerencia'));
        $ids = \App\Models\Employee::pluck('id')->take(3)->all();

        $this->post('/escala/rodizio', ['mes' => '2030-03', 'employee_ids' => $ids, 'per_day' => 1, 'start_time' => '06:00', 'end_time' => '12:00', 'days' => ['sabado', 'domingo']])->assertRedirect();

        $counts = \App\Models\Shift::whereBetween('date', ['2030-03-01', '2030-03-31'])->get()->countBy('employee_id');
        $this->assertCount(3, $counts);
        $this->assertLessThanOrEqual(1, $counts->max() - $counts->min());
    }
}
