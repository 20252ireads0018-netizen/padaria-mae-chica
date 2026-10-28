@extends('layouts.app')
@section('title', 'Escala de trabalho')
@section('content')
@php
    $isMgr = auth()->user()->hasRole();
    $q = fn ($extra = []) => '?'.http_build_query(array_filter(array_merge(['mes' => $month->format('Y-m'), 'funcionario' => request('funcionario')], $extra)));
    $toMin = fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
@endphp
<div class="page-head">
    <div><h1>Escala de trabalho</h1><div class="sub">{{ month_name($month->month) }} de {{ $month->year }} · fins de semana e feriados em destaque</div></div>
    <div class="actions">
        <a class="btn btn-ico" href="{{ $q(['mes' => $month->copy()->subMonthNoOverflow()->format('Y-m'), 'dia' => null]) }}"><x-icon n="left" /></a>
        <a class="btn" href="?mes={{ now()->format('Y-m') }}">Hoje</a>
        <a class="btn btn-ico" href="{{ $q(['mes' => $month->copy()->addMonthNoOverflow()->format('Y-m'), 'dia' => null]) }}"><x-icon n="right" /></a>
        <form class="filters inline">
            <input type="hidden" name="mes" value="{{ $month->format('Y-m') }}">
            <select name="funcionario" data-autosubmit><option value="">Toda a equipe</option>@foreach ($employees as $e)<option value="{{ $e->id }}" @selected(request('funcionario') == $e->id)>{{ $e->name }}</option>@endforeach</select>
        </form>
        @if ($isMgr)
            <a class="btn" href="{{ route('schedule.team') }}"><x-icon n="users" /> Equipe & escala padrão</a>
            <button class="btn btn-y" data-open="dlg-rot"><x-icon n="repeat" /> Rodízio FDS/feriados</button>
            <form class="inline" method="POST" action="{{ route('schedule.generate') }}" data-confirm="Aplicar a escala padrão semanal a todos os dias de {{ month_name($month->month) }}? Turnos já existentes serão mantidos.">@csrf<input type="hidden" name="mes" value="{{ $month->format('Y-m') }}"><button class="btn btn-p"><x-icon n="wand" /> Gerar mês</button></form>
        @endif
    </div>
</div>

<div class="grid g-side">
    <div class="stack">
        <div class="card">
            <div class="cal">
                @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $i => $w)<div class="wd {{ in_array($i, [0, 6]) ? 'we' : '' }}">{{ $w }}</div>@endforeach
                @for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay())
                    @php
                        $k = $d->toDateString(); $list = $shifts->get($k, collect()); $hol = $holidays->get($k);
                        $cls = collect([$d->month !== $month->month ? 'out' : '', $d->isWeekend() ? 'we' : '', $hol ? 'hol' : '', $d->isToday() ? 'today' : '', $k === $day->toDateString() ? 'sel' : ''])->filter()->join(' ');
                    @endphp
                    <a class="d {{ $cls }}" href="{{ $q(['dia' => $k]) }}">
                        <div class="n"><b>{{ $d->day }}</b>@if ($list->count())<small class="muted" style="margin-left:auto">{{ $list->pluck('employee_id')->unique()->count() }}p</small>@endif</div>
                        @if ($hol)<div class="hn">{{ $hol->name }}</div>@endif
                        @foreach ($list->take(4) as $s)
                            <div class="chip"><span class="dot" style="background: {{ $s->employee->color }}"></span><span class="nm">{{ $s->employee->first_name }}</span> <small class="muted">{{ substr($s->start_time, 0, 2) }}–{{ substr($s->end_time, 0, 2) }}h</small></div>
                        @endforeach
                        @if ($list->count() > 4)<div class="more">+{{ $list->count() - 4 }} turno(s)</div>@endif
                    </a>
                @endfor
            </div>
        </div>

        <div class="card">
            <div class="card-h"><x-icon n="sun" /><h2>Fins de semana e feriados de {{ month_name($month->month) }}</h2></div>
            <div class="table-wrap">
                <table class="t">
                    <thead><tr><th>Dia</th><th>Quem trabalha</th></tr></thead>
                    <tbody>
                    @foreach ($specialDays as $sd)
                        <tr class="{{ $sd['holiday'] ? 'row-red' : '' }}">
                            <td class="nowrap"><a class="b" href="{{ $q(['dia' => $sd['date']->toDateString()]) }}">{{ $sd['date']->format('d/m') }} {{ weekday_name($sd['date']->dayOfWeek, true) }}</a>@if ($sd['holiday'])<br><small class="neg b">{{ $sd['holiday']->name }}</small>@endif</td>
                            <td>
                                @forelse ($sd['shifts'] as $s)
                                    <span class="tag" style="background: {{ $s->employee->color }}1f; color: {{ $s->employee->color }}"><span class="dot" style="background: {{ $s->employee->color }}"></span> {{ $s->employee->name }} · {{ $s->period }}</span>
                                @empty
                                    <span class="tag red">Ninguém escalado</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-h"><x-icon n="clock" /><h2>{{ ucfirst($day->translatedFormat('l, d/m')) }}</h2>@if ($dayHoliday)<div class="r"><span class="tag red">{{ $dayHoliday->name }}</span></div>@endif</div>
            @if ($dayShifts->isEmpty())
                <div class="empty">Ninguém escalado neste dia.</div>
            @else
                @php
                    $minH = max(0, intdiv($dayShifts->min(fn ($s) => $toMin($s->start_time)), 60) - 1);
                    $maxH = min(24, (int) ceil($dayShifts->max(fn ($s) => $toMin($s->end_time) <= $toMin($s->start_time) ? 1440 : $toMin($s->end_time)) / 60) + 1);
                    $span = max(1, ($maxH - $minH) * 60);
                @endphp
                <div class="card-b">
                    <div class="timeline">
                        <div class="hours" style="grid-template-columns: repeat({{ $maxH - $minH }}, 1fr); margin-left: 90px">
                            @for ($h = $minH; $h < $maxH; $h++)<span>{{ $h }}h</span>@endfor
                        </div>
                        @foreach ($dayShifts as $s)
                            @php $a = $toMin($s->start_time); $b = $toMin($s->end_time); if ($b <= $a) $b = 1440; @endphp
                            <div class="ln">
                                <div class="nm" style="width:90px">{{ $s->employee->first_name }}</div>
                                <div class="track">
                                    <div class="bar" style="left: {{ ($a - $minH * 60) / $span * 100 }}%; width: {{ ($b - $a) / $span * 100 }}%; background: {{ $s->employee->color }}">{{ $s->period }}</div>
                                    @if ($day->isToday() && now()->hour >= $minH && now()->hour < $maxH)<div class="now" style="left: {{ (now()->hour * 60 + now()->minute - $minH * 60) / $span * 100 }}%"></div>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="list">
                    @foreach ($dayShifts as $s)
                        @php
                            $a = $toMin($s->start_time); $b = $toMin($s->end_time) <= $a ? 1440 : $toMin($s->end_time);
                            $with = $dayShifts->filter(fn ($o) => $o->id !== $s->id && $toMin($o->start_time) < $b && ($toMin($o->end_time) <= $toMin($o->start_time) ? 1440 : $toMin($o->end_time)) > $a)->map(fn ($o) => $o->employee->first_name)->unique();
                        @endphp
                        <div>
                            <span class="av-sm" style="background: {{ $s->employee->color }}">{{ $s->employee->initials }}</span>
                            <div class="grow"><div class="t1">{{ $s->employee->name }} <small class="muted">{{ $s->hours }}h</small></div><div class="t2">{{ $s->period }}{{ $s->notes ? ' · '.$s->notes : '' }}{{ $with->count() ? ' · junto com '.$with->join(', ') : '' }}</div></div>
                            @if ($isMgr)<form class="inline" method="POST" action="{{ route('schedule.shifts.destroy', $s) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost btn-danger btn-ico"><x-icon n="x" /></button></form>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($isMgr)
            <div class="card">
                <div class="card-h"><x-icon n="plus" /><h2>Adicionar turno em {{ $day->format('d/m') }}</h2></div>
                <form class="card-b" method="POST" action="{{ route('schedule.shifts.store') }}">
                    @csrf
                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                    <div class="f"><span class="muted" style="font-size:.76rem;font-weight:600">Quem (pode marcar mais de um para trabalharem juntos)</span>
                        <div class="flex wrap mt-s">@foreach ($employees as $e)<label class="check" style="font-size:.84rem"><input type="checkbox" name="employee_ids[]" value="{{ $e->id }}"> <span class="dot" style="background: {{ $e->color }}"></span>{{ $e->first_name }}</label>@endforeach</div>
                    </div>
                    <div class="row mt-s">
                        <label class="f"><span>Início</span><input type="time" name="start_time" value="06:00" required></label>
                        <label class="f"><span>Fim</span><input type="time" name="end_time" value="14:00" required></label>
                    </div>
                    <div class="row">
                        <label class="f"><span>Observação</span><input type="text" name="notes" placeholder="Ex.: plantão, cobertura"></label>
                        <label class="f"><span>Repetir por (semanas)</span><input type="number" name="repeat_weeks" value="0" min="0" max="12"></label>
                    </div>
                    <button class="btn btn-p w100" style="justify-content:center"><x-icon n="check" /> Escalar</button>
                </form>
            </div>
        @endif

        <div class="card">
            <div class="card-h"><x-icon n="chart" /><h2>Horas no mês</h2></div>
            <table class="t">
                <thead><tr><th>Funcionário</th><th class="num">Dias</th><th class="num">FDS/Fer.</th><th class="num">Horas</th></tr></thead>
                <tbody>
                @forelse ($hours as $h)
                    <tr><td><span class="dot" style="background: {{ $h['employee']->color }}"></span> {{ $h['employee']->name }}</td><td class="num">{{ $h['days'] }}</td><td class="num">{{ $h['weekend'] }}</td><td class="num b">{{ qty($h['hours']) }}h</td></tr>
                @empty
                    <tr><td colspan="4"><div class="empty">Sem turnos no mês.</div></td></tr>
                @endforelse
                </tbody>
            </table>
            @if ($isMgr && $hours->count())
                <form class="card-b" method="POST" action="{{ route('schedule.clear') }}" data-confirm="Remover TODOS os turnos de {{ month_name($month->month) }}?">@csrf<input type="hidden" name="mes" value="{{ $month->format('Y-m') }}"><button class="btn btn-sm btn-danger"><x-icon n="trash" /> Limpar mês</button></form>
            @endif
        </div>
    </div>
</div>

@if ($isMgr)
<dialog id="dlg-rot">
    <div class="card-h"><h2>Rodízio de fins de semana e feriados</h2><div class="r"><button class="btn btn-ghost btn-ico" data-close><x-icon n="x" /></button></div></div>
    <form class="card-b" method="POST" action="{{ route('schedule.rotation') }}">
        @csrf
        <input type="hidden" name="mes" value="{{ $month->format('Y-m') }}">
        <p class="muted mb0" style="font-size:.84rem">Distribui os plantões de {{ month_name($month->month) }} igualmente entre as pessoas escolhidas, sempre priorizando quem tem menos plantões.</p>
        <div class="f mt"><span class="muted" style="font-size:.76rem;font-weight:600">Participantes <a href="#" data-checkall="employee_ids[]">(todos)</a></span>
            <div class="flex wrap mt-s">@foreach ($employees as $e)<label class="check"><input type="checkbox" name="employee_ids[]" value="{{ $e->id }}"> {{ $e->name }}</label>@endforeach</div>
        </div>
        <div class="f mt"><span class="muted" style="font-size:.76rem;font-weight:600">Dias</span>
            <div class="flex wrap mt-s">
                <label class="check"><input type="checkbox" name="days[]" value="sabado"> Sábados</label>
                <label class="check"><input type="checkbox" name="days[]" value="domingo" checked> Domingos</label>
                <label class="check"><input type="checkbox" name="days[]" value="feriado" checked> Feriados</label>
            </div>
        </div>
        <div class="row mt">
            <label class="f"><span>Pessoas por dia</span><input type="number" name="per_day" value="2" min="1" max="10"></label>
            <label class="f"><span>Início</span><input type="time" name="start_time" value="06:00"></label>
            <label class="f"><span>Fim</span><input type="time" name="end_time" value="13:00"></label>
        </div>
        <button class="btn btn-p w100" style="justify-content:center"><x-icon n="repeat" /> Distribuir plantões</button>
    </form>
</dialog>
@endif
@endsection
