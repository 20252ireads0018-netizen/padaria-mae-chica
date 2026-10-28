<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;

class SchoolOrder extends Model
{
    protected $guarded = [];

    public const STATUSES = ['pendente' => 'Pendente', 'producao' => 'Em produção', 'entregue' => 'Entregue', 'cancelada' => 'Cancelada'];
    public const SHIFTS = ['manha' => 'Manhã', 'tarde' => 'Tarde'];

    protected function casts(): array
    {
        return ['delivery_date' => DateOnly::class, 'paid' => 'boolean', 'total' => 'float'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function items()
    {
        return $this->hasMany(SchoolOrderItem::class);
    }

    public function syncFinance(): void
    {
        $amount = ($this->paid && $this->status !== 'cancelada') ? $this->total : 0;
        FinancialEntry::syncFrom($this, 'receita', 'Escolas', $amount, "Escola: {$this->school->name} ({$this->delivery_date->format('d/m')})", 'pix');
    }
}
