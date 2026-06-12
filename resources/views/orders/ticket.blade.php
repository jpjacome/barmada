<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket — Order #{{ $order->id }}</title>
    <style>
        body { font-family: "DejaVu Sans", Arial, sans-serif; max-width: 320px; margin: 1.5rem auto; padding: 0 1rem; color: #111; }
        h1 { font-size: 1.4rem; text-align: center; margin: 0; }
        .meta { text-align: center; color: #555; font-size: 0.9rem; margin: 0.3rem 0 1rem; }
        ul { list-style: none; padding: 0; margin: 0; font-size: 1.15rem; }
        li { padding: 0.3rem 0; border-bottom: 1px dotted #ccc; }
        .note { margin-top: 0.8rem; font-style: italic; border: 1px dashed #999; border-radius: 6px; padding: 0.5rem; }
        .print-btn { display: block; margin: 1.2rem auto 0; padding: 0.6rem 1.6rem; border: 1px solid #333; background: #fff; border-radius: 8px; cursor: pointer; }
        @media print { .print-btn { display: none; } body { margin: 0 auto; } }
    </style>
</head>
<body>
    <h1>Table {{ $order->table->table_number ?? $order->table_id }}</h1>
    <p class="meta">Order #{{ $order->id }} · {{ $order->created_at->format('H:i') }}</p>
    <ul>
        @foreach($lines as $name => $qty)
            <li><strong>{{ $qty }}×</strong> {{ $name }}</li>
        @endforeach
    </ul>
    @if($order->note)
        <div class="note">{{ $order->note }}</div>
    @endif
    <button class="print-btn" onclick="window.print()">Print</button>
</body>
</html>
