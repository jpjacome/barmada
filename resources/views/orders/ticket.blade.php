<!DOCTYPE html>
@php
    // Receipt paper width: 80mm printers by default, or ?w=58 for 58mm rolls.
    $isNarrow = request()->query('w') == '58';
    $paperMm = $isNarrow ? '58mm' : '80mm';
    $paperPx = $isNarrow ? '219px' : '302px';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Ticket') }} — {{ __('Order #:id', ['id' => $order->id]) }}</title>
    <style>
        @page { size: {{ $paperMm }} auto; margin: 2mm; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; max-width: {{ $paperPx }}; margin: 1.5rem auto; padding: 0 0.5rem; color: #111; word-wrap: break-word; overflow-wrap: break-word; }
        h1 { font-size: 1.1rem; text-align: center; margin: 0; }
        .meta { text-align: center; color: #555; font-size: 0.8rem; margin: 0.3rem 0 1rem; }
        ul { list-style: none; padding: 0; margin: 0; font-size: 0.95rem; }
        li { padding: 0.3rem 0; border-bottom: 1px dotted #ccc; overflow-wrap: break-word; }
        .note { margin-top: 0.8rem; font-style: italic; border: 1px dashed #999; border-radius: 6px; padding: 0.5rem; font-size: 0.85rem; overflow-wrap: break-word; }
        .print-btn { display: block; margin: 1.2rem auto 0; padding: 0.6rem 1.6rem; border: 1px solid #333; background: #fff; border-radius: 8px; cursor: pointer; }
        .paper-switch { display: block; text-align: center; margin-top: 0.6rem; font-size: 0.8rem; }
        @media print { .print-btn, .paper-switch { display: none; } body { margin: 0 auto; padding: 0; } }
    </style>
</head>
<body>
    <h1>{{ __('Table :number', ['number' => $order->table->table_number ?? $order->table_id]) }}</h1>
    <p class="meta">{{ __('Order #:id', ['id' => $order->id]) }} · {{ \App\Support\VenueClock::format($order->editor, $order->created_at, 'H:i') }}</p>
    <ul>
        @foreach($lines as $name => $qty)
            <li><strong>{{ $qty }}×</strong> {{ $name }}</li>
        @endforeach
    </ul>
    @if($order->note)
        <div class="note">{{ $order->note }}</div>
    @endif
    <button class="print-btn" onclick="window.print()">{{ __('Print') }}</button>
    <a class="paper-switch" href="?{{ $isNarrow ? '' : 'w=58' }}">
        {{ $isNarrow ? __('Switch to 80mm paper') : __('Switch to 58mm paper') }}
    </a>
</body>
</html>
