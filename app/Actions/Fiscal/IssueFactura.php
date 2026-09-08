<?php

namespace App\Actions\Fiscal;

use App\Exceptions\DomainActionException;
use App\Fiscal\ClaveAcceso;
use App\Fiscal\FacturaXmlBuilder;
use App\Fiscal\FormaPago;
use App\Fiscal\SequenceAllocator;
use App\Fiscal\Transport\TransportManager;
use App\Models\ClientInvoice;
use App\Models\FiscalDocument;
use App\Models\Table;
use App\Models\User;
use App\Support\Money;
use App\Support\TableBill;
use App\Support\VenueClock;
use Illuminate\Support\Facades\DB;

/**
 * Issues a factura for a table's current session:
 *
 *  1. the venue's fiscal profile must be complete and invoicing enabled;
 *  2. the bill must have something on it and not already be invoiced;
 *  3. the buyer is the captured ClientInvoice or CONSUMIDOR FINAL — and
 *     above the legal threshold a real identification is required;
 *  4. lines are grouped per product + tax code, money is taken from the
 *     order snapshots (never recomputed), payments from the items'
 *     recorded methods;
 *  5. a gapless secuencial is allocated, the clave de acceso built, the
 *     XML rendered, and the transport driver invoked synchronously.
 */
class IssueFactura
{
    public function __construct(
        private SequenceAllocator $sequences,
        private FacturaXmlBuilder $xml,
        private TransportManager $transports,
    ) {
    }

    /**
     * @throws DomainActionException
     */
    public function handle(Table $table, User $actor): FiscalDocument
    {
        $venue = $table->editor ?: User::withoutGlobalScopes()->find($table->editor_id);

        $this->assertProfileComplete($venue);

        $bill = TableBill::build($table);
        $session = $bill['session'];

        if (! $session || empty($bill['orders'])) {
            throw new DomainActionException(__('There is nothing on this table\'s bill to invoice.'));
        }

        $existing = FiscalDocument::withoutGlobalScopes()
            ->where('table_session_id', $session->id)
            ->where('doc_type', FiscalDocument::TYPE_FACTURA)
            ->whereNotIn('status', [FiscalDocument::STATUS_REJECTED, FiscalDocument::STATUS_ERROR])
            ->first();
        if ($existing) {
            throw new DomainActionException(__('This session already has factura :number.', ['number' => $existing->number()]));
        }

        $buyer = $this->buyer($bill['invoice'], (float) $bill['grand_total']);

        [$lines, $taxes] = $this->linesAndTaxes($bill['orders']);
        $payments = $this->payments($bill['orders'], (float) $bill['grand_total']);

        $ambiente = (int) $venue->fiscal_ambiente;
        $estab = str_pad((string) $venue->fiscal_estab, 3, '0', STR_PAD_LEFT);
        $ptoEmi = str_pad((string) $venue->fiscal_pto_emi, 3, '0', STR_PAD_LEFT);
        $issuedAt = VenueClock::now($venue);

        $document = DB::transaction(function () use ($venue, $table, $session, $actor, $buyer, $lines, $taxes, $payments, $bill, $ambiente, $estab, $ptoEmi, $issuedAt) {
            $secuencial = $this->sequences->next($venue, $ambiente, FiscalDocument::TYPE_FACTURA, $estab, $ptoEmi);

            $clave = ClaveAcceso::build(
                $issuedAt,
                FiscalDocument::TYPE_FACTURA,
                $venue->fiscal_ruc,
                $ambiente,
                $estab,
                $ptoEmi,
                $secuencial,
                ClaveAcceso::randomCodigoNumerico(),
            );

            return FiscalDocument::create([
                'editor_id' => $venue->id,
                'table_id' => $table->id,
                'table_session_id' => $session->id,
                'issued_by' => $actor->id,
                'doc_type' => FiscalDocument::TYPE_FACTURA,
                'ambiente' => $ambiente,
                'estab' => $estab,
                'pto_emi' => $ptoEmi,
                'secuencial' => $secuencial,
                'clave_acceso' => $clave,
                'fecha_emision' => $issuedAt->toDateString(),
                'buyer_id_type' => $buyer['id_type'],
                'buyer_identification' => $buyer['identification'],
                'buyer_name' => $buyer['name'],
                'buyer_address' => $buyer['address'],
                'buyer_email' => $buyer['email'],
                'subtotal' => $bill['subtotal'],
                'tax_total' => $bill['tax_total'],
                'propina' => $bill['service_charge'],
                'importe_total' => $bill['grand_total'],
                'taxes' => $taxes,
                'lines' => $lines,
                'payments' => $payments,
                'status' => FiscalDocument::STATUS_DRAFT,
            ]);
        });

        $document->xml_unsigned = $this->xml->build($document, $venue);
        $document->status = FiscalDocument::STATUS_BUILT;
        $document->attempts = 1;
        $document->last_attempt_at = now();
        $document->save();

        return $this->transports->for($venue)->submit($document, $venue);
    }

    private function assertProfileComplete(?User $venue): void
    {
        if (! $venue || ! $venue->fiscal_enabled) {
            throw new DomainActionException(__('Electronic invoicing is not enabled for this venue.'));
        }

        $missing = [];
        foreach (['fiscal_ruc' => 'RUC', 'fiscal_razon_social' => 'razón social', 'fiscal_dir_matriz' => 'dirección matriz'] as $field => $label) {
            if (trim((string) $venue->{$field}) === '') {
                $missing[] = $label;
            }
        }
        if (! preg_match('/^\d{13}$/', (string) $venue->fiscal_ruc)) {
            $missing[] = 'RUC (13 digits)';
        }

        if ($missing !== []) {
            throw new DomainActionException(__('Complete the venue\'s fiscal profile first: :fields.', ['fields' => implode(', ', array_unique($missing))]));
        }
    }

    /**
     * @return array{id_type:string,identification:string,name:string,address:?string,email:?string}
     */
    private function buyer(?ClientInvoice $invoice, float $total): array
    {
        $threshold = (float) config('fiscal.consumidor_final_max_total', 50);

        if ($invoice && trim((string) $invoice->tax_id) !== '') {
            $id = preg_replace('/\D+/', '', (string) $invoice->tax_id) ?? '';

            return [
                'id_type' => match (strlen($id)) {
                    13 => '04',       // RUC
                    10 => '05',       // cédula
                    default => '06',  // pasaporte / other
                },
                'identification' => $id !== '' ? $id : mb_substr((string) $invoice->tax_id, 0, 20),
                'name' => $invoice->name,
                'address' => $invoice->address,
                'email' => $invoice->email,
            ];
        }

        if ($total > $threshold) {
            throw new DomainActionException(__('Bills above :amount need the customer\'s identification (RUC, cédula or passport) before invoicing.', ['amount' => number_format($threshold, 2)]));
        }

        $cf = config('fiscal.consumidor_final');

        return [
            'id_type' => $cf['id_type'],
            'identification' => $cf['identification'],
            'name' => $cf['name'],
            'address' => null,
            'email' => null,
        ];
    }

    /**
     * Lines grouped by (product, tax code); document taxes aggregated by
     * code. Amounts come from item snapshots so the XML equals the bill.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function linesAndTaxes(array $orders): array
    {
        $groups = [];
        foreach ($orders as $order) {
            foreach ($order['items'] as $item) {
                $code = (string) ($item['tax_code'] ?? config('fiscal.default_iva_code'));
                $key = $item['product_id'].'|'.$code;
                $groups[$key] ??= [
                    'product_id' => $item['product_id'],
                    'name' => $item['product']['name'],
                    'code' => $code,
                    'qty' => 0,
                    'net' => 0.0,
                    'tax' => 0.0,
                ];
                $groups[$key]['qty']++;
                $groups[$key]['net'] = Money::add($groups[$key]['net'], $item['net_price'] ?? $item['price']);
                $groups[$key]['tax'] = Money::add($groups[$key]['tax'], $item['tax_amount'] ?? 0);
            }
        }

        $lines = [];
        $taxes = [];
        foreach ($groups as $g) {
            $rateBp = \App\Support\Tax::rateBp($g['code']);
            $lines[] = [
                'codigo_principal' => 'P'.$g['product_id'],
                'descripcion' => $g['name'],
                'cantidad' => $g['qty'],
                // Unit net price to six decimals so cantidad × precio = total.
                'precio_unitario' => round($g['net'] / $g['qty'], 6),
                'descuento' => 0,
                'total_sin_impuesto' => $g['net'],
                'impuestos' => [[
                    'codigo' => '2',
                    'codigo_porcentaje' => $g['code'],
                    'tarifa_bp' => $rateBp,
                    'base' => $g['net'],
                    'valor' => $g['tax'],
                ]],
            ];
            $taxes[$g['code']] ??= ['codigo' => '2', 'codigo_porcentaje' => $g['code'], 'tarifa_bp' => $rateBp, 'base' => 0.0, 'valor' => 0.0];
            $taxes[$g['code']]['base'] = Money::add($taxes[$g['code']]['base'], $g['net']);
            $taxes[$g['code']]['valor'] = Money::add($taxes[$g['code']]['valor'], $g['tax']);
        }

        return [array_values($lines), array_values($taxes)];
    }

    /**
     * Payments by SRI forma de pago, from the items' recorded methods.
     * Unpaid or method-less items count as cash; the propina rides with
     * the largest bucket so the pagos sum to importeTotal.
     *
     * @return array<int, array{forma_pago:string,total:float}>
     */
    private function payments(array $orders, float $importeTotal): array
    {
        $buckets = [];
        foreach ($orders as $order) {
            foreach ($order['items'] as $item) {
                $code = FormaPago::fromPaymentMethod($item['payment_method'] ?? null);
                $buckets[$code] = Money::add($buckets[$code] ?? 0, $item['price']);
            }
        }
        if ($buckets === []) {
            $buckets[FormaPago::SIN_SISTEMA_FINANCIERO] = 0.0;
        }

        $assigned = Money::sum($buckets);
        $remainder = Money::subtract($importeTotal, $assigned);
        if (! Money::isZero($remainder)) {
            arsort($buckets);
            $top = array_key_first($buckets);
            $buckets[$top] = Money::add($buckets[$top], $remainder);
        }

        $out = [];
        foreach ($buckets as $code => $total) {
            $out[] = ['forma_pago' => (string) $code, 'total' => $total];
        }

        return $out;
    }
}
