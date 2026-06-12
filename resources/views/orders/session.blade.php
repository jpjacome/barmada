@extends('layouts.app')

@section('content')
<link href="{{ asset('css/create-order.css') }}" rel="stylesheet">
<div class="container page-container">
    <div class="content-card content-card-body" style="max-width: 560px; margin: 2rem auto;">
        <h1 class="page-title" style="text-align:center;">{{ __('Table') }} {{ $table->table_number ?? $table->id }}</h1>

        @if($orders->isEmpty())
            <p style="text-align:center;color:var(--color-accents);">{{ __('No orders yet this session.') }}</p>
        @else
            @foreach($orders as $order)
                @php
                    $grouped = [];
                    foreach ($order->items as $item) {
                        $name = $item->product->name ?? '—';
                        $grouped[$name] = ($grouped[$name] ?? ['qty' => 0, 'amount' => 0]);
                        $grouped[$name]['qty'] += 1;
                        $grouped[$name]['amount'] += $item->price;
                    }
                    $orderTotal = $order->items->sum('price');
                @endphp
                <div class="session-order">
                    <div class="session-order-header">
                        <strong>{{ __('Order') }} #{{ $order->id }}</strong>
                        <span class="session-order-time">{{ $order->created_at->format('H:i') }}</span>
                        <span class="session-order-status status-{{ $order->status }}">
                            {{ $order->status === 'pending' ? __('Being prepared') : __('Delivered') }}
                        </span>
                    </div>
                    <ul class="session-order-items">
                        @foreach($grouped as $name => $line)
                            <li><span>{{ $line['qty'] }} × {{ $name }}</span><span>{{ $currency }}{{ number_format($line['amount'], 2) }}</span></li>
                        @endforeach
                    </ul>
                    @if($order->note)
                        <div class="session-order-note"><i class="bi bi-chat-left-text"></i> {{ $order->note }}</div>
                    @endif
                    <div class="session-order-total">{{ __('Total') }}: {{ $currency }}{{ number_format($orderTotal, 2) }}</div>
                </div>
            @endforeach

            <div class="session-summary">
                <div><span>{{ __('Table total') }}</span><strong>{{ $currency }}{{ number_format($total, 2) }}</strong></div>
                <div><span>{{ __('Paid') }}</span><strong>{{ $currency }}{{ number_format($paid, 2) }}</strong></div>
                <div><span>{{ __('Remaining') }}</span><strong>{{ $currency }}{{ number_format($left, 2) }}</strong></div>
            </div>
        @endif

        <div class="session-actions">
            <form method="POST" action="{{ route('order.service', ['unique_token' => $unique_token]) }}">
                <input type="hidden" name="type" value="bill">
                <button type="submit" class="submit-button" {{ in_array('bill', $openRequests) ? 'disabled' : '' }}>
                    <i class="bi bi-receipt"></i>
                    {{ in_array('bill', $openRequests) ? __('Bill requested — on its way') : __('Request the bill') }}
                </button>
            </form>
            <form method="POST" action="{{ route('order.service', ['unique_token' => $unique_token]) }}">
                <input type="hidden" name="type" value="waiter">
                <button type="submit" class="submit-button" {{ in_array('waiter', $openRequests) ? 'disabled' : '' }}>
                    <i class="bi bi-hand-index-thumb"></i>
                    {{ in_array('waiter', $openRequests) ? __('Waiter called — on the way') : __('Call a waiter') }}
                </button>
            </form>
        </div>

        <p style="text-align:center;margin-top:1.25rem;">
            <a href="{{ route('order.redirect', ['unique_token' => $unique_token]) }}" class="page-link">
                <i class="bi bi-plus-circle"></i> {{ __('Order more') }}
            </a>
        </p>
    </div>
</div>
<script>
    // Light auto-refresh so statuses and payment progress stay current.
    setTimeout(function () { window.location.reload(); }, 20000);
</script>
@endsection
