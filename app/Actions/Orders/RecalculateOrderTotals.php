<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Support\Money;
use App\Support\Tax;

/**
 * The single place an order's money columns are written from its items.
 *
 *  total_amount  gross consumption  = Σ items.price (what the guest owes
 *                                     for the items, tax included)
 *  amount_paid   Σ price of paid items
 *  amount_left   total_amount − amount_paid
 *  subtotal      Σ items.net_price (pre-tax)
 *  tax_total     Σ items.tax_amount
 *  service_charge  subtotal × service_charge_rate_bp (the legal "10% de
 *                  servicio", on the pre-tax base, never part of the IVA base)
 *  grand_total   total_amount + service_charge
 *
 * Before this action the first three were maintained in CreateOrder only;
 * settling touched two, editing touched none, and analytics (which reads
 * the columns) drifted from the bill (which summed the items).
 */
class RecalculateOrderTotals
{
    public function handle(Order $order): Order
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();

        $total = Money::sum($items->pluck('price'));
        $paid = Money::sum($items->where('is_paid', true)->pluck('price'));

        // Items predating the tax model have no snapshot: treat them as
        // untaxed net so the breakdown still adds up.
        $subtotal = Money::sum($items->map(fn ($i) => $i->net_price ?? $i->price));
        $taxTotal = Money::sum($items->map(fn ($i) => $i->tax_amount ?? 0));
        $serviceCharge = Tax::serviceCharge($subtotal, (int) ($order->service_charge_rate_bp ?? 0));

        $order->total_amount = $total;
        $order->amount_paid = $paid;
        $order->amount_left = Money::subtract($total, $paid);
        $order->subtotal = $subtotal;
        $order->tax_total = $taxTotal;
        $order->service_charge = $serviceCharge;
        $order->grand_total = Money::add($total, $serviceCharge);
        $order->save();

        return $order;
    }
}
