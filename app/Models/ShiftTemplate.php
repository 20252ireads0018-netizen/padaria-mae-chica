<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftTemplate extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
