<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    public const DEFAULTS = [
        'reserve_percent' => '10',   // % do resultado do dia separado para o fluxo de caixa
        'reserve_minimum' => '50',   // valor mínimo separado por dia (quando houver resultado)
        'cash_float' => '150',       // troco que fica na gaveta para o dia seguinte
        'order_alert_hours' => '48', // antecedência dos lembretes de encomendas
    ];

    protected static ?array $cache = null;

    public static function get(string $key, $default = null)
    {
        static::$cache ??= static::pluck('value', 'key')->all();

        return static::$cache[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::$cache = null;
    }
}
