<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;

class CashClosing extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => DateOnly::class];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
