@extends('layouts.app')
@section('title', 'Escolas')
@section('content')
@php use App\Models\School; use App\Models\SchoolOrder; $canEdit = auth()->user()->hasRole('caixa'); @endphp
<div class="page-head">
    <div><h1>Pedidos das escolas</h1><div class="sub">Semana de {{ $start->format('d/m') }} a {{ $end->format('d/m/Y') }} · {{ $orders->count() }} entregas · {{ brl($orders->where('status', '!=', 'cancelada')->sum('total')) }}</div></div>
    <div class="actions">
        <a class="btn btn-ico" href="?semana={{ $start->copy()->subWeek()->toDateString() }}" title="Semana anterior"><x-icon n="left" /></a>
        <a class="btn" href="?semana={{ now()->toDateString() }}">Esta semana</a>
        <a class="btn btn-ico" href="?semana={{ $start->copy()->addWeek()->toDateString() }}" title="Próxima semana"><x-icon n="right" /></a>
        @if ($canEdit)
            <form class="inline" method="POST" action="{{ route('schools.repeat') }}" data-confirm="Copiar os pedidos desta semana para a próxima?">@csrf<input type="hidden" name="semana" value="{{ $start->toDateString() }}"><button class="btn"><x-icon n="repeat" /> Repetir na próxima semana</button></form>
            <a class="btn btn-p" href="{{ route('schools.orders.create') }}"><x-icon n="plus" /> Novo pedido</a>
        @endif
    </div>
</div>

<div class="grid g-side">
    <div class="stack">
        @for ($d = $start->copy(); $d->lte($end); $d->addDay())
            @php $key = $d->toDateString(); $dayOrders = $orders->filter(fn ($o) => $o->delivery_date->toDateString() === $key); @endphp
            @continue($dayOrders->isEmpty() && $d->isWeekend())
            <div class="card">
                <div class="card-h">
                    <div class="when {{ $d->isToday() ? 'yel' : '' }}"><b>{{ $d->format('d') }}</b><small>{{ weekday_name($d->dayOfWeek, true) }}</small></div>
                    <div><h2>{{ weekday_name($d->dayOfWeek) }}</h2>
                        <small class="muted">@foreach ($byDay->get($key, collect()) as $desc => $q){{ qty($q) }} {{ $desc }}@if (! $loop->last) · @endif @endforeach</small>
                    </div>
                    @if ($canEdit)<div class="r"><a class="btn btn-sm" href="{{ route('schools.orders.create', ['data' => $key]) }}"><x-icon n="plus" /></a></div>@endif
                </div>
                @if ($dayOrders->isEmpty())
                    <div class="empty" style="padding:12px">Sem entregas.</div>
                @else
                    <table class="t">
                        <tbody>
                        @foreach ($dayOrders as $o)
                            <tr>
                                <td style="width:90px"><span class="tag {{ $o->shift === 'manha' ? 'yel' : 'orange' }}">{{ SchoolOrder::SHIFTS[$o->shift] }}</span></td>
                                <td><a class="b" href="{{ route('schools.show', $o->school) }}">{{ $o->school->name }}</a><br><small class="muted">{{ $o->items->map(fn ($i) => qty($i->quantity).' '.$i->description)->join(' · ') }}</small></td>
                                <td class="num b">{{ brl($o->total) }}<br>@if ($o->paid)<span class="tag green">Pago</span>@endif</td>
                                <td>@include('partials.status', ['s' => $o->status])</td>
                                <td class="act">
                                    @php $next = ['pendente' => 'producao', 'producao' => 'entregue'][$o->status] ?? null; @endphp
                                    @if ($next)
                                        <form class="inline" method="POST" action="{{ route('schools.orders.status', $o) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $next }}"><button class="btn btn-sm {{ $next === 'entregue' ? 'btn-g' : 'btn-y' }}">{{ $next === 'entregue' ? 'Entregue' : 'Produzir' }}</button></form>
                                    @endif
                                    @if ($canEdit)
                                        <a class="btn btn-sm btn-ghost btn-ico" href="{{ route('schools.orders.edit', $o) }}"><x-icon n="edit" /></a>
                                        <form class="inline" method="POST" action="{{ route('schools.orders.destroy', $o) }}" data-confirm="Excluir este pedido?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost btn-danger btn-ico"><x-icon n="trash" /></button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endfor
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-h"><x-icon n="school" /><h2>Escolas atendidas</h2>@if ($canEdit)<div class="r"><button class="btn btn-sm btn-y" data-open="dlg-school"><x-icon n="plus" /> Escola</button></div>@endif</div>
            <div class="list">
                @forelse ($schools as $s)
                    <a href="{{ route('schools.show', $s) }}">
                        <div class="grow"><div class="t1">{{ $s->name }} @unless ($s->active)<span class="tag">inativa</span>@endunless</div><div class="t2">{{ $s->contact_name }} · {{ School::BILLING[$s->billing] }}</div></div>
                        @if ($s->open_amount > 0)<div class="right"><small class="muted">a faturar</small><div class="b neg">{{ brl($s->open_amount) }}</div></div>@endif
                    </a>
                @empty
                    <div class="empty">Nenhuma escola cadastrada.</div>
                @endforelse
            </div>
        </div>
        <div class="card"><div class="card-b muted" style="font-size:.82rem">
            <b>Dica:</b> ao criar um pedido, use “repetir até” para lançar todos os dias úteis de uma vez; ou copie a semana inteira com <b>Repetir na próxima semana</b>. Ao marcar o pedido como pago (ou faturar o mês na página da escola), a receita entra no caixa automaticamente.
        </div></div>
    </div>
</div>

@if ($canEdit)
<dialog id="dlg-school">
    <div class="card-h"><h2>Nova escola</h2><div class="r"><button class="btn btn-ghost btn-ico" data-close><x-icon n="x" /></button></div></div>
    <form class="card-b" method="POST" action="{{ route('schools.store') }}">
        @csrf
        @include('schools.partials.fields', ['s' => new School(['billing' => 'mensal'])])
        <button class="btn btn-p w100" style="justify-content:center"><x-icon n="check" /> Cadastrar</button>
    </form>
</dialog>
@endif
@endsection
