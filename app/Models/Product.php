<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    public const CATEGORIES = ['Pães', 'Doces', 'Salgados', 'Tortas', 'Bolos', 'Frios', 'Bebidas', 'Insumos', 'Embalagens', 'Outros'];
    public const UNITS = ['un', 'kg', 'g', 'L', 'cx', 'pct', 'dz', 'saco'];

    protected function casts(): array
    {
        return [
            'price' => 'float', 'cost' => 'float',
            'stock' => 'float', 'min_stock' => 'float',
            'is_supply' => 'boolean', 'active' => 'boolean',
        ];
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->where('active', true)->where('min_stock', '>', 0)->whereColumn('stock', '<=', 'min_stock');
    }

    public function isLow(): bool
    {
        return $this->min_stock > 0 && $this->stock <= $this->min_stock;
    }

    public function getMarginAttribute(): ?float
    {
        return $this->price > 0 ? round((($this->price - $this->cost) / $this->price) * 100, 1) : null;
    }

    /** Quantidade sugerida para repor até 2x o estoque mínimo. */
    public function getSuggestedOrderAttribute(): float
    {
        return max(0, round($this->min_stock * 2 - $this->stock, 3));
    }

    public function adjustStock(float $qty, string $reason, ?int $userId = null): void
    {
        $this->increment('stock', $qty);
        $this->movements()->create(['quantity' => $qty, 'reason' => $reason, 'user_id' => $userId]);
    }
}
