<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['date' => DateOnly::class];
}
