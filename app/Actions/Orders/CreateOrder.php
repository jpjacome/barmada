<?php

namespace App\Actions\Orders;

use App\Exceptions\DomainActionException;
use App\Exceptions\InvalidProductException;
use App\Exceptions\ProductUnavailableException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;

/**
 * Creates an order on a table session — the single code path behind the
 * guest QR flow, the staff manual-entry form, and the API.
 *
 * Items are exploded one row per unit (quantity 1, sequential item_index)
 * so the item-by-item payment tracking can settle each unit separately.
 * Every product must resolve within the table's tenant and be available;
 * validation happens BEFORE the order row exists so failures never persist
 * partial orders.
 */
class CreateOrder
{
    /**
     * @param  array<int|string, int|string>  $productQuantities  product_id => quantity
     *
     * @throws DomainActionException
     */
    public function handle(
        Table $table,
        TableSession $session,
        array $productQuantities,
        ?string $note = null,
        ?User $creator = null,
    ): Order {
        // Normalize and drop zero/invalid quantities.
        $quantities = [];
        foreach ($productQuantities as $productId => $quantity) {
            $quantity = min(99, max(0, (int) $quantity));
            if ($quantity > 0) {
                $quantities[(int) $productId] = $quantity;
            }
        }

        if ($quantities === []) {
            throw new DomainActionException(__('Please select at least one product.'));
        }

        // Resolve every product within the table's tenant up front. This also
        // bounds admin- and staff-created orders to the right catalog.
        $products = [];
        foreach (array_keys($quantities) as $productId) {
            $product = Product::forEditor($table->editor_id)->find($productId);

            if (! $product) {
                throw new InvalidProductException(__('Invalid product for this table.'));
            }

            if (! $product->is_available) {
                // Sold out between page load and submit. [86]
                throw new ProductUnavailableException($product);
            }

            $products[$productId] = $product;
        }

        // Tenant on the order row: guests and admins record the table's
        // tenant; editors and staff record their own (identical to the
        // table's by construction — cross-tenant tables don't resolve).
        $editorId = ($creator === null || $creator->is_admin)
            ? $table->editor_id
            : $creator->effectiveEditorId();

        $order = Order::create([
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'status' => 'pending',
            'note' => $note !== null && $note !== '' ? strip_tags($note) : null,
            'created_by' => $creator?->id,
            'total_amount' => 0,
            'amount_paid' => 0,
            'amount_left' => 0,
            'editor_id' => $editorId,
        ]);

        $totalAmount = 0;
        $itemIndex = 0;
        foreach ($quantities as $productId => $quantity) {
            $price = $products[$productId]->price;
            for ($i = 0; $i < $quantity; $i++) {
                $order->items()->create([
                    'product_id' => $productId,
                    'quantity' => 1,
                    'price' => $price,
                    'is_paid' => false,
                    'item_index' => $itemIndex++,
                ]);
                $totalAmount += $price;
            }
        }

        $order->update([
            'total_amount' => $totalAmount,
            'amount_left' => $totalAmount,
        ]);

        // Every order placed — guest QR, staff form or API — alerts the
        // venue's registered staff devices.
        \App\Support\Push::venue($table->editor_id, 'order.created', [
            'order_id' => $order->id,
            'table_number' => $table->table_number,
        ]);

        return $order->refresh()->load('items.product');
    }
}
