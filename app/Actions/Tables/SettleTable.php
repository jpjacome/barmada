<?php

namespace App\Actions\Tables;

use App\Actions\Orders\RecalculateOrderTotals;
use App\Models\ActivityLog;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\User;
use App\Support\Money;

/**
 * Marks every item on the table's current-session orders as paid,
 * recording who settled the table and how, recomputes each order's
 * money columns and status, and logs the table-wide payment.
 */
class SettleTable
{
    public function handle(Table $table, ?User $actor = null, ?string $method = null): Table
    {
        // Current session only. Without this filter "pay all" settled every
        // order the table had ever carried, and the activity-log amount
        // recorded the table's lifetime unpaid total rather than tonight's.
        $orders = $table->currentSessionOrders()->with('items')->get();
        $now = now();

        $newlyPaid = collect();
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (! $item->is_paid) {
                    $item->is_paid = true;
                    $item->paid_at = $now;
                    $item->paid_by = $actor?->id;
                    $item->payment_method = $method;
                    $item->save();
                    $newlyPaid->push($item);
                }
            }

            if ($order->items->count() > 0) {
                $order->status = 'delivered';
            }
            app(RecalculateOrderTotals::class)->handle($order);
        }

        ActivityLog::create([
            'type' => 'payment',
            'table_id' => $table->id,
            'user_id' => $actor?->id,
            'amount' => Money::sum($newlyPaid->pluck('price')),
            'description' => "All items for Table #{$table->table_number} marked as paid",
            'metadata' => [
                'orders_count' => $orders->count(),
                'items_count' => $orders->sum(fn ($o) => $o->items->count()),
                'items_newly_paid' => $newlyPaid->count(),
                'action' => 'paid',
                'payment_method' => $method,
            ],
            'editor_id' => $table->editor_id,
        ]);

        return $table;
    }
}
