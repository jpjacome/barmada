<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Support\Money;

/**
 * The single place orders.total_amount / amount_paid / amount_left are
 * written from the items.
 *
 * Those three columns are a denormalisation of SUM(order_items.price)
 * and SUM(... WHERE is_paid). Before this action they were maintained in
 * CreateOrder only; settling touched two of the three, editing an order
 * touched none, and analytics (which reads the columns) drifted from the
 * bill (which sums the items). Every mutation path now ends here.
 */
class RecalculateOrderTotals
{
    public function handle(Order $order): Order
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();

        $total = Money::sum($items->pluck('price'));
        $paid = Money::sum($items->where('is_paid', true)->pluck('price'));

        $order->total_amount = $total;
        $order->amount_paid = $paid;
        $order->amount_left = Money::subtract($total, $paid);
        $order->save();

        return $order;
    }
}
