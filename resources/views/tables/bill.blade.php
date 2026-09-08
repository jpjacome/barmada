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
    <title>{{ __('Bill') }} — {{ __('Table :number', ['number' => $table->table_number ?? $table->id]) }}</title>
    <style>
        @page { size: {{ $paperMm }} auto; margin: 2mm; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; max-width: {{ $paperPx }}; margin: 1.5rem auto; padding: 0 0.5rem; color: #111; word-wrap: break-word; overflow-wrap: break-word; }
        h1 { font-size: 1rem; text-align: center; margin: 0; letter-spacing: 0.05em; text-transform: uppercase; }
        .meta { text-align: center; color: #555; font-size: 0.75rem; margin: 0.3rem 0 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        td { padding: 0.25rem 0; overflow-wrap: break-word; }
        td.qty { width: 2rem; }
        td.amount { text-align: right; white-space: nowrap; padding-left: 0.3rem; }
        .totals { border-top: 1px dashed #999; margin-top: 0.6rem; padding-top: 0.6rem; }
        .totals div { display: flex; justify-content: space-between; padding: 0.1rem 0; }
        .totals .due { font-weight: bold; font-size: 0.95rem; }
        .invoice { border-top: 1px dashed #999; margin-top: 0.8rem; padding-top: 0.6rem; font-size: 0.75rem; overflow-wrap: break-word; }
        .invoice h2 { font-size: 0.8rem; margin: 0 0 0.3rem; }
        .footer { text-align: center; color: #777; font-size: 0.7rem; margin-top: 1.2rem; }
        .print-btn { display: block; margin: 1.2rem auto 0; padding: 0.6rem 1.6rem; border: 1px solid #333; background: #fff; border-radius: 8px; cursor: pointer; font-size: 0.95rem; }
        .paper-switch { display: block; text-align: center; margin-top: 0.6rem; font-size: 0.75rem; }
        @media print { .print-btn, .paper-switch { display: none; } body { margin: 0 auto; padding: 0; } }
    </style>
</head>
<body>
    <h1>{{ $venue ? ($venue->business_name ?: $venue->name) : 'Barmada' }}</h1>
    <p class="meta">
        {{ __('Table :number', ['number' => $table->table_number ?? $table->id]) }}
        @if($session) · {{ __('session #:number', ['number' => $session->session_number]) }} @endif
        · {{ \App\Support\VenueClock::now($venue)->format('Y-m-d H:i') }}
    </p>

    @if(empty($lines))
        <p style="text-align:center;color:#777;">{{ __('No orders this session.') }}</p>
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
            @if(!empty($bill['taxes']))
                <div><span>{{ __('Subtotal') }}</span><span>{{ $currency }}{{ number_format($bill['subtotal'], 2) }}</span></div>
                @foreach($bill['taxes'] as $tax)
                    <div><span>{{ $tax['label'] }}</span><span>{{ $currency }}{{ number_format($tax['amount'], 2) }}</span></div>
                @endforeach
            @endif
            @if($bill['service_charge'] > 0)
                <div><span>{{ __('Service charge') }} ({{ $venue ? $venue->serviceChargeRateBp() / 100 : 10 }}%)</span><span>{{ $currency }}{{ number_format($bill['service_charge'], 2) }}</span></div>
            @endif
            <div><span>{{ __('Total') }}</span><span>{{ $currency }}{{ number_format($bill['grand_total'], 2) }}</span></div>
            <div><span>{{ __('Paid') }}</span><span>{{ $currency }}{{ number_format($paid, 2) }}</span></div>
            <div class="due"><span>{{ __('Due') }}</span><span>{{ $currency }}{{ number_format($bill['grand_left'], 2) }}</span></div>
        </div>
    @endif

    @if($invoice)
        <div class="invoice">
            <h2>{{ __('Invoice details') }}</h2>
            <div>{{ $invoice->name }}</div>
            <div>{{ __('Tax ID') }}: {{ $invoice->tax_id }}</div>
            @if($invoice->address)<div>{{ $invoice->address }}</div>@endif
            @if($invoice->email)<div>{{ $invoice->email }}</div>@endif
            @if($invoice->phone)<div>{{ $invoice->phone }}</div>@endif
        </div>
    @endif

    @php
        $fiscalDoc = $session
            ? \App\Models\FiscalDocument::where('table_session_id', $session->id)->where('doc_type', '01')->whereNotIn('status', ['rejected', 'error'])->latest('id')->first()
            : null;
    @endphp
    @if($fiscalDoc)
        <div class="invoice">
            <h2>{{ __('Invoice :number', ['number' => $fiscalDoc->number()]) }}</h2>
            <div>{{ __('Status') }}: {{ $fiscalDoc->status }}</div>
            <div style="word-break:break-all;font-size:0.75rem;">{{ $fiscalDoc->clave_acceso }}</div>
            <a class="print-btn" style="text-decoration:none;text-align:center;" href="{{ route('fiscal.ride', $fiscalDoc) }}">{{ __('View RIDE') }}</a>
        </div>
        <p class="footer">{{ __('Electronic invoice issued — see RIDE for the fiscal representation.') }}</p>
    @else
        @if($errors->has('factura'))
            <p class="footer" style="color:#b00020;">{{ $errors->first('factura') }}</p>
        @endif
        @if($venue && $venue->fiscal_enabled && $session && !empty($lines))
            <form method="POST" action="{{ route('tables.factura', $table) }}" onsubmit="return confirm({{ Js::from(__('Issue an electronic invoice for this bill?')) }})">
                @csrf
                <button type="submit" class="print-btn">{{ __('Issue invoice') }}</button>
            </form>
        @endif
        <p class="footer">{{ __('Not a fiscal receipt — internal bill summary.') }}</p>
    @endif
    <button class="print-btn" onclick="window.print()">{{ __('Print') }}</button>
    <a class="paper-switch" href="?{{ $isNarrow ? '' : 'w=58' }}">
        {{ $isNarrow ? __('Switch to 80mm paper') : __('Switch to 58mm paper') }}
    </a>
</body>
</html>
