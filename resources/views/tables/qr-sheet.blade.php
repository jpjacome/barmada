<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('QR codes') }} — {{ $venue ? ($venue->business_name ?: $venue->name) : 'Barmada' }}</title>
    <style>
        body { font-family: "DejaVu Sans", Arial, sans-serif; margin: 1.5rem; color: #111; }
        h1 { text-align: center; font-size: 1.2rem; letter-spacing: 0.05em; text-transform: uppercase; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.2rem; max-width: 760px; margin: 1.5rem auto; }
        .card { border: 1px solid #ccc; border-radius: 12px; text-align: center; padding: 1rem; page-break-inside: avoid; }
        .card img { width: 100%; max-width: 280px; }
        .card h2 { margin: 0.4rem 0 0; font-size: 1.3rem; }
        .card p { margin: 0.2rem 0 0; color: #666; font-size: 0.85rem; }
        .print-btn { display: block; margin: 0 auto 1rem; padding: 0.6rem 1.6rem; border: 1px solid #333; background: #fff; border-radius: 8px; cursor: pointer; }
        @media print { .print-btn { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">{{ __('Print all') }}</button>
    <h1>{{ $venue ? ($venue->business_name ?: $venue->name) : 'Barmada' }}</h1>
    <div class="grid">
        @forelse($tables as $table)
            <div class="card">
                <img src="{{ route('tables.qr', $table->id) }}" alt="{{ __('QR Table :number', ['number' => $table->table_number]) }}">
                <h2>{{ __('Table :number', ['number' => $table->table_number]) }}</h2>
                <p>{{ __('Scan to order from your phone') }}</p>
            </div>
        @empty
            <p>{{ __('No tables yet.') }}</p>
        @endforelse
    </div>
</body>
</html>
