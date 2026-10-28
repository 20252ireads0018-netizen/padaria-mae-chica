@extends('layouts.app')
@section('title', 'Painel')
@section('content')
@php $u = auth()->user(); @endphp
<div class="page-head">
    <div>
        <h1>Olá, {{ explode(' ', $u->name)[0] }}!</h1>
        <div class="sub">
            @if ($holiday)<span class="tag red">Feriado: {{ $holiday->name }}</span>
            @elseif ($nextHoliday)Próximo feriado: <b>{{ $nextHoliday->name }}</b> ({{ $nextHoliday->date->format('d/m') }}, {{ weekday_name($nextHoliday->date->dayOfWeek) }})@endif
        </div>
    </div>
    <div class="actions">
        @if ($u->hasRole('caixa'))
            <a class="btn btn-y" href="{{ route('orders.create') }}"><x-icon n="plus" /> Nova encomenda</a>
            <a class="btn btn-p" href="{{ route('cash.closing.create') }}"><x-icon n="cash" /> {{ $closedToday ? 'Ver fechamento' : 'Fechar caixa de hoje' }}</a>
        @endif
    </div>
</div>

<div class="grid g4 mb">
    @if ($u->hasRole('caixa'))
        <div class="card kpi green"><div class="ic"><x-icon n="up" /></div><div class="l">Receitas hoje</div><div class="v">{{ brl($income) }}</div><div class="s">Lançamentos do dia</div></div>
        <div class="card kpi red"><div class="ic"><x-icon n="down" /></div><div class="l">Despesas hoje</div><div class="v">{{ brl($expense) }}</div><div class="s">Saldo: <b class="{{ $income - $expense >= 0 ? 'pos' : 'neg' }}">{{ brl($income - $expense) }}</b></div></div>
    @endif
    <div class="card kpi"><div class="ic"><x-icon n="bag" /></div><div class="l">Encomendas em breve</div><div class="v">{{ $orders->count() }}</div><div class="s">{{ $orders->where('urgency', 'atrasada')->count() }} atrasada(s) · {{ $orders->where('urgency', 'hoje')->count() }} hoje</div></div>
    <div class="card kpi blue"><div class="ic"><x-icon n="box" /></div><div class="l">Estoque baixo</div><div class="v">{{ $lowStock->count() }}</div><div class="s">{{ $poTodo }} pedido(s) de compra a fazer</div></div>
    @if (! $u->hasRole('caixa'))
        <div class="card kpi green"><div class="ic"><x-icon n="school" /></div><div class="l">Entregas escolas</div><div class="v">{{ $schoolOrders->count() }}</div><div class="s">hoje e amanhã</div></div>
        <div class="card kpi red"><div class="ic"><x-icon n="users" /></div><div class="l">Equipe hoje</div><div class="v">{{ $shiftsToday->pluck('employee_id')->unique()->count() }}</div><div class="s">pessoas escaladas</div></div>
    @endif
</div>

<div class="grid g-main">
    <div class="stack">
        <div class="card">
            <div class="card-h"><x-icon n="bell" /><h2>Encomendas — próximos prazos</h2><div class="r"><a class="btn btn-sm" href="{{ route('orders.index') }}">Ver todas</a></div></div>
            <div class="list">
                @forelse ($orders as $o)
                    @php $cls = ['atrasada' => 'red', 'hoje' => 'yel'][$o->urgency] ?? ''; @endphp
                    <a href="{{ route('orders.show', $o) }}">
                        <div class="when {{ $cls }}"><b>{{ $o->due_at->format('d/m') }}</b><small>{{ $o->due_at->format('H:i') }}</small></div>
                        <div class="grow">
                            <div class="t1">{{ $o->customer_name }} @if ($o->urgency === 'atrasada')<span class="tag red">Atrasada</span>@elseif ($o->urgency === 'hoje')<span class="tag yel">Hoje</span>@endif</div>
                            <div class="t2">{{ $o->items->map(fn ($i) => qty($i->quantity).'× '.$i->description)->join(' · ') }}</div>
                        </div>
                        <div class="right">
                            <div class="b">{{ brl($o->total) }}</div>
                            <div class="t2">{!! $o->paid ? '<span class="pos">Pago</span>' : 'Falta '.brl($o->balance) !!}</div>
                        </div>
                        @include('partials.status', ['s' => $o->status])
                    </a>
                @empty
                    <div class="empty"><x-icon n="check" />Nenhuma encomenda nas próximas horas.</div>
                @endforelse
            </div>
        </div>

        <div class="grid g2">
            <div class="card">
                <div class="card-h"><x-icon n="bread" /><h2>Produção: hoje e amanhã</h2></div>
                <div class="card-b tight">
                    @if ($production)
                        <table class="t">
                            @php $pmax = max($production); @endphp
                            @foreach ($production as $name => $q)
                                <tr><td>{{ $name }}</td><td style="width:35%"><div class="hbar"><i style="width:{{ $q / $pmax * 100 }}%"></i></div></td><td class="num b">{{ qty($q) }}</td></tr>
                            @endforeach
                        </table>
                    @else
                        <div class="empty">Sem produção extra programada.</div>
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="card-h"><x-icon n="school" /><h2>Entregas para escolas</h2><div class="r"><a class="btn btn-sm" href="{{ route('schools.index') }}">Abrir</a></div></div>
                <div class="list">
                    @forelse ($schoolOrders as $so)
                        <div>
                            <div class="when {{ $so->delivery_date->isToday() ? 'yel' : '' }}"><b>{{ $so->delivery_date->isToday() ? 'Hoje' : 'Amanhã' }}</b><small>{{ \App\Models\SchoolOrder::SHIFTS[$so->shift] }}</small></div>
                            <div class="grow"><div class="t1">{{ $so->school->name }}</div><div class="t2">{{ $so->items->map(fn ($i) => qty($i->quantity).' '.$i->description)->join(' · ') }}</div></div>
                            @include('partials.status', ['s' => $so->status])
                        </div>
                    @empty
                        <div class="empty">Nenhuma entrega hoje ou amanhã.</div>
                    @endforelse
                </div>
            </div>
        </div>

        @if ($u->hasRole('caixa'))
            <div class="card">
                <div class="card-h"><x-icon n="chart" /><h2>Últimos 14 dias</h2>@if ($u->hasRole())<div class="r"><a class="btn btn-sm" href="{{ route('accounting.index') }}">Contabilidade</a></div>@endif</div>
                <div class="card-b">@include('partials.bars', ['series' => $chart, 'height' => 150])</div>
            </div>
        @endif
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-h"><x-icon n="users" /><h2>Quem trabalha hoje</h2><div class="r"><a class="btn btn-sm" href="{{ route('schedule.index') }}">Escala</a></div></div>
            <div class="list">
                @forelse ($shiftsToday as $s)
                    <div>
                        <span class="av-sm" style="background: {{ $s->employee->color }}">{{ $s->employee->initials }}</span>
                        <div class="grow"><div class="t1">{{ $s->employee->name }}</div><div class="t2">{{ $s->employee->position }}{{ $s->notes ? ' · '.$s->notes : '' }}</div></div>
                        <span class="tag {{ now()->format('H:i') >= substr($s->start_time, 0, 5) && now()->format('H:i') < substr($s->end_time, 0, 5) ? 'green' : '' }}">{{ $s->period }}</span>
                    </div>
                @empty
                    <div class="empty">Ninguém escalado hoje.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-h"><x-icon n="box" /><h2>Estoque baixo</h2>@if ($u->hasRole('producao'))<div class="r"><a class="btn btn-sm" href="{{ route('inventory.index') }}">Repor</a></div>@endif</div>
            <div class="list">
                @forelse ($lowStock as $p)
                    <div>
                        <div class="grow"><div class="t1">{{ $p->name }}</div><div class="hbar red mt-s"><i style="width: {{ min(100, $p->min_stock > 0 ? $p->stock / $p->min_stock * 100 : 0) }}%"></i></div></div>
                        <div class="right nowrap"><b class="neg">{{ qty($p->stock) }}</b> <small>/ {{ qty($p->min_stock) }} {{ $p->unit }}</small></div>
                    </div>
                @empty
                    <div class="empty"><x-icon n="check" />Estoque em dia.</div>
                @endforelse
            </div>
        </div>

        @if ($u->hasRole('caixa') && $docsDue->count())
            <div class="card">
                <div class="card-h"><x-icon n="file" /><h2>Documentos com prazo</h2></div>
                <div class="list">
                    @foreach ($docsDue as $d)
                        <div>
                            <div class="grow"><div class="t1">{{ $d->title }}</div><div class="t2">{{ $d->category }}{{ $d->amount ? ' · '.brl($d->amount) : '' }}</div></div>
                            <span class="tag {{ $d->due_date->isPast() ? 'red' : ($d->due_date->diffInDays(now(), true) <= 5 ? 'yel' : '') }}">{{ $d->due_date->format('d/m') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
