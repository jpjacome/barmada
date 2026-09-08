<?php

namespace App\Actions\Orders;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

/**
 * The payment-ticking core: toggles one item's paid flag, records who
 * took the money and how, writes the payment activity log, recomputes
 * the order's money columns, and auto-marks the order delivered once
 * every item is paid (it never reverts to pending when un-ticking).
 */
class ToggleItemPaid
{
    public function handle(Order $order, int $productId, int $itemIndex, ?User $actor = null, ?string $method = null): ?OrderItem
    {
        $orderItem = OrderItem::where('order_id', $order->id)
            ->where('product_id', $productId)
            ->where('item_index', $itemIndex)
            ->first();

        if (! $orderItem) {
            return null;
        }

        $orderItem->is_paid = ! $orderItem->is_paid;

        if ($orderItem->is_paid) {
            $orderItem->paid_at = now();
            $orderItem->paid_by = $actor?->id;
            $orderItem->payment_method = $method;
        } else {
            $orderItem->paid_at = null;
            $orderItem->paid_by = null;
            $orderItem->payment_method = null;
        }

        $orderItem->save();

        ActivityLog::create([
            'type' => 'payment',
            'table_id' => $order->table_id,
            'order_id' => $order->id,
            'user_id' => $actor?->id,
            'amount' => $orderItem->price,
            'description' => $orderItem->is_paid
                ? "Item #{$itemIndex} of Product #{$productId} marked as paid in Order #{$order->id} for Table #{$order->table_id}"
                : "Item #{$itemIndex} of Product #{$productId} marked as unpaid in Order #{$order->id} for Table #{$order->table_id}",
            'metadata' => [
                'product_id' => $productId,
                'item_index' => $itemIndex,
                'action' => $orderItem->is_paid ? 'paid' : 'unpaid',
                'payment_method' => $method,
            ],
            'editor_id' => $order->table?->editor_id,
        ]);

        $fresh = Order::with('items')->find($order->id);
        if ($fresh) {
            app(RecalculateOrderTotals::class)->handle($fresh);

            // Fully paid orders count as delivered; partial payment never
            // reverts a delivered order back to pending.
            if ($fresh->items->isNotEmpty() && $fresh->items->every(fn ($item) => $item->is_paid)) {
                $fresh->status = 'delivered';
                $fresh->save();
            }
        }

        return $orderItem;
    }
}
