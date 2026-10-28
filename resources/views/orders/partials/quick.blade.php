{{-- Avança o status da encomenda em um clique --}}
@php
    $next = ['pendente' => 'producao', 'producao' => 'pronta', 'pronta' => 'entregue'][$o->status] ?? null;
    $labels = ['producao' => 'Produzir', 'pronta' => 'Pronta', 'entregue' => 'Entregar'];
@endphp
@if ($next)
    <form class="inline" method="POST" action="{{ route('orders.status', $o) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="status" value="{{ $next }}">
        @if ($next === 'entregue' && ! $o->paid && auth()->user()->hasRole('caixa'))
            <input type="hidden" name="paid" value="1">
            <button class="btn btn-sm btn-g" title="Entregar e receber {{ brl($o->balance) }}"><x-icon n="check" /> Entregar + receber</button>
        @else
            <button class="btn btn-sm {{ $next === 'entregue' ? 'btn-g' : 'btn-y' }}"><x-icon n="right" /> {{ $labels[$next] }}</button>
        @endif
    </form>
@endif
