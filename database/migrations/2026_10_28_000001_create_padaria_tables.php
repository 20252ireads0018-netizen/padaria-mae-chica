<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('caixa')->after('email'); // gerencia | caixa | producao
            $table->boolean('active')->default(true)->after('role');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku', 40)->nullable();
            $table->string('category', 40)->default('Pães');
            $table->string('unit', 10)->default('un');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('stock', 10, 3)->default(0);
            $table->decimal('min_stock', 10, 3)->default(0);
            $table->boolean('is_supply')->default(false); // insumo (farinha, fermento...) x produto de venda
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 10, 3); // + entrada / - saída
            $table->string('reason', 60);
            $table->timestamps();
        });

        // Lançamentos financeiros: base para fechamento de caixa e contabilidade
        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('type', 10); // receita | despesa
            $table->string('category', 40);
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20)->default('dinheiro'); // dinheiro | pix | cartao | boleto | outro
            $table->nullableMorphs('source'); // encomenda, pedido escola, compra...
            $table->string('source_slot', 20)->nullable(); // ex.: sinal | saldo
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('income_cash', 12, 2)->default(0);
            $table->decimal('income_pix', 12, 2)->default(0);
            $table->decimal('income_card', 12, 2)->default(0);
            $table->decimal('income_other', 12, 2)->default(0);
            $table->decimal('total_income', 12, 2)->default(0);
            $table->decimal('total_expense', 12, 2)->default(0);
            $table->decimal('expected_balance', 12, 2)->default(0);
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->default(0);
            $table->decimal('reserve_amount', 12, 2)->default(0); // separado para o fluxo de caixa
            $table->decimal('available_balance', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('customer_phone', 30)->nullable();
            $table->dateTime('due_at')->index();
            $table->string('status', 20)->default('pendente'); // pendente | producao | pronta | entregue | cancelada
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('deposit', 12, 2)->default(0); // sinal pago
            $table->boolean('paid')->default(false);
            $table->string('payment_method', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 10, 2);
        });

        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('billing', 20)->default('mensal'); // mensal | semanal | por_entrega
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('school_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('delivery_date')->index();
            $table->string('shift', 10)->default('manha'); // manha | tarde
            $table->string('status', 20)->default('pendente'); // pendente | producao | entregue | cancelada
            $table->decimal('total', 12, 2)->default(0);
            $table->boolean('paid')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('school_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 10, 2);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position', 40); // Padeiro, Caixa, Atendente...
            $table->string('phone', 30)->nullable();
            $table->string('color', 7)->default('#C8102E');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
        });

        // Escala padrão semanal (gera os turnos do mês)
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0=domingo ... 6=sábado
            $table->time('start_time');
            $table->time('end_time');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('a_fazer'); // a_fazer | enviado | recebido | cancelado
            $table->date('expected_date')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_cost', 10, 2);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 40); // Nota fiscal, Boleto, Contrato, Alvará...
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['documents', 'purchase_order_items', 'purchase_orders', 'suppliers', 'shifts', 'shift_templates',
            'holidays', 'employees', 'school_order_items', 'school_orders', 'schools', 'order_items', 'orders',
            'cash_closings', 'financial_entries', 'stock_movements', 'products', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active']));
    }
};
