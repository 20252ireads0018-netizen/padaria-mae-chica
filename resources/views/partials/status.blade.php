@php
    $map = [
        'pendente' => 'yel', 'producao' => 'blue', 'pronta' => 'green', 'entregue' => 'dark', 'cancelada' => '',
        'a_fazer' => 'red', 'enviado' => 'blue', 'recebido' => 'green', 'cancelado' => '',
    ];
    $labels = \App\Models\Order::STATUSES + \App\Models\PurchaseOrder::STATUSES;
@endphp
<span class="tag {{ $map[$s] ?? '' }}">{{ $labels[$s] ?? $s }}</span>
