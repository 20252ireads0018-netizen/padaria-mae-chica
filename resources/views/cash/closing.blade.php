@extends('layouts.app')
@section('title', 'Fechamento de caixa')
@section('content')
@php use App\Models\FinancialEntry; $d = \Illuminate\Support\Carbon::parse($date); @endphp
<div class="page-head">
    <div>
        <h1>Fechamento de caixa</h1>
        <div class="sub">{{ ucfirst($d->translatedFormat('l, d/m/Y')) }} — valores recebidos automaticamente de {{ $s['count'] }} lançamentos</div>
    </div>
    <div class="actions"><a class="btn" href="{{ route('cash.index', ['data' => $date]) }}"><x-icon n="left" /> Voltar ao caixa</a></div>
</div>

<form method="POST" action="{{ route('cash.closing.store') }}" data-confirm="Confirmar o fechamento do caixa deste dia?">
    @csrf
    <input type="hidden" name="date" value="{{ $date }}">
    <div class="grid g-main">
        <div class="stack">
            <div class="flow">
                <div class="res"><div class="l">1 · Resultado (ganhou − gastou)</div><div class="v">{{ brl($s['result']) }}</div></div>
                <div class="rsv"><div class="l">2 · Deduz p/ fluxo de caixa</div><div class="v">− {{ brl($s['reserve']) }}</div></div>
                <div class="avl"><div class="l">3 · Restante disponível</div><div class="v">{{ brl($s['available']) }}</div></div>
            </div>

            <div class="grid g2">
                <div class="card">
                    <div class="card-h"><x-icon n="up" /><h2>Entradas</h2><div class="r b pos">{{ brl($s['income']) }}</div></div>
                    <div class="card-b ledger">
                        @foreach (FinancialEntry::METHODS as $k => $m)
                            <div class="ln"><span class="k">{{ $m }}</span><span>{{ brl($s['in'][$k]) }}</span></div>
                        @endforeach
                    </div>
                </div>
                <div class="card">
                    <div class="card-h"><x-icon n="down" /><h2>Saídas</h2><div class="r b neg">{{ brl($s['expense']) }}</div></div>
                    <div class="card-b ledger">
                        @foreach (FinancialEntry::METHODS as $k => $m)
                            <div class="ln"><span class="k">{{ $m }}</span><span>{{ brl($s['out'][$k]) }}</span></div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-h"><x-icon n="list" /><h2>Lançamentos considerados</h2></div>
                <div class="table-wrap" style="max-height:320px;overflow:auto">
                    <table class="t">
                        <tbody>
                        @forelse ($entries as $e)
                            <tr><td>{{ $e->category }}</td><td class="muted">{{ $e->description }}</td><td>{{ FinancialEntry::METHODS[$e->payment_method] ?? '' }}</td><td class="num b {{ $e->type === 'receita' ? 'pos' : 'neg' }}">{{ $e->type === 'receita' ? '+' : '−' }} {{ brl($e->amount) }}</td></tr>
                        @empty
                            <tr><td><div class="empty">Sem lançamentos — lance as vendas e despesas antes de fechar.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-h"><x-icon n="wallet" /><h2>Conferência da gaveta</h2></div>
                <div class="card-b ledger">
                    <label class="f"><span>Troco inicial (fundo de caixa)</span><input type="text" name="opening_balance" value="{{ number_format($s['opening'], 2, ',', '.') }}" inputmode="decimal"></label>
                    <div class="ln sub"><span class="k">+ Recebido em dinheiro</span><span>{{ brl($s['in']['dinheiro']) }}</span></div>
                    <div class="ln sub"><span class="k">− Pago em dinheiro</span><span>{{ brl($s['out']['dinheiro']) }}</span></div>
                    <div class="ln sum"><span>Esperado na gaveta</span><span>{{ brl($s['drawer']) }}</span></div>
                    <label class="f mt"><span>Dinheiro contado na gaveta</span><input type="text" name="counted_cash" inputmode="decimal" placeholder="0,00" data-counted data-expected="{{ $s['drawer'] }}"></label>
                    <div class="ln"><span class="k">Diferença (sobra / falta)</span><span data-diff>—</span></div>
                    <p class="muted" style="font-size:.76rem">Se alterar o troco inicial, o esperado será recalculado ao salvar.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-h"><x-icon n="piggy" /><h2>Fluxo de caixa</h2></div>
                <div class="card-b ledger">
                    <div class="ln"><span class="k">Regra</span><span>{{ qty($s['percent']) }}% · mín. {{ brl($s['minimum']) }}</span></div>
                    <div class="ln"><span class="k">Separar hoje</span><span class="b neg">{{ brl($s['reserve']) }}</span></div>
                    <div class="ln"><span class="k">Fica disponível</span><span class="b pos">{{ brl($s['available']) }}</span></div>
                    @if (auth()->user()->hasRole())<a class="muted" style="font-size:.76rem" href="{{ route('users.index') }}#ajustes">Alterar regra da reserva</a>@endif
                </div>
            </div>
            <label class="f"><span>Observações</span><textarea name="notes" placeholder="Ocorrências do dia, sangrias, etc."></textarea></label>
            <button class="btn btn-p btn-lg w100" style="justify-content:center"><x-icon n="lock" /> Confirmar fechamento</button>
        </div>
    </div>
</form>
@endsection
