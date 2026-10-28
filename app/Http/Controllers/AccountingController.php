<?php

namespace App\Http\Controllers;

use App\Models\CashClosing;
use App\Models\FinancialEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AccountingController extends Controller
{
    public function index(Request $request)
    {
        [$start, $end, $period] = $this->range($request);
        $days = $start->diffInDays($end) + 1;

        // Mesmo intervalo imediatamente anterior, para comparação
        $prevEnd = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1);

        $base = fn ($s, $e) => FinancialEntry::whereBetween('date', [$s->toDateString(), $e->toDateString()]);
        $totals = fn ($s, $e) => $base($s, $e)->selectRaw('type, SUM(amount) as t')->groupBy('type')->pluck('t', 'type');

        $cur = $totals($start, $end);
        $prev = $totals($prevStart, $prevEnd);
        $income = (float) ($cur['receita'] ?? 0);
        $expense = (float) ($cur['despesa'] ?? 0);
        $pIncome = (float) ($prev['receita'] ?? 0);
        $pExpense = (float) ($prev['despesa'] ?? 0);

        // Série: por dia (até ~2 meses) ou por mês
        $byMonth = $days > 62;
        $key = $byMonth ? 'substr(date, 1, 7)' : 'substr(date, 1, 10)';
        $rows = $base($start, $end)->selectRaw("$key as k, type, SUM(amount) as t")->groupByRaw("$key, type")->get()->groupBy('k');
        $series = [];
        for ($d = $start->copy(); $d->lte($end); $byMonth ? $d->addMonthNoOverflow()->startOfMonth() : $d->addDay()) {
            $k = $byMonth ? $d->format('Y-m') : $d->toDateString();
            $r = $rows->get($k, collect());
            $series[] = [
                'label' => $byMonth ? mb_substr(month_name($d->month), 0, 3).'/'.$d->format('y') : $d->format('d/m'),
                'in' => (float) $r->firstWhere('type', 'receita')?->t,
                'out' => (float) $r->firstWhere('type', 'despesa')?->t,
            ];
        }

        $byCategory = $base($start, $end)->selectRaw('type, category, SUM(amount) as t, COUNT(*) as n')
            ->groupBy('type', 'category')->orderByDesc('t')->get()->groupBy('type');

        $byMethod = $base($start, $end)->where('type', 'receita')->selectRaw('payment_method, SUM(amount) as t')
            ->groupBy('payment_method')->orderByDesc('t')->pluck('t', 'payment_method');

        $entries = $base($start, $end)->with('user')
            ->when($request->input('tipo'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->input('cat'), fn ($q, $c) => $q->where('category', $c))
            ->orderByDesc('date')->orderByDesc('id')->paginate(30)->withQueryString();

        $closings = CashClosing::whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        // Resumo anual mês a mês (DRE simplificada)
        $year = (int) $request->input('ano', $end->year);
        $yearRows = FinancialEntry::whereBetween('date', ["$year-01-01", "$year-12-31"])
            ->selectRaw('substr(date, 6, 2) as m, type, SUM(amount) as t')->groupByRaw('substr(date, 6, 2), type')->get()->groupBy('m');
        $yearly = collect(range(1, 12))->map(fn ($m) => [
            'm' => $m,
            'in' => (float) $yearRows->get(str_pad($m, 2, '0', STR_PAD_LEFT), collect())->firstWhere('type', 'receita')?->t,
            'out' => (float) $yearRows->get(str_pad($m, 2, '0', STR_PAD_LEFT), collect())->firstWhere('type', 'despesa')?->t,
        ]);

        return view('accounting.index', [
            'start' => $start, 'end' => $end, 'period' => $period, 'days' => $days,
            'income' => $income, 'expense' => $expense, 'profit' => $income - $expense,
            'margin' => $income > 0 ? ($income - $expense) / $income * 100 : 0,
            'var' => [
                'income' => $pIncome > 0 ? ($income - $pIncome) / $pIncome * 100 : null,
                'expense' => $pExpense > 0 ? ($expense - $pExpense) / $pExpense * 100 : null,
                'profit' => ($pIncome - $pExpense) != 0 ? (($income - $expense) - ($pIncome - $pExpense)) / abs($pIncome - $pExpense) * 100 : null,
            ],
            'avgDay' => $income / max(1, $days),
            'series' => $series, 'byMonth' => $byMonth,
            'byCategory' => $byCategory, 'byMethod' => $byMethod,
            'entries' => $entries,
            'reserve' => (float) (clone $closings)->sum('reserve_amount'),
            'closingsCount' => (clone $closings)->count(),
            'difference' => (float) (clone $closings)->sum('difference'),
            'year' => $year, 'yearly' => $yearly,
        ]);
    }

    public function export(Request $request)
    {
        [$start, $end] = $this->range($request);
        $name = "lancamentos_{$start->format('Ymd')}_{$end->format('Ymd')}.csv";

        return response()->streamDownload(function () use ($start, $end) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel reconhecer acentos
            fputcsv($out, ['Data', 'Tipo', 'Categoria', 'Descrição', 'Forma de pagamento', 'Valor', 'Origem'], ';');
            FinancialEntry::whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('date')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [
                            $r->date->format('d/m/Y'), ucfirst($r->type), $r->category, $r->description,
                            FinancialEntry::METHODS[$r->payment_method] ?? $r->payment_method,
                            number_format(($r->type === 'despesa' ? -1 : 1) * $r->amount, 2, ',', ''),
                            $r->source_type ? 'Automático' : 'Manual',
                        ], ';');
                    }
                });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** periodo: hoje | semana | mes | mes_anterior | trimestre | ano | ano_anterior | 12m | personalizado */
    private function range(Request $request): array
    {
        $period = $request->input('periodo', 'mes');
        $now = now();

        [$s, $e] = match ($period) {
            'hoje' => [$now->copy()->startOfDay(), $now->copy()],
            'semana' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'mes_anterior' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'trimestre' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'ano' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'ano_anterior' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            '12m' => [$now->copy()->subMonths(11)->startOfMonth(), $now->copy()->endOfMonth()],
            'personalizado' => [
                Carbon::parse($request->input('inicio', $now->copy()->startOfMonth()->toDateString())),
                Carbon::parse($request->input('fim', $now->toDateString())),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        if ($s->gt($e)) {
            [$s, $e] = [$e, $s];
        }

        return [$s->startOfDay(), $e->startOfDay(), $period];
    }
}
