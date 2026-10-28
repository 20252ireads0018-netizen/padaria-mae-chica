@extends('layouts.app')
@section('title', 'Contabilidade')
@section('content')
@php
    use App\Models\FinancialEntry;
    $periods = ['hoje' => 'Hoje', 'semana' => 'Semana', 'mes' => 'Mês', 'mes_anterior' => 'Mês anterior', 'trimestre' => 'Trimestre', 'ano' => 'Ano', 'ano_anterior' => 'Ano anterior', '12m' => '12 meses'];
    $delta = function ($v, $inverse = false) {
        if ($v === null) return '<span class="muted">sem base</span>';
        $good = $inverse ? $v <= 0 : $v >= 0;
        return '<span class="'.($good ? 'pos' : 'neg').' b">'.($v >= 0 ? '▲ ' : '▼ ').qty(round(abs($v), 1)).'%</span> <span class="muted">vs. período anterior</span>';
    };
@endphp
<div class="page-head">
    <div><h1>Contabilidade</h1><div class="sub">{{ $start->format('d/m/Y') }} a {{ $end->format('d/m/Y') }} · {{ $days }} dia(s)</div></div>
    <div class="actions">
        <a class="btn" href="{{ route('accounting.export', request()->query()) }}"><x-icon n="download" /> Exportar CSV</a>
        <button class="btn" onclick="window.print()"><x-icon n="print" /> Imprimir</button>
    </div>
</div>

<div class="card mb no-print">
    <div class="card-b flex wrap">
        <div class="seg">
            @foreach ($periods as $k => $l)<a class="{{ $period === $k ? 'on' : '' }}" href="?periodo={{ $k }}">{{ $l }}</a>@endforeach
        </div>
        <form class="filters" style="margin-left:auto">
            <input type="hidden" name="periodo" value="personalizado">
            <input type="date" name="inicio" value="{{ $start->toDateString() }}">
            <span class="muted">até</span>
            <input type="date" name="fim" value="{{ $end->toDateString() }}">
            <button class="btn btn-y">Aplicar</button>
        </form>
    </div>
</div>

<div class="grid g4 mb">
    <div class="card kpi green"><div class="ic"><x-icon n="up" /></div><div class="l">Receitas</div><div class="v">{{ brl($income) }}</div><div class="s">{!! $delta($var['income']) !!}</div></div>
    <div class="card kpi red"><div class="ic"><x-icon n="down" /></div><div class="l">Despesas</div><div class="v">{{ brl($expense) }}</div><div class="s">{!! $delta($var['expense'], true) !!}</div></div>
    <div class="card kpi"><div class="ic"><x-icon n="wallet" /></div><div class="l">Lucro líquido</div><div class="v {{ $profit < 0 ? 'neg' : '' }}">{{ brl($profit) }}</div><div class="s">Margem {{ qty(round($margin, 1)) }}% · {!! $delta($var['profit']) !!}</div></div>
    <div class="card kpi blue"><div class="ic"><x-icon n="piggy" /></div><div class="l">Reserva de caixa</div><div class="v">{{ brl($reserve) }}</div><div class="s">{{ $closingsCount }} fechamento(s) · média/dia {{ brl($avgDay) }}</div></div>
</div>

<div class="grid g-main mb">
    <div class="card">
        <div class="card-h"><x-icon n="chart" /><h2>Receitas × despesas {{ $byMonth ? 'por mês' : 'por dia' }}</h2></div>
        <div class="card-b">@include('partials.bars', ['series' => $series, 'height' => 200])</div>
    </div>
    <div class="card">
        <div class="card-h"><x-icon n="cash" /><h2>Receitas por forma de pagamento</h2></div>
        <div class="card-b">
            @forelse ($byMethod as $m => $t)
                <div class="flex between"><span>{{ FinancialEntry::METHODS[$m] ?? $m }}</span><b>{{ brl($t) }} <small class="muted">{{ $income > 0 ? qty(round($t / $income * 100, 1)) : 0 }}%</small></b></div>
                <div class="hbar mb" style="margin-top:4px"><i style="width: {{ $income > 0 ? $t / $income * 100 : 0 }}%"></i></div>
            @empty
                <div class="empty">Sem receitas no período.</div>
            @endforelse
            @if (abs($difference) >= 0.01)
                <div class="alert-box {{ $difference < 0 ? 'err' : 'ok' }} mb0 mt">Diferenças de caixa no período: <b>{{ brl($difference) }}</b></div>
            @endif
        </div>
    </div>
</div>

<div class="grid g2 mb">
    @foreach (['receita' => 'Receitas por categoria', 'despesa' => 'Despesas por categoria'] as $type => $title)
        @php $rows = $byCategory->get($type, collect()); $tot = max(1, $rows->sum('t')); @endphp
        <div class="card">
            <div class="card-h"><x-icon n="{{ $type === 'receita' ? 'up' : 'down' }}" /><h2>{{ $title }}</h2></div>
            <table class="t">
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td><a href="?{{ http_build_query(array_merge(request()->query(), ['tipo' => $type, 'cat' => $r->category])) }}#lancamentos">{{ $r->category }}</a> <small class="muted">({{ $r->n }})</small></td>
                        <td style="width:35%"><div class="hbar {{ $type === 'despesa' ? 'red' : '' }}"><i style="width: {{ $r->t / $tot * 100 }}%"></i></div></td>
                        <td class="num b">{{ brl($r->t) }}</td>
                        <td class="num muted" style="width:60px">{{ qty(round($r->t / $tot * 100, 1)) }}%</td>
                    </tr>
                @empty
                    <tr><td><div class="empty">Sem lançamentos.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</div>

<div class="card mb">
    <div class="card-h"><x-icon n="list" /><h2>Demonstrativo mensal {{ $year }}</h2>
        <div class="r"><a class="btn btn-sm btn-ico" href="?{{ http_build_query(array_merge(request()->query(), ['ano' => $year - 1])) }}"><x-icon n="left" /></a><a class="btn btn-sm btn-ico" href="?{{ http_build_query(array_merge(request()->query(), ['ano' => $year + 1])) }}"><x-icon n="right" /></a></div>
    </div>
    <div class="table-wrap">
        <table class="t">
            <thead><tr><th></th>@foreach ($yearly as $y)<th class="num">{{ mb_substr(month_name($y['m']), 0, 3) }}</th>@endforeach<th class="num">Total</th></tr></thead>
            <tbody>
                <tr><td class="b">Receitas</td>@foreach ($yearly as $y)<td class="num">{{ $y['in'] ? number_format($y['in'], 0, ',', '.') : '—' }}</td>@endforeach<td class="num b pos">{{ brl($yearly->sum('in')) }}</td></tr>
                <tr><td class="b">Despesas</td>@foreach ($yearly as $y)<td class="num">{{ $y['out'] ? number_format($y['out'], 0, ',', '.') : '—' }}</td>@endforeach<td class="num b neg">{{ brl($yearly->sum('out')) }}</td></tr>
            </tbody>
            <tfoot><tr><td>Resultado</td>@foreach ($yearly as $y)<td class="num {{ $y['in'] - $y['out'] < 0 ? 'neg' : '' }}">{{ $y['in'] || $y['out'] ? number_format($y['in'] - $y['out'], 0, ',', '.') : '—' }}</td>@endforeach<td class="num">{{ brl($yearly->sum('in') - $yearly->sum('out')) }}</td></tr></tfoot>
        </table>
    </div>
</div>

<div class="card" id="lancamentos">
    <div class="card-h"><x-icon n="list" /><h2>Lançamentos</h2>
        <div class="r">
            @if (request('tipo') || request('cat'))<a class="btn btn-sm" href="?{{ http_build_query(\Illuminate\Support\Arr::except(request()->query(), ['tipo', 'cat', 'page'])) }}#lancamentos"><x-icon n="x" /> {{ request('cat') ?: ucfirst(request('tipo')) }}</a>@endif
        </div>
    </div>
    <div class="table-wrap">
        <table class="t">
            <thead><tr><th>Data</th><th>Categoria</th><th>Descrição</th><th>Forma</th><th>Origem</th><th class="num">Valor</th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr>
                    <td class="nowrap">{{ $e->date->format('d/m/Y') }}</td>
                    <td><span class="dot" style="background: {{ $e->type === 'receita' ? 'var(--yel)' : 'var(--red)' }}"></span> {{ $e->category }}</td>
                    <td>{{ $e->description }}</td>
                    <td>{{ FinancialEntry::METHODS[$e->payment_method] ?? '' }}</td>
                    <td>{!! $e->isAutomatic() ? '<span class="tag blue">auto</span>' : '<span class="tag">manual</span>' !!}</td>
                    <td class="num b {{ $e->type === 'receita' ? 'pos' : 'neg' }}">{{ $e->type === 'receita' ? '+' : '−' }} {{ brl($e->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty">Sem lançamentos.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $entries->fragment('lancamentos')->links() }}
</div>
@endsection
