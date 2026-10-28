<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['quantity' => 'float', 'unit_cost' => 'float'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
