<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $month = Carbon::parse($request->input('mes', now()->format('Y-m')).'-01');
        $first = $month->copy()->startOfMonth();
        $last = $month->copy()->endOfMonth();
        $gridStart = $first->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $last->copy()->endOfWeek(Carbon::SATURDAY);

        $q = Shift::with('employee')->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()]);
        if ($emp = $request->input('funcionario')) {
            $q->where('employee_id', $emp);
        }
        $shifts = $q->orderBy('start_time')->get()->groupBy(fn ($s) => $s->date->toDateString());

        $holidays = Holiday::whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])->get()->keyBy(fn ($h) => $h->date->toDateString());

        $day = Carbon::parse($request->input('dia', now()->between($first, $last) ? now()->toDateString() : $first->toDateString()));
        $dayShifts = Shift::with('employee')->whereDate('date', $day->toDateString())->orderBy('start_time')->get();

        // Horas do mês por funcionário (só dias do mês exibido)
        $hours = Shift::with('employee')->whereBetween('date', [$first->toDateString(), $last->toDateString()])->get()
            ->groupBy('employee_id')->map(fn ($g) => [
                'employee' => $g->first()->employee,
                'hours' => $g->sum('hours'),
                'days' => $g->map(fn ($s) => $s->date->toDateString())->unique()->count(),
                'weekend' => $g->filter(fn ($s) => $s->date->isWeekend() || $holidays->has($s->date->toDateString()))->map(fn ($s) => $s->date->toDateString())->unique()->count(),
            ])->sortByDesc('hours');

        return view('schedule.index', [
            'month' => $month, 'gridStart' => $gridStart, 'gridEnd' => $gridEnd,
            'shifts' => $shifts, 'holidays' => $holidays,
            'day' => $day, 'dayShifts' => $dayShifts, 'dayHoliday' => $holidays->get($day->toDateString()) ?? Holiday::whereDate('date', $day)->first(),
            'employees' => Employee::where('active', true)->orderBy('name')->get(),
            'hours' => $hours,
            'specialDays' => $this->specialDays($first, $last, $holidays),
        ]);
    }

    public function storeShift(Request $request)
    {
        $data = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:120',
            'repeat_weeks' => 'nullable|integer|min:0|max:12',
        ]);

        $count = 0;
        for ($w = 0; $w <= (int) ($data['repeat_weeks'] ?? 0); $w++) {
            $date = Carbon::parse($data['date'])->addWeeks($w)->toDateString();
            foreach ($data['employee_ids'] as $id) {
                Shift::create(['employee_id' => $id, 'date' => $date, 'start_time' => $data['start_time'], 'end_time' => $data['end_time'], 'notes' => $data['notes'] ?? null]);
                $count++;
            }
        }

        return back()->with('ok', "$count turno(s) adicionados.");
    }

    public function destroyShift(Shift $shift)
    {
        $shift->delete();

        return back()->with('ok', 'Turno removido.');
    }

    /** Aplica a escala padrão semanal de cada funcionário a todos os dias do mês. */
    public function generate(Request $request)
    {
        $month = Carbon::parse($request->input('mes').'-01');
        $skipHolidays = $request->boolean('skip_holidays');
        $holidays = Holiday::whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get()->map(fn ($h) => $h->date->toDateString())->all();
        $templates = ShiftTemplate::whereHas('employee', fn ($q) => $q->where('active', true))->get()->groupBy('weekday');
        $count = 0;

        DB::transaction(function () use ($month, $templates, $skipHolidays, $holidays, &$count) {
            for ($d = $month->copy()->startOfMonth(); $d->month === $month->month; $d->addDay()) {
                if ($skipHolidays && in_array($d->toDateString(), $holidays)) {
                    continue;
                }
                foreach ($templates->get($d->dayOfWeek, []) as $t) {
                    $exists = Shift::where('employee_id', $t->employee_id)->whereDate('date', $d->toDateString())->exists();
                    if (! $exists) {
                        Shift::create(['employee_id' => $t->employee_id, 'date' => $d->toDateString(), 'start_time' => $t->start_time, 'end_time' => $t->end_time]);
                        $count++;
                    }
                }
            }
        });

        return back()->with('ok', "Escala padrão aplicada: $count turnos criados.");
    }

    /**
     * Rodízio de plantão: distribui os funcionários escolhidos entre os fins de semana e feriados do mês,
     * em ordem, com N pessoas por dia, priorizando quem tem menos plantões.
     */
    public function rotation(Request $request)
    {
        $data = $request->validate([
            'mes' => 'required|date_format:Y-m',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'per_day' => 'required|integer|min:1|max:10',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'days' => 'required|array|min:1', // sabado, domingo, feriado
        ]);

        $first = Carbon::parse($data['mes'].'-01');
        $last = $first->copy()->endOfMonth();
        $holidays = Holiday::whereBetween('date', [$first->toDateString(), $last->toDateString()])->get()->keyBy(fn ($h) => $h->date->toDateString());
        $load = array_fill_keys($data['employee_ids'], 0);
        $count = 0;

        DB::transaction(function () use ($first, $last, $data, $holidays, &$load, &$count) {
            for ($d = $first->copy(); $d->lte($last); $d->addDay()) {
                $key = $d->toDateString();
                $match = ($d->isSaturday() && in_array('sabado', $data['days']))
                    || ($d->isSunday() && in_array('domingo', $data['days']))
                    || ($holidays->has($key) && in_array('feriado', $data['days']));
                if (! $match) {
                    continue;
                }
                // quem tem menos plantões vai primeiro (estável pela ordem escolhida)
                $order = $load;
                asort($order);
                $chosen = array_slice(array_keys($order), 0, min($data['per_day'], count($order)));
                foreach ($chosen as $id) {
                    Shift::where('employee_id', $id)->whereDate('date', $key)->delete();
                    Shift::create(['employee_id' => $id, 'date' => $key, 'start_time' => $data['start_time'], 'end_time' => $data['end_time'], 'notes' => $holidays->has($key) ? 'Plantão feriado' : 'Plantão fim de semana']);
                    $load[$id]++;
                    $count++;
                }
            }
        });

        return back()->with('ok', "Rodízio criado: $count plantões distribuídos.");
    }

    public function clear(Request $request)
    {
        $month = Carbon::parse($request->input('mes').'-01');
        $n = Shift::whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])->delete();

        return back()->with('ok', "$n turnos removidos do mês.");
    }

    public function team()
    {
        return view('schedule.team', [
            'employees' => Employee::with('templates')->orderByDesc('active')->orderBy('name')->get(),
            'holidays' => Holiday::whereYear('date', '>=', now()->year)->orderBy('date')->get(),
        ]);
    }

    public function storeEmployee(Request $request)
    {
        $employee = Employee::create($this->validateEmployee($request));
        $this->saveTemplates($employee, $request);

        return back()->with('ok', 'Funcionário cadastrado.');
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        $employee->update($this->validateEmployee($request) + ['active' => $request->boolean('active')]);
        $this->saveTemplates($employee, $request);

        return back()->with('ok', "Escala de {$employee->first_name} atualizada.");
    }

    public function destroyEmployee(Employee $employee)
    {
        $employee->update(['active' => false]);
        $employee->shifts()->whereDate('date', '>=', now()->toDateString())->delete();

        return back()->with('ok', 'Funcionário desativado e turnos futuros removidos.');
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate(['date' => 'required|date|unique:holidays,date', 'name' => 'required|string|max:80']);
        Holiday::create($data);

        return back()->with('ok', 'Feriado cadastrado.');
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with('ok', 'Feriado removido.');
    }

    private function validateEmployee(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'position' => 'required|string|max:40',
            'phone' => 'nullable|string|max:30',
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
    }

    private function saveTemplates(Employee $employee, Request $request): void
    {
        $employee->templates()->delete();
        foreach ((array) $request->input('tpl', []) as $wd => $t) {
            if (! empty($t['on']) && ! empty($t['start']) && ! empty($t['end'])) {
                $employee->templates()->create(['weekday' => (int) $wd, 'start_time' => $t['start'], 'end_time' => $t['end']]);
            }
        }
    }

    /** Fins de semana e feriados do mês com quem está escalado. */
    private function specialDays(Carbon $first, Carbon $last, $holidays)
    {
        $days = collect();
        $shifts = Shift::with('employee')->whereBetween('date', [$first->toDateString(), $last->toDateString()])->get()->groupBy(fn ($s) => $s->date->toDateString());
        for ($d = $first->copy(); $d->lte($last); $d->addDay()) {
            $k = $d->toDateString();
            if ($d->isWeekend() || $holidays->has($k)) {
                $days->push(['date' => $d->copy(), 'holiday' => $holidays->get($k), 'shifts' => $shifts->get($k, collect())]);
            }
        }

        return $days;
    }
}
