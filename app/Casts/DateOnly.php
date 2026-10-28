<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Carbon;

/** Grava colunas DATE como "Y-m-d" (sem hora), mantendo filtros por data consistentes no SQLite e no MySQL. */
class DateOnly implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return $value === null ? null : Carbon::parse($value)->startOfDay();
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
