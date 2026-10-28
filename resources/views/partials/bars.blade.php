{{-- Gráfico de barras receita x despesa. $series = [[label, in, out], ...] --}}
@php $max = max(1, collect($series)->max(fn ($s) => max($s['in'], $s['out']))); $step = max(1, (int) ceil(count($series) / 16)); @endphp
<div class="bars" style="height: {{ $height ?? 170 }}px">
    @foreach ($series as $i => $s)
        <div class="col" title="{{ $s['label'] }} · Receitas {{ brl($s['in']) }} · Despesas {{ brl($s['out']) }} · Saldo {{ brl($s['in'] - $s['out']) }}">
            <div class="pair">
                <div class="bi" style="height: {{ round($s['in'] / $max * 100, 1) }}%"></div>
                <div class="bo" style="height: {{ round($s['out'] / $max * 100, 1) }}%"></div>
            </div>
            <div class="lb">{{ $i % $step === 0 ? $s['label'] : '' }}</div>
        </div>
    @endforeach
</div>
<div class="legend mt-s"><span><i style="background:var(--yel)"></i>Receitas</span><span><i style="background:var(--red)"></i>Despesas</span></div>
