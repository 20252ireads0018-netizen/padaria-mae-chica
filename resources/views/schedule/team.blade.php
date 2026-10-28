@extends('layouts.app')
@section('title', 'Equipe')
@section('content')
@php use App\Models\ShiftTemplate; @endphp
<div class="page-head">
    <div><h1>Equipe & escala padrão</h1><div class="sub">Defina os horários fixos de cada pessoa por dia da semana. Use “Gerar mês” na escala para aplicar.</div></div>
    <div class="actions">
        <a class="btn" href="{{ route('schedule.index') }}"><x-icon n="cal" /> Calendário</a>
        <button class="btn btn-p" data-open="dlg-new"><x-icon n="plus" /> Funcionário</button>
    </div>
</div>

<div class="grid g-side">
    <div class="stack">
        @foreach ($employees as $e)
            @php $tpl = $e->templates->keyBy('weekday'); @endphp
            <form class="card" method="POST" action="{{ route('schedule.employees.update', $e) }}" style="{{ $e->active ? '' : 'opacity:.6' }}">
                @csrf @method('PUT')
                <div class="card-h">
                    <span class="av-sm" style="background: {{ $e->color }}">{{ $e->initials }}</span>
                    <div><h2>{{ $e->name }}</h2><small class="muted">{{ $e->position }} · {{ $tpl->sum(fn ($t) => (strtotime($t->end_time) - strtotime($t->start_time) + (strtotime($t->end_time) <= strtotime($t->start_time) ? 86400 : 0)) / 3600) }}h/semana</small></div>
                    <div class="r">
                        <button class="btn btn-sm btn-y"><x-icon n="check" /> Salvar</button>
                    </div>
                </div>
                <div class="card-b">
                    <div class="row">
                        <label class="f"><span>Nome</span><input type="text" name="name" value="{{ $e->name }}" required></label>
                        <label class="f"><span>Função</span><input type="text" name="position" value="{{ $e->position }}" required></label>
                        <label class="f"><span>Telefone</span><input type="tel" name="phone" value="{{ $e->phone }}"></label>
                        <label class="f auto"><span>Cor</span><input type="color" name="color" value="{{ $e->color }}"></label>
                        <label class="check auto" style="padding-top:18px"><input type="checkbox" name="active" value="1" @checked($e->active)> Ativo</label>
                    </div>
                    <div class="table-wrap">
                        <table class="t">
                            <thead><tr>@foreach (ShiftTemplate::WEEKDAYS as $i => $w)<th class="{{ in_array($i, [0, 6]) ? 'neg' : '' }}">{{ $w }}</th>@endforeach</tr></thead>
                            <tbody><tr>
                                @foreach (ShiftTemplate::WEEKDAYS as $i => $w)
                                    @php $t = $tpl->get($i); @endphp
                                    <td style="min-width:96px;vertical-align:top">
                                        <label class="check" style="font-size:.78rem"><input type="checkbox" name="tpl[{{ $i }}][on]" value="1" @checked($t)> Trabalha</label>
                                        <input type="time" name="tpl[{{ $i }}][start]" value="{{ $t ? substr($t->start_time, 0, 5) : '06:00' }}" style="margin-top:4px;padding:4px 6px">
                                        <input type="time" name="tpl[{{ $i }}][end]" value="{{ $t ? substr($t->end_time, 0, 5) : '14:00' }}" style="margin-top:4px;padding:4px 6px">
                                    </td>
                                @endforeach
                            </tr></tbody>
                        </table>
                    </div>
                </div>
            </form>
        @endforeach
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-h"><x-icon n="cal" /><h2>Feriados</h2></div>
            <form class="card-b" method="POST" action="{{ route('schedule.holidays.store') }}">
                @csrf
                <div class="row"><label class="f"><span>Data</span><input type="date" name="date" required></label><label class="f"><span>Nome</span><input type="text" name="name" placeholder="Ex.: Aniversário da cidade" required></label></div>
                <button class="btn btn-sm btn-p"><x-icon n="plus" /> Adicionar feriado</button>
            </form>
            <div class="list" style="max-height:420px;overflow:auto">
                @foreach ($holidays as $h)
                    <div>
                        <div class="when {{ $h->date->isPast() ? '' : 'yel' }}"><b>{{ $h->date->format('d/m') }}</b><small>{{ weekday_name($h->date->dayOfWeek, true) }}</small></div>
                        <div class="grow t1">{{ $h->name }}</div>
                        <form class="inline" method="POST" action="{{ route('schedule.holidays.destroy', $h) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost btn-danger btn-ico"><x-icon n="x" /></button></form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<dialog id="dlg-new">
    <div class="card-h"><h2>Novo funcionário</h2><div class="r"><button class="btn btn-ghost btn-ico" data-close><x-icon n="x" /></button></div></div>
    <form class="card-b" method="POST" action="{{ route('schedule.employees.store') }}">
        @csrf
        <label class="f"><span>Nome *</span><input type="text" name="name" required></label>
        <div class="row">
            <label class="f"><span>Função *</span><input type="text" name="position" placeholder="Padeiro, Caixa, Atendente..." required></label>
            <label class="f"><span>Telefone</span><input type="tel" name="phone"></label>
            <label class="f auto"><span>Cor</span><input type="color" name="color" value="#C8102E"></label>
        </div>
        <p class="muted" style="font-size:.8rem">Depois de cadastrar, defina os horários fixos semanais no cartão do funcionário.</p>
        <button class="btn btn-p w100" style="justify-content:center"><x-icon n="check" /> Cadastrar</button>
    </form>
</dialog>
@endsection
