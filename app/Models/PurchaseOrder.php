<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $guarded = [];

    public const STATUSES = ['a_fazer' => 'A fazer', 'enviado' => 'Enviado', 'recebido' => 'Recebido', 'cancelado' => 'Cancelado'];

    protected function casts(): array
    {
        return ['expected_date' => DateOnly::class, 'total' => 'float'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
