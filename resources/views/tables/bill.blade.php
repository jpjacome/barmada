<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bill — Table {{ $table->table_number ?? $table->id }}</title>
    <style>
        body { font-family: "DejaVu Sans", Arial, sans-serif; max-width: 420px; margin: 1.5rem auto; padding: 0 1rem; color: #111; }
        h1 { font-size: 1.1rem; text-align: center; margin: 0; letter-spacing: 0.05em; text-transform: uppercase; }
        .meta { text-align: center; color: #555; font-size: 0.85rem; margin: 0.3rem 0 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        td { padding: 0.25rem 0; }
        td.qty { width: 2.5rem; }
        td.amount { text-align: right; white-space: nowrap; }
        .totals { border-top: 1px dashed #999; margin-top: 0.6rem; padding-top: 0.6rem; }
        .totals div { display: flex; justify-content: space-between; padding: 0.1rem 0; }
        .totals .due { font-weight: bold; font-size: 1.05rem; }
        .invoice { border-top: 1px dashed #999; margin-top: 0.8rem; padding-top: 0.6rem; font-size: 0.85rem; }
        .invoice h2 { font-size: 0.9rem; margin: 0 0 0.3rem; }
        .footer { text-align: center; color: #777; font-size: 0.75rem; margin-top: 1.2rem; }
        .print-btn { display: block; margin: 1.2rem auto 0; padding: 0.6rem 1.6rem; border: 1px solid #333; background: #fff; border-radius: 8px; cursor: pointer; font-size: 0.95rem; }
        @media print { .print-btn { display: none; } body { margin: 0 auto; } }
    </style>
</head>
<body>
    <h1>{{ $venue ? ($venue->business_name ?: $venue->name) : 'Barmada' }}</h1>
    <p class="meta">
        Table {{ $table->table_number ?? $table->id }}
        @if($session) · session #{{ $session->session_number }} @endif
        · {{ now()->format('Y-m-d H:i') }}
    </p>

    @if(empty($lines))
        <p style="text-align:center;color:#777;">No orders this session.</p>
    @else
        <table>
            @foreach($lines as $name => $line)
                <tr>
                    <td class="qty">{{ $line['qty'] }}×</td>
                    <td>{{ $name }}</td>
                    <td class="amount">{{ $currency }}{{ number_format($line['amount'], 2) }}</td>
                </tr>
            @endforeach
        </table>
        <div class="totals">
            <div><span>Total</span><span>{{ $currency }}{{ number_format($total, 2) }}</span></div>
            <div><span>Paid</span><span>{{ $currency }}{{ number_format($paid, 2) }}</span></div>
            <div class="due"><span>Due</span><span>{{ $currency }}{{ number_format($left, 2) }}</span></div>
        </div>
    @endif

    @if($invoice)
        <div class="invoice">
            <h2>Invoice details</h2>
            <div>{{ $invoice->name }}</div>
            <div>Tax ID: {{ $invoice->tax_id }}</div>
            @if($invoice->address)<div>{{ $invoice->address }}</div>@endif
            @if($invoice->email)<div>{{ $invoice->email }}</div>@endif
            @if($invoice->phone)<div>{{ $invoice->phone }}</div>@endif
        </div>
    @endif

    <p class="footer">Not a fiscal receipt — internal bill summary.</p>
    <button class="print-btn" onclick="window.print()">Print</button>
</body>
</html>
