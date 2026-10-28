<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Employee;
use App\Models\FinancialEntry;
use App\Models\Holiday;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\School;
use App\Models\SchoolOrder;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuários de demonstração (senha padrão: maechica123 — troque após o primeiro acesso)
        $gerente = User::create(['name' => 'Gerência Mãe Chica', 'email' => 'gerencia@maechica.com', 'password' => 'maechica123', 'role' => 'gerencia']);
        User::create(['name' => 'Ana (Caixa)', 'email' => 'caixa@maechica.com', 'password' => 'maechica123', 'role' => 'caixa']);
        User::create(['name' => 'José (Produção)', 'email' => 'producao@maechica.com', 'password' => 'maechica123', 'role' => 'producao']);

        foreach (Setting::DEFAULTS as $k => $v) {
            Setting::put($k, $v);
        }

        // Produtos de venda e insumos
        $items = [
            ['Pão francês', 'Pães', 'kg', 16.90, 7.20, 25, 10, false],
            ['Pão de forma caseiro', 'Pães', 'un', 12.00, 4.80, 14, 6, false],
            ['Pão de queijo', 'Salgados', 'kg', 54.90, 22.00, 6, 3, false],
            ['Pão doce', 'Pães', 'un', 3.50, 1.20, 40, 15, false],
            ['Coxinha', 'Salgados', 'un', 7.00, 2.40, 30, 20, false],
            ['Esfiha de carne', 'Salgados', 'un', 6.50, 2.20, 12, 15, false],
            ['Enroladinho de salsicha', 'Salgados', 'un', 6.00, 1.90, 25, 15, false],
            ['Torta de frango (fatia)', 'Tortas', 'un', 12.90, 4.50, 10, 6, false],
            ['Torta de frango inteira', 'Tortas', 'un', 89.90, 32.00, 2, 1, false],
            ['Bolo de cenoura', 'Bolos', 'un', 35.00, 12.00, 3, 2, false],
            ['Sonho', 'Doces', 'un', 6.00, 2.00, 20, 10, false],
            ['Café coado 200ml', 'Bebidas', 'un', 4.50, 0.90, 100, 0, false],
            ['Farinha de trigo 25kg', 'Insumos', 'saco', 0, 98.00, 3, 5, true],
            ['Fermento biológico 500g', 'Insumos', 'pct', 0, 14.50, 8, 6, true],
            ['Açúcar refinado 5kg', 'Insumos', 'pct', 0, 22.00, 2, 4, true],
            ['Margarina 15kg', 'Insumos', 'cx', 0, 165.00, 1, 2, true],
            ['Ovos (bandeja 30)', 'Insumos', 'un', 0, 21.00, 9, 6, true],
            ['Saco de papel kraft (100)', 'Embalagens', 'pct', 0, 18.00, 4, 5, true],
        ];
        $products = [];
        foreach ($items as $i => [$name, $cat, $unit, $price, $cost, $stock, $min, $supply]) {
            $products[$name] = Product::create([
                'name' => $name, 'sku' => 'MC'.str_pad($i + 1, 3, '0', STR_PAD_LEFT), 'category' => $cat, 'unit' => $unit,
                'price' => $price, 'cost' => $cost, 'stock' => $stock, 'min_stock' => $min, 'is_supply' => $supply,
            ]);
        }

        // Funcionários e escala padrão
        $staff = [
            ['José Ribeiro', 'Padeiro', '#C8102E', [[1, '04:00', '12:00'], [2, '04:00', '12:00'], [3, '04:00', '12:00'], [4, '04:00', '12:00'], [5, '04:00', '12:00']]],
            ['Carlos Mendes', 'Padeiro', '#8B1E1E', [[2, '12:00', '20:00'], [3, '12:00', '20:00'], [4, '12:00', '20:00'], [5, '12:00', '20:00'], [6, '04:00', '12:00']]],
            ['Ana Paula Souza', 'Caixa', '#E39B00', [[1, '06:00', '14:00'], [2, '06:00', '14:00'], [3, '06:00', '14:00'], [4, '06:00', '14:00'], [5, '06:00', '14:00']]],
            ['Mariana Lima', 'Atendente', '#2F7D32', [[1, '13:00', '21:00'], [2, '13:00', '21:00'], [3, '13:00', '21:00'], [5, '13:00', '21:00'], [6, '13:00', '21:00']]],
            ['Rafael Costa', 'Confeiteiro', '#1565C0', [[1, '07:00', '15:00'], [3, '07:00', '15:00'], [4, '07:00', '15:00'], [5, '07:00', '15:00'], [6, '07:00', '15:00']]],
        ];
        $employees = [];
        foreach ($staff as [$name, $pos, $color, $tpls]) {
            $e = Employee::create(['name' => $name, 'position' => $pos, 'color' => $color, 'phone' => '(31) 9'.rand(8000, 9999).'-'.rand(1000, 9999)]);
            foreach ($tpls as [$wd, $s, $end]) {
                ShiftTemplate::create(['employee_id' => $e->id, 'weekday' => $wd, 'start_time' => $s, 'end_time' => $end]);
            }
            $employees[] = $e;
        }

        // Feriados nacionais 2026
        foreach ([
            '2026-01-01' => 'Confraternização Universal', '2026-02-16' => 'Carnaval', '2026-02-17' => 'Carnaval',
            '2026-04-03' => 'Sexta-feira Santa', '2026-04-21' => 'Tiradentes', '2026-05-01' => 'Dia do Trabalho',
            '2026-06-04' => 'Corpus Christi', '2026-09-07' => 'Independência do Brasil', '2026-10-12' => 'Nossa Senhora Aparecida',
            '2026-11-02' => 'Finados', '2026-11-15' => 'Proclamação da República', '2026-11-20' => 'Consciência Negra',
            '2026-12-25' => 'Natal', '2026-12-31' => 'Véspera de Ano Novo',
        ] as $d => $n) {
            Holiday::create(['date' => $d, 'name' => $n]);
        }

        // Gera a escala do mês atual e do próximo a partir dos modelos
        foreach ([now()->startOfMonth(), now()->addMonthNoOverflow()->startOfMonth()] as $month) {
            for ($d = $month->copy(); $d->month === $month->month; $d->addDay()) {
                foreach (ShiftTemplate::where('weekday', $d->dayOfWeek)->get() as $t) {
                    Shift::create(['employee_id' => $t->employee_id, 'date' => $d->toDateString(), 'start_time' => $t->start_time, 'end_time' => $t->end_time]);
                }
                if ($d->isSunday()) { // plantão de domingo em rodízio
                    $e = $employees[$d->weekOfYear % count($employees)];
                    Shift::create(['employee_id' => $e->id, 'date' => $d->toDateString(), 'start_time' => '06:00', 'end_time' => '12:00', 'notes' => 'Plantão domingo']);
                }
            }
        }

        // Escolas e pedidos
        $schools = collect([
            ['E.E. Professora Helena Antipoff', 'Dona Cida', 'mensal'],
            ['Escola Municipal Monteiro Lobato', 'Sr. Paulo', 'semanal'],
            ['Colégio Sagrado Coração', 'Irmã Teresa', 'mensal'],
        ])->map(fn ($s) => School::create(['name' => $s[0], 'contact_name' => $s[1], 'billing' => $s[2], 'phone' => '(31) 3'.rand(100, 999).'-'.rand(1000, 9999), 'address' => 'Rua das Flores, '.rand(10, 999)]));

        $pf = $products['Pão francês'];
        $pd = $products['Pão doce'];
        for ($i = -12; $i <= 10; $i++) {
            $day = now()->addDays($i);
            if ($day->isWeekend()) {
                continue;
            }
            foreach ($schools as $k => $school) {
                if ($k === 2 && $day->dayOfWeek % 2) {
                    continue;
                }
                $qty = [300, 180, 120][$k];
                $so = SchoolOrder::create([
                    'school_id' => $school->id, 'delivery_date' => $day->toDateString(), 'shift' => $k === 1 ? 'tarde' : 'manha',
                    'status' => $i < 0 ? 'entregue' : 'pendente', 'paid' => $i < -5,
                ]);
                $so->items()->create(['product_id' => $pd->id, 'description' => 'Pão doce (unidade)', 'quantity' => $qty, 'unit_price' => 1.10]);
                $so->items()->create(['product_id' => $pf->id, 'description' => 'Pão francês (kg)', 'quantity' => round($qty / 20), 'unit_price' => 14.50]);
                $so->update(['total' => $so->items->sum(fn ($it) => $it->quantity * $it->unit_price)]);
                if ($so->paid) {
                    $so->syncFinance();
                    FinancialEntry::where('source_type', $so->getMorphClass())->where('source_id', $so->id)->update(['date' => $day->toDateString()]);
                }
            }
        }

        // Encomendas
        $enc = [
            ['Dona Lúcia', 1, 'producao', [['Torta de frango inteira', 2], ['Coxinha', 100]], 80],
            ['Buffet Alegria', 2, 'pendente', [['Enroladinho de salsicha', 200], ['Coxinha', 200], ['Esfiha de carne', 100]], 300],
            ['Igreja São José', 4, 'pendente', [['Pão de forma caseiro', 30], ['Bolo de cenoura', 4]], 0],
            ['Marcos Vieira', 0, 'pronta', [['Bolo de cenoura', 1], ['Sonho', 20]], 155],
            ['Festa da Júlia', 6, 'pendente', [['Coxinha', 150], ['Pão de queijo', 3]], 100],
            ['Dona Rosa', -3, 'entregue', [['Torta de frango inteira', 1]], 89.90],
        ];
        foreach ($enc as [$cust, $days, $status, $its, $dep]) {
            $o = Order::create([
                'customer_name' => $cust, 'customer_phone' => '(31) 9'.rand(8000, 9999).'-'.rand(1000, 9999),
                'due_at' => now()->addDays($days)->setTime([9, 10, 14, 16][rand(0, 3)], 0), 'status' => $status,
                'deposit' => $dep, 'payment_method' => 'pix', 'user_id' => $gerente->id,
            ]);
            foreach ($its as [$pn, $q]) {
                $o->items()->create(['product_id' => $products[$pn]->id, 'description' => $pn, 'quantity' => $q, 'unit_price' => $products[$pn]->price]);
            }
            $o->total = $o->items()->get()->sum(fn ($it) => $it->subtotal);
            $o->paid = $status === 'entregue';
            $o->save();
            $o->syncFinance();
        }

        // Histórico financeiro dos últimos 14 meses (vendas balcão e despesas)
        $start = now()->subMonths(14)->startOfMonth();
        for ($d = $start->copy(); $d->lt(now()->startOfDay()); $d->addDay()) {
            $season = 1 + 0.15 * sin($d->month / 12 * 2 * M_PI);
            $base = ($d->isWeekend() ? 2600 : 1900) * $season;
            foreach (['dinheiro' => .35, 'pix' => .40, 'cartao' => .25] as $m => $share) {
                FinancialEntry::create(['date' => $d->toDateString(), 'type' => 'receita', 'category' => 'Vendas balcão', 'description' => 'Vendas do dia', 'payment_method' => $m, 'amount' => round($base * $share * (0.85 + mt_rand() / mt_getrandmax() * 0.3), 2)]);
            }
            FinancialEntry::create(['date' => $d->toDateString(), 'type' => 'despesa', 'category' => 'Insumos', 'description' => 'Compras do dia', 'payment_method' => 'dinheiro', 'amount' => round($base * 0.28, 2)]);
            if ($d->day === 5) {
                FinancialEntry::create(['date' => $d->toDateString(), 'type' => 'despesa', 'category' => 'Salários', 'description' => 'Folha de pagamento', 'payment_method' => 'pix', 'amount' => 14800]);
            }
            if ($d->day === 10) {
                FinancialEntry::create(['date' => $d->toDateString(), 'type' => 'despesa', 'category' => 'Aluguel', 'description' => 'Aluguel do ponto', 'payment_method' => 'boleto', 'amount' => 4500]);
                FinancialEntry::create(['date' => $d->toDateString(), 'type' => 'despesa', 'category' => 'Energia', 'description' => 'Cemig', 'payment_method' => 'boleto', 'amount' => rand(1800, 2600)]);
            }
            if ($d->day === 15) {
                FinancialEntry::create(['date' => $d->toDateString(), 'type' => 'despesa', 'category' => 'Gás', 'description' => 'Gás do forno', 'payment_method' => 'pix', 'amount' => rand(700, 950)]);
                FinancialEntry::create(['date' => $d->toDateString(), 'type' => 'despesa', 'category' => 'Água', 'description' => 'Copasa', 'payment_method' => 'boleto', 'amount' => rand(250, 420)]);
            }
            if ($d->day === 20) {
                FinancialEntry::create(['date' => $d->toDateString(), 'type' => 'despesa', 'category' => 'Impostos', 'description' => 'DAS Simples Nacional', 'payment_method' => 'boleto', 'amount' => rand(2500, 3400)]);
            }
        }

        // Fornecedores, pedidos de compra e documentos
        $moinho = Supplier::create(['name' => 'Moinho Vale do Sol', 'phone' => '(31) 3333-1020', 'email' => 'vendas@moinhovs.com.br']);
        Supplier::create(['name' => 'Distribuidora Doce Lar', 'phone' => '(31) 3444-2211']);
        $po = PurchaseOrder::create(['supplier_id' => $moinho->id, 'status' => 'a_fazer', 'expected_date' => now()->addDays(2), 'user_id' => $gerente->id, 'notes' => 'Gerado a partir do estoque baixo']);
        $po->items()->create(['product_id' => $products['Farinha de trigo 25kg']->id, 'description' => 'Farinha de trigo 25kg', 'quantity' => 10, 'unit_cost' => 98]);
        $po->update(['total' => 980]);

        foreach ([
            ['Alvará de funcionamento 2026', 'Alvará / Licença', now()->addDays(40), null],
            ['Boleto Moinho Vale do Sol', 'Boleto', now()->addDays(3), 1960],
            ['Licença Vigilância Sanitária', 'Vigilância sanitária', now()->addDays(12), null],
            ['NF 4587 - Distribuidora Doce Lar', 'Nota fiscal', null, 742.30],
        ] as [$t, $c, $due, $amt]) {
            Document::create(['title' => $t, 'category' => $c, 'due_date' => $due, 'amount' => $amt, 'user_id' => $gerente->id]);
        }
    }
}
