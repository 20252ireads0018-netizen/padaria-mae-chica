<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados iniciais de produção (sem dados de demonstração). Pode rodar várias vezes.
 * Cria o primeiro usuário da gerência a partir de ADMIN_EMAIL / ADMIN_PASSWORD do .env.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Setting::DEFAULTS as $k => $v) {
            Setting::firstOrCreate(['key' => $k], ['value' => $v]);
        }

        $year = now()->year;
        foreach ([
            '01-01' => 'Confraternização Universal', '04-21' => 'Tiradentes', '05-01' => 'Dia do Trabalho',
            '09-07' => 'Independência do Brasil', '10-12' => 'Nossa Senhora Aparecida', '11-02' => 'Finados',
            '11-15' => 'Proclamação da República', '11-20' => 'Consciência Negra', '12-25' => 'Natal',
        ] as $md => $name) {
            foreach ([$year, $year + 1] as $y) {
                Holiday::firstOrCreate(['date' => "$y-$md"], ['name' => $name]);
            }
        }

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if ($email && $password && ! User::where('email', $email)->exists()) {
            User::create(['name' => env('ADMIN_NAME', 'Gerência'), 'email' => $email, 'password' => $password, 'role' => 'gerencia']);
            $this->command?->info("Usuário da gerência criado: $email");
        }
    }
}
