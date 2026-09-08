<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs orders damaged by the board's edit-order modal.
 *
 * order_items carries one row per physical unit — CreateOrder has always
 * written them that way, and the bill, the API and item-level payment
 * ticking all assume it. The board's "save changes" action instead wrote
 * a single row with quantity > 1, so a 3x line was billed as 1x while
 * analytics (which does multiply by quantity) reported the full amount.
 *
 * This expands any surviving collapsed row back into one row per unit and
 * rewrites the affected orders' payment columns from their items. Paid
 * state is preserved: a collapsed row that was ticked as paid produces
 * units that are all paid.
 *
 * Idempotent — once every row is quantity = 1 there is nothing to do.
 */
return new class extends Migration
{
    public function up(): void
    {
        $collapsed = DB::table('order_items')->where('quantity', '>', 1)->get();

        if ($collapsed->isEmpty()) {
            return;
        }

        $affectedOrderIds = $collapsed->pluck('order_id')->unique();

        DB::transaction(function () use ($collapsed, $affectedOrderIds) {
            foreach ($collapsed as $row) {
                // Keep the original row as the first unit.
                DB::table('order_items')->where('id', $row->id)->update(['quantity' => 1]);

                $nextIndex = (int) DB::table('order_items')
                    ->where('order_id', $row->order_id)
                    ->max('item_index');

                $extras = [];
                for ($unit = 1; $unit < (int) $row->quantity; $unit++) {
                    $extras[] = [
                        'order_id' => $row->order_id,
                        'product_id' => $row->product_id,
                        'quantity' => 1,
                        'price' => $row->price,
                        'is_paid' => $row->is_paid,
                        'item_index' => ++$nextIndex,
                        'created_at' => $row->created_at,
                        'updated_at' => now(),
                    ];
                }

                if ($extras !== []) {
                    DB::table('order_items')->insert($extras);
                }
            }

            // Re-derive the denormalized money columns for every order we
            // touched, so the stored total matches the items again.
            foreach ($affectedOrderIds as $orderId) {
                $total = (float) DB::table('order_items')
                    ->where('order_id', $orderId)
                    ->sum('price');

                $paid = (float) DB::table('order_items')
                    ->where('order_id', $orderId)
                    ->where('is_paid', true)
                    ->sum('price');

                DB::table('orders')->where('id', $orderId)->update([
                    'total_amount' => round($total, 2),
                    'amount_paid' => round($paid, 2),
                    'amount_left' => round($total - $paid, 2),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Expanded units cannot be told apart from units that were always
        // separate, and re-collapsing them would restore the undercharge.
        // Nothing to reverse.
    }
};
