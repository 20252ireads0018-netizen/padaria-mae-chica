@extends('layouts.app')
@section('title', 'Documentos')
@section('content')
@php use App\Models\Document; @endphp
<div class="page-head">
    <div><h1>Documentos</h1><div class="sub">Notas fiscais, boletos, contratos, alvarás e licenças — com controle de vencimento.</div></div>
</div>

@if ($overdue)<div class="alert-box err"><x-icon n="alert" /> {{ $overdue }} documento(s) com prazo vencido.</div>@endif
@if ($dueSoon)<div class="alert-box warn"><x-icon n="clock" /> {{ $dueSoon }} documento(s) vencem nos próximos 15 dias.</div>@endif

<div class="grid g-side">
    <div class="card">
        <div class="card-h wrap">
            <div class="seg">
                <a class="{{ request('ver') ? '' : 'on' }}" href="?">Todos</a>
                <a class="{{ request('ver') === 'vencendo' ? 'on' : '' }}" href="?ver=vencendo">Vencendo / vencidos</a>
            </div>
            <form class="r filters">
                <select name="categoria" data-autosubmit><option value="">Categoria</option>@foreach (Document::CATEGORIES as $c)<option @selected(request('categoria') === $c)>{{ $c }}</option>@endforeach</select>
                <input type="search" name="busca" value="{{ request('busca') }}" placeholder="Buscar">
            </form>
        </div>
        <div class="table-wrap">
            <table class="t">
                <thead><tr><th>Documento</th><th>Categoria</th><th>Vencimento</th><th class="num">Valor</th><th>Arquivo</th><th></th></tr></thead>
                <tbody>
                @forelse ($documents as $d)
                    @php $late = $d->due_date && $d->due_date->lt(today()); $soon = $d->due_date && ! $late && $d->due_date->lte(today()->addDays(7)); @endphp
                    <tr class="{{ $late ? 'row-red' : ($soon ? 'row-yel' : '') }}">
                        <td><b>{{ $d->title }}</b>@if ($d->notes)<br><small class="muted">{{ \Illuminate\Support\Str::limit($d->notes, 80) }}</small>@endif</td>
                        <td><span class="tag">{{ $d->category }}</span></td>
                        <td class="nowrap">@if ($d->due_date){{ $d->due_date->format('d/m/Y') }}<br><small class="{{ $late ? 'neg b' : 'muted' }}">{{ $d->due_date->isToday() ? 'hoje' : $d->due_date->diffForHumans(today()) }}</small>@else — @endif</td>
                        <td class="num">{{ $d->amount ? brl($d->amount) : '—' }}</td>
                        <td>@if ($d->file_path)<a class="btn btn-sm" href="{{ route('documents.download', $d) }}"><x-icon n="download" /> Baixar</a>@else<small class="muted">sem arquivo</small>@endif</td>
                        <td class="act"><form class="inline" method="POST" action="{{ route('documents.destroy', $d) }}" data-confirm="Excluir este documento?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost btn-danger btn-ico"><x-icon n="trash" /></button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty"><x-icon n="file" />Nenhum documento.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $documents->links() }}
    </div>
    <div class="card">
        <div class="card-h"><x-icon n="plus" /><h2>Arquivar documento</h2></div>
        <form class="card-b" method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
            @csrf
            <label class="f"><span>Título *</span><input type="text" name="title" required></label>
            <label class="f"><span>Categoria</span><select name="category">@foreach (Document::CATEGORIES as $c)<option>{{ $c }}</option>@endforeach</select></label>
            <div class="row">
                <label class="f"><span>Vencimento</span><input type="date" name="due_date"></label>
                <label class="f"><span>Valor (R$)</span><input type="text" name="amount" inputmode="decimal"></label>
            </div>
            <label class="f"><span>Arquivo (PDF, imagem, planilha — até 10 MB)</span><input type="file" name="file"></label>
            <label class="f"><span>Observações</span><textarea name="notes"></textarea></label>
            <button class="btn btn-p w100" style="justify-content:center"><x-icon n="check" /> Salvar</button>
        </form>
    </div>
</div>
@endsection
