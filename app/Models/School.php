<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $guarded = [];

    public const BILLING = ['mensal' => 'Mensal', 'semanal' => 'Semanal', 'por_entrega' => 'Por entrega'];

    protected $casts = ['active' => 'boolean'];

    public function orders()
    {
        return $this->hasMany(SchoolOrder::class);
    }
}
