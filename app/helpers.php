<?php

if (! function_exists('brl')) {
    function brl($value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }
}

if (! function_exists('qty')) {
    /** Quantidade sem zeros desnecessários: 3.000 → 3 | 2.500 → 2,5 */
    function qty($value): string
    {
        $v = (float) $value;

        return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
    }
}

if (! function_exists('money_in')) {
    /** Aceita "1.234,56", "1234,56" ou "1234.56". */
    function money_in($value): float
    {
        $v = trim((string) $value);
        if ($v === '') {
            return 0;
        }
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }

        return round((float) $v, 2);
    }
}

if (! function_exists('month_name')) {
    function month_name(int $m): string
    {
        return ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'][$m];
    }
}

if (! function_exists('weekday_name')) {
    function weekday_name(int $d, bool $short = false): string
    {
        $long = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

        return $short ? mb_substr($long[$d], 0, 3) : $long[$d];
    }
}
