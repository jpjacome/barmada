<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura {{ $document->number() }}</title>
    <style>
        /* RIDE — representación impresa. Thermal-friendly: 80mm (default) or 58mm via ?w=58. */
        body { font-family: "DejaVu Sans", Arial, sans-serif; color: #111; margin: 0 auto; padding: 0 3mm; max-width: {{ $width === 58 ? '200px' : '302px' }}; font-size: {{ $width === 58 ? '10px' : '12px' }}; }
        h1 { font-size: 1.1em; text-align: center; margin: 0.6em 0 0.1em; text-transform: uppercase; letter-spacing: .04em; }
        .muted { color: #555; }
        .center { text-align: center; }
        .box { border: 1px solid #333; padding: 0.4em 0.5em; margin: 0.5em 0; }
        .kv { display: flex; justify-content: space-between; gap: 0.5em; }
        table { width: 100%; border-collapse: collapse; margin-top: 0.4em; }
        td { padding: 0.15em 0; vertical-align: top; }
        td.qty { width: 2.2em; white-space: nowrap; }
        td.amount { text-align: right; white-space: nowrap; }
        .totals { border-top: 1px dashed #999; margin-top: 0.5em; padding-top: 0.4em; }
        .totals .kv { padding: 0.08em 0; }
        .totals .grand { font-weight: bold; font-size: 1.1em; }
        .clave { font-family: "DejaVu Sans Mono", monospace; font-size: 0.8em; word-break: break-all; text-align: center; }
        .status { display: inline-block; padding: 0.1em 0.5em; border: 1px solid #333; border-radius: 3px; font-size: 0.85em; }
        .footer { text-align: center; color: #777; font-size: 0.8em; margin: 1em 0; }
        .print-btn { display: block; margin: 1em auto; padding: 0.5em 1.4em; border: 1px solid #333; background: #fff; border-radius: 6px; cursor: pointer; }
        @page { size: auto; margin: 4mm; }
        @media print { .print-btn { display: none; } body { margin: 0; padding: 0; } }
    </style>
</head>
<body>
    <h1>{{ $venue->fiscal_nombre_comercial ?: $venue->fiscal_razon_social }}</h1>
    <div class="center muted">{{ $venue->fiscal_razon_social }}</div>
    <div class="center muted">RUC {{ $venue->fiscal_ruc }}</div>
    <div class="center muted">{{ $venue->fiscal_dir_establecimiento ?: $venue->fiscal_dir_matriz }}</div>
    @if($venue->fiscal_obligado_contabilidad)<div class="center muted">Obligado a llevar contabilidad: SÍ</div>@endif
    @if(($venue->fiscal_rimpe ?? 'none') !== 'none')<div class="center muted">{{ $venue->fiscal_rimpe === 'negocio_popular' ? 'CONTRIBUYENTE NEGOCIO POPULAR - RÉGIMEN RIMPE' : 'CONTRIBUYENTE RÉGIMEN RIMPE' }}</div>@endif

    <div class="box">
        <div class="kv"><span>FACTURA</span><strong>{{ $document->number() }}</strong></div>
        <div class="kv"><span>Fecha de emisión</span><span>{{ $document->fecha_emision->format('d/m/Y') }}</span></div>
        <div class="kv"><span>Ambiente</span><span>{{ $document->ambiente === 2 ? 'PRODUCCIÓN' : 'PRUEBAS' }}</span></div>
        <div class="kv"><span>Emisión</span><span>NORMAL</span></div>
        <div style="margin-top:0.3em;">Clave de acceso / N.º de autorización</div>
        <div class="clave">{{ $document->clave_acceso }}</div>
        <div class="center" style="margin-top:0.3em;">
            <span class="status">{{ strtoupper($document->status === 'authorized' ? 'AUTORIZADO' : ($document->status === 'rejected' ? 'RECHAZADO' : 'PENDIENTE DE AUTORIZACIÓN')) }}</span>
            @if($document->authorized_at)<div class="muted">{{ $document->authorized_at->format('d/m/Y H:i:s') }}</div>@endif
        </div>
    </div>

    <div>
        <div><strong>{{ $document->buyer_name }}</strong></div>
        <div class="muted">{{ $document->buyer_id_type === '04' ? 'RUC' : ($document->buyer_id_type === '05' ? 'C.I.' : ($document->buyer_id_type === '07' ? 'Consumidor final' : 'ID')) }} {{ $document->buyer_identification }}</div>
        @if($document->buyer_address)<div class="muted">{{ $document->buyer_address }}</div>@endif
        @if($document->buyer_email)<div class="muted">{{ $document->buyer_email }}</div>@endif
    </div>

    <table>
        @foreach($document->lines as $line)
            <tr>
                <td class="qty">{{ rtrim(rtrim(number_format($line['cantidad'], 2, '.', ''), '0'), '.') }}×</td>
                <td>{{ $line['descripcion'] }}</td>
                <td class="amount">{{ $currency }}{{ number_format($line['total_sin_impuesto'], 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="totals">
        @php $byRate = collect($document->taxes); @endphp
        @foreach($byRate as $tax)
            <div class="kv"><span>Subtotal {{ $tax['tarifa_bp'] / 100 }}%</span><span>{{ $currency }}{{ number_format($tax['base'], 2) }}</span></div>
        @endforeach
        <div class="kv"><span>Subtotal sin impuestos</span><span>{{ $currency }}{{ number_format($document->subtotal, 2) }}</span></div>
        <div class="kv"><span>Descuento</span><span>{{ $currency }}0.00</span></div>
        @foreach($byRate as $tax)
            @if($tax['tarifa_bp'] > 0)
                <div class="kv"><span>IVA {{ $tax['tarifa_bp'] / 100 }}%</span><span>{{ $currency }}{{ number_format($tax['valor'], 2) }}</span></div>
            @endif
        @endforeach
        <div class="kv"><span>Propina (servicio)</span><span>{{ $currency }}{{ number_format($document->propina, 2) }}</span></div>
        <div class="kv grand"><span>VALOR TOTAL</span><span>{{ $currency }}{{ number_format($document->importe_total, 2) }}</span></div>
    </div>

    <table style="margin-top:0.5em;">
        @foreach($document->payments as $p)
            <tr>
                <td>Forma de pago {{ $p['forma_pago'] }}</td>
                <td class="amount">{{ $currency }}{{ number_format($p['total'], 2) }}</td>
            </tr>
        @endforeach
    </table>

    @if($document->propina > 0)
        <div class="muted center" style="margin-top:0.4em;">Incluye el 10% de servicios – propina – TIP</div>
    @endif

    <div class="footer">
        @if($document->ambiente !== 2)Documento emitido en ambiente de pruebas — sin validez tributaria.<br>@endif
        @if($document->table)Mesa {{ $document->table->table_number }} · @endif{{ config('app.name', 'Barmada') }}
    </div>

    <button class="print-btn" onclick="window.print()">Imprimir</button>
</body>
</html>
