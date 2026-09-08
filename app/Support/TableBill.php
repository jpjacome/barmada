<?php

namespace App\Support;

use App\Models\ClientInvoice;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableSession;

/**
 * The staff-side bill read model for a table's CURRENT session: countable
 * (non-cancelled [#12]) orders with per-item paid state, the session
 * totals and the tax breakdown. Shared by the Livewire tables screen, the
 * printed bill, the guest's session page and the API session endpoint so
 * every surface shows the same numbers.
 */
class TableBill
{
    /**
     * @return array{
     *     session: ?TableSession,
     *     orders: array<int, array<string, mixed>>,
     *     total: float,
     *     paid: float,
     *     left: float,
     *     subtotal: float,
     *     tax_total: float,
     *     taxes: array<int, array{code:string,label:string,rate_bp:int,base:float,amount:float}>,
     *     service_charge: float,
     *     grand_total: float,
     *     grand_left: float,
     *     invoice: ?ClientInvoice,
     * }
     */
    public static function build(Table $table): array
    {
        $session = $table->currentSession();

        $orders = $session
            ? Order::countable()
                ->where('table_id', $table->id)
                ->where('table_session_id', $session->id)
                ->with(['items.product'])
                ->orderBy('created_at', 'desc')
                ->get()
            : collect();

        $taxBuckets = [];

        $orderRows = $orders->map(function ($order) use (&$taxBuckets) {
            $totalAmount = Money::sum($order->items->pluck('price'));
            $paidAmount = Money::sum($order->items->where('is_paid', true)->pluck('price'));

            foreach ($order->items as $item) {
                $code = (string) ($item->tax_code ?? '');
                if ($code === '') {
                    continue; // pre-tax-model row: no breakdown available
                }
                $taxBuckets[$code] ??= ['base' => 0, 'amount' => 0, 'rate_bp' => (int) $item->tax_rate_bp];
                $taxBuckets[$code]['base'] = Money::add($taxBuckets[$code]['base'], $item->net_price ?? $item->price);
                $taxBuckets[$code]['amount'] = Money::add($taxBuckets[$code]['amount'], $item->tax_amount ?? 0);
            }

            return [
                'id' => $order->id,
                'status' => $order->status,
                'note' => $order->note,
                'created_at' => $order->created_at,
                'total_amount' => $totalAmount,
                'amount_paid' => $paidAmount,
                'amount_left' => Money::subtract($totalAmount, $paidAmount),
                'subtotal' => (float) ($order->subtotal ?? $totalAmount),
                'tax_total' => (float) ($order->tax_total ?? 0),
                'service_charge' => (float) ($order->service_charge ?? 0),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'item_index' => $item->item_index,
                    'price' => $item->price,
                    'net_price' => $item->net_price,
                    'tax_amount' => $item->tax_amount,
                    'tax_code' => $item->tax_code,
                    'is_paid' => $item->is_paid,
                    'payment_method' => $item->payment_method,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'icon_type' => $item->product->icon_type,
                        'icon_value' => $item->product->icon_value,
                    ],
                ])->toArray(),
            ];
        })->toArray();

        $total = Money::sum(array_column($orderRows, 'total_amount'));
        $paid = Money::sum(array_column($orderRows, 'amount_paid'));
        $subtotal = Money::sum(array_column($orderRows, 'subtotal'));
        $taxTotal = Money::sum(array_column($orderRows, 'tax_total'));
        $serviceCharge = Money::sum(array_column($orderRows, 'service_charge'));
        $grandTotal = Money::add($total, $serviceCharge);

        $taxes = collect($taxBuckets)
            ->map(fn ($bucket, $code) => [
                'code' => (string) $code,
                'label' => Tax::label((string) $code),
                'rate_bp' => $bucket['rate_bp'],
                'base' => $bucket['base'],
                'amount' => $bucket['amount'],
            ])
            ->sortByDesc('rate_bp')
            ->values()
            ->all();

        return [
            'session' => $session,
            'orders' => $orderRows,
            'total' => $total,
            'paid' => $paid,
            'left' => Money::subtract($total, $paid),
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'taxes' => $taxes,
            'service_charge' => $serviceCharge,
            'grand_total' => $grandTotal,
            // The service charge is owed with the bill; it counts as
            // settled once every item is.
            'grand_left' => Money::subtract($grandTotal, Money::isZero(Money::subtract($total, $paid)) ? $grandTotal : $paid),
            'invoice' => $session
                ? ClientInvoice::where('table_session_id', $session->id)->first()
                : null,
        ];
    }
}
