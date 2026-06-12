<?php

namespace App\Actions\Orders;

use App\Models\ActivityLog;
use App\Models\Order;

/**
 * Marks every item of one order as paid, updates the order's payment
 * columns and status, and logs the bulk payment.
 */
class SettleOrder
{
    public function handle(Order $order): Order
    {
        $items = $order->items;

        $totalAmount = 0;
        $items->each(function ($item) use (&$totalAmount) {
            $item->is_paid = true;
            $item->save();
            $totalAmount += $item->price;
        });

        $order->amount_paid = $items->sum('price');
        $order->amount_left = 0;
        if ($items->count() > 0) {
            $order->status = 'delivered';
        }
        $order->save();

        ActivityLog::create([
            'type' => 'payment',
            'table_id' => $order->table_id,
            'order_id' => $order->id,
            'amount' => $totalAmount,
            'description' => "All items in Order #{$order->id} marked as paid for Table #{$order->table_id}",
            'metadata' => [
                'items_count' => $items->count(),
                'action' => 'paid',
            ],
            'editor_id' => $order->table?->editor_id,
        ]);

        return $order;
    }
}
