<?php

namespace App\Support;

use App\Models\ClientInvoice;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableSession;

/**
 * The staff-side bill read model for a table's CURRENT session: countable
 * (non-cancelled [#12]) orders with per-item paid state, and the session
 * totals. Shared by the Livewire tables screen, the printed bill, and the
 * API session endpoint so every surface shows the same numbers.
 */
class TableBill
{
    /**
     * @return array{
     *     session: ?TableSession,
     *     orders: array<int, array<string, mixed>>,
     *     total: float|int,
     *     paid: float|int,
     *     left: float|int,
     *     invoice: ?ClientInvoice,
     * }
     */
    public static function build(Table $table): array
    {
        $session = $table->sessions()
            ->whereIn('status', ['open', 'reopened'])
            ->latest('opened_at')
            ->first();

        $orders = $session
            ? Order::countable()
                ->where('table_id', $table->id)
                ->where('table_session_id', $session->id)
                ->with(['items.product'])
                ->orderBy('created_at', 'desc')
                ->get()
            : collect();

        $orderRows = $orders->map(function ($order) {
            $totalAmount = $order->items->sum(fn ($item) => $item->price);
            $paidAmount = $order->items->where('is_paid', true)->sum(fn ($item) => $item->price);

            return [
                'id' => $order->id,
                'status' => $order->status,
                'note' => $order->note,
                'created_at' => $order->created_at,
                'total_amount' => $totalAmount,
                'amount_paid' => $paidAmount,
                'amount_left' => $totalAmount - $paidAmount,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'item_index' => $item->item_index,
                    'price' => $item->price,
                    'is_paid' => $item->is_paid,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'icon_type' => $item->product->icon_type,
                        'icon_value' => $item->product->icon_value,
                    ],
                ])->toArray(),
            ];
        })->toArray();

        $total = collect($orderRows)->sum('total_amount');
        $paid = collect($orderRows)->sum('amount_paid');

        return [
            'session' => $session,
            'orders' => $orderRows,
            'total' => $total,
            'paid' => $paid,
            'left' => $total - $paid,
            'invoice' => $session
                ? ClientInvoice::where('table_session_id', $session->id)->first()
                : null,
        ];
    }
}
