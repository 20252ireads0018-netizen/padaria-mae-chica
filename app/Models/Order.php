<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    public const STATUSES = [
        'pendente' => 'Pendente',
        'producao' => 'Em produção',
        'pronta' => 'Pronta',
        'entregue' => 'Entregue',
        'cancelada' => 'Cancelada',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'paid' => 'boolean', 'total' => 'float', 'deposit' => 'float'];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen($q)
    {
        return $q->whereNotIn('status', ['entregue', 'cancelada']);
    }

    public function getBalanceAttribute(): float
    {
        return $this->paid ? 0 : max(0, $this->total - $this->deposit);
    }

    /** atrasada | hoje | breve | ok */
    public function getUrgencyAttribute(): string
    {
        if (in_array($this->status, ['entregue', 'cancelada'])) {
            return 'ok';
        }
        if ($this->due_at->isPast()) {
            return 'atrasada';
        }
        if ($this->due_at->isToday()) {
            return 'hoje';
        }

        return now()->diffInHours($this->due_at) <= (int) Setting::get('order_alert_hours') ? 'breve' : 'ok';
    }

    /** Lança no caixa, automaticamente, o sinal (no dia em que foi dado) e o saldo (no dia em que foi pago). */
    public function syncFinance(): void
    {
        $active = $this->status !== 'cancelada';
        $deposit = $active ? min($this->deposit, $this->total) : 0;
        $rest = $active && $this->paid ? $this->total - $deposit : 0;
        $label = "Encomenda #{$this->id} - {$this->customer_name}";

        FinancialEntry::syncFrom($this, 'receita', 'Encomendas', $deposit, "$label (sinal)", $this->payment_method, 'sinal');
        FinancialEntry::syncFrom($this, 'receita', 'Encomendas', $rest, $deposit > 0 ? "$label (saldo)" : $label, $this->payment_method, 'saldo');
    }
}
