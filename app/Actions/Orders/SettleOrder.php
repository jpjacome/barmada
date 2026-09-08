<?php

namespace App\Actions\Orders;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;

/**
 * Marks every item of one order as paid, records who settled it and how,
 * recomputes the order's money columns and status, and logs the bulk
 * payment.
 */
class SettleOrder
{
    public function handle(Order $order, ?User $actor = null, ?string $method = null): Order
    {
        $items = $order->items()->get();
        $now = now();

        $newlyPaid = $items->where('is_paid', false);
        foreach ($newlyPaid as $item) {
            $item->is_paid = true;
            $item->paid_at = $now;
            $item->paid_by = $actor?->id;
            $item->payment_method = $method;
            $item->save();
        }

        if ($items->count() > 0) {
            $order->status = 'delivered';
        }
        $order->setRelation('items', $items);
        app(RecalculateOrderTotals::class)->handle($order);

        ActivityLog::create([
            'type' => 'payment',
            'table_id' => $order->table_id,
            'order_id' => $order->id,
            'user_id' => $actor?->id,
            // The money that changed hands in THIS action, not the order's
            // lifetime total.
            'amount' => Money::sum($newlyPaid->pluck('price')),
            'description' => "All items in Order #{$order->id} marked as paid for Table #{$order->table_id}",
            'metadata' => [
                'items_count' => $items->count(),
                'items_newly_paid' => $newlyPaid->count(),
                'action' => 'paid',
                'payment_method' => $method,
            ],
            'editor_id' => $order->table?->editor_id,
        ]);

        return $order;
    }
}
