<?php

namespace App\Actions\Tables;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;

/**
 * Marks every item on the table's countable (non-cancelled) orders as
 * paid, updates each order's payment columns and status, and logs the
 * table-wide payment.
 */
class SettleTable
{
    public function handle(Table $table): Table
    {
        $orders = Order::countable()->where('table_id', $table->id)->get();
        $allItems = OrderItem::whereIn('order_id', $orders->pluck('id'))->get();

        $totalAmount = 0;
        $allItems->each(function ($item) use (&$totalAmount) {
            $item->is_paid = true;
            $item->save();
            $totalAmount += $item->price;
        });

        foreach ($orders as $order) {
            $items = $order->items;
            $order->amount_paid = $items->sum('price');
            $order->amount_left = 0;
            if ($items->count() > 0) {
                $order->status = 'delivered';
            }
            $order->save();
        }

        ActivityLog::create([
            'type' => 'payment',
            'table_id' => $table->id,
            'amount' => $totalAmount,
            'description' => "All items for Table #{$table->id} marked as paid",
            'metadata' => [
                'orders_count' => $orders->count(),
                'items_count' => $allItems->count(),
                'action' => 'paid',
            ],
            'editor_id' => $table->editor_id,
        ]);

        return $table;
    }
}
