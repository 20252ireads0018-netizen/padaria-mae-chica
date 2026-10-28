<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $guarded = [];
    protected $casts = ['date' => DateOnly::class];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getHoursAttribute(): float
    {
        $s = strtotime($this->start_time);
        $e = strtotime($this->end_time);
        if ($e <= $s) {
            $e += 86400; // turno que vira a noite
        }

        return round(($e - $s) / 3600, 1);
    }

    public function getPeriodAttribute(): string
    {
        return substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5);
    }
}
