<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;

class FinancialEntry extends Model
{
    protected $guarded = [];

    public const METHODS = ['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'cartao' => 'Cartão', 'boleto' => 'Boleto', 'outro' => 'Outro'];

    public const CATEGORIES = [
        'receita' => ['Vendas balcão', 'Encomendas', 'Escolas', 'Outras receitas'],
        'despesa' => ['Insumos', 'Fornecedores', 'Salários', 'Energia', 'Água', 'Aluguel', 'Gás', 'Impostos', 'Manutenção', 'Embalagens', 'Sangria', 'Outras despesas'],
    ];

    protected function casts(): array
    {
        return ['date' => DateOnly::class, 'amount' => 'float'];
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isAutomatic(): bool
    {
        return $this->source_type !== null;
    }

    /**
     * Cria/atualiza o lançamento automático vinculado a uma origem (encomenda, escola, compra). Valor 0 remove.
     * A data é a do primeiro registro, para o valor cair no caixa do dia em que foi recebido/pago.
     */
    public static function syncFrom(Model $source, string $type, string $category, float $amount, string $description, ?string $method = null, string $slot = 'total'): void
    {
        $entry = static::firstOrNew(['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'source_slot' => $slot], ['category' => $category]);

        if ($amount <= 0) {
            if ($entry->exists) {
                $entry->delete();
            }

            return;
        }

        $entry->fill([
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'payment_method' => $method ?? $entry->payment_method ?? 'dinheiro',
            'date' => $entry->date ?? now()->toDateString(),
            'user_id' => $entry->user_id ?? auth()->id(),
        ])->save();
    }
}
