<?php

namespace App\Inventory;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductComponent;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of stock movements. Every entry adjusts the item's
 * on-hand projection; purchases also move its weighted-average cost.
 */
class StockLedger
{
    /**
     * Goods received. Positive quantity, cost per unit in venue currency.
     */
    public function receive(StockItem $item, float $quantity, float $unitCost, ?User $actor = null, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($item, $quantity, $unitCost, $actor, $note) {
            $item = StockItem::withoutGlobalScopes()->lockForUpdate()->find($item->id);

            $onHand = max(0.0, (float) $item->quantity_on_hand);
            $newQty = $onHand + $quantity;
            // Weighted average: what was already there at its cost plus
            // what arrived at its cost.
            $item->unit_cost = $newQty > 0
                ? round((($onHand * (float) $item->unit_cost) + ($quantity * $unitCost)) / $newQty, 4)
                : $unitCost;
            $item->quantity_on_hand = $newQty;
            $item->save();

            return $this->write($item, StockMovement::TYPE_PURCHASE, $quantity, $unitCost, $actor, $note);
        });
    }

    /**
     * Breakage, spillage, expiry. Positive quantity of loss.
     */
    public function waste(StockItem $item, float $quantity, ?User $actor = null, ?string $note = null): StockMovement
    {
        return $this->apply($item, StockMovement::TYPE_WASTE, -abs($quantity), $actor, $note);
    }

    /**
     * A physical count: records the delta between what the ledger thought
     * and what is actually on the shelf — the variance a manager looks at.
     */
    public function count(StockItem $item, float $counted, ?User $actor = null, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($item, $counted, $actor, $note) {
            $item = StockItem::withoutGlobalScopes()->lockForUpdate()->find($item->id);
            $delta = round($counted - (float) $item->quantity_on_hand, 3);
            $item->quantity_on_hand = $counted;
            $item->save();

            return $this->write($item, StockMovement::TYPE_COUNT, $delta, null, $actor, $note ?? 'Physical count');
        });
    }

    /**
     * Manual correction with a reason (signed quantity).
     */
    public function adjust(StockItem $item, float $signedQuantity, ?User $actor, string $reason): StockMovement
    {
        return $this->apply($item, StockMovement::TYPE_ADJUSTMENT, $signedQuantity, $actor, $reason);
    }

    /**
     * Bring an order's stock effect in line with its status: a DELIVERED
     * order depletes each unit's recipe once; anything else with a prior
     * depletion gets it reversed. Idempotent — safe to call from every
     * status-changing path.
     */
    public function syncOrder(Order $order, ?User $actor = null): void
    {
        $order->loadMissing('items');
        $shouldDeplete = $order->status === 'delivered';

        foreach ($order->items as $item) {
            $depleted = StockMovement::withoutGlobalScopes()
                ->where('reference_type', OrderItem::class)
                ->where('reference_id', $item->id)
                ->where('type', StockMovement::TYPE_SALE)
                ->exists();
            $reversed = StockMovement::withoutGlobalScopes()
                ->where('reference_type', OrderItem::class)
                ->where('reference_id', $item->id)
                ->where('type', StockMovement::TYPE_REVERSAL)
                ->exists();
            $inEffect = $depleted && ! $reversed;

            if ($shouldDeplete && ! $inEffect) {
                $this->depleteUnit($item, $actor, $depleted /* re-deplete after a reversal */);
            } elseif (! $shouldDeplete && $inEffect) {
                $this->reverseUnit($item, $actor);
            }
        }
    }

    private function depleteUnit(OrderItem $unit, ?User $actor, bool $afterReversal): void
    {
        $components = ProductComponent::with('stockItem')->where('product_id', $unit->product_id)->get();
        if ($components->isEmpty()) {
            return; // no recipe: nothing to deplete, no COGS
        }

        DB::transaction(function () use ($unit, $components, $actor, $afterReversal) {
            $cost = 0.0;
            foreach ($components as $component) {
                $stock = $component->stockItem;
                if (! $stock) {
                    continue;
                }
                $qty = (float) $component->quantity_per_unit;
                $this->apply($stock, StockMovement::TYPE_SALE, -$qty, $actor, null, OrderItem::class, $unit->id);
                $cost += $qty * (float) $stock->unit_cost;
            }

            if ($afterReversal) {
                // A re-delivery after an un-delivery: the reversal row must
                // no longer mask the new sale rows.
                StockMovement::withoutGlobalScopes()
                    ->where('reference_type', OrderItem::class)
                    ->where('reference_id', $unit->id)
                    ->where('type', StockMovement::TYPE_REVERSAL)
                    ->delete();
            }

            $unit->cost_amount = round($cost, 4);
            $unit->save();
        });
    }

    private function reverseUnit(OrderItem $unit, ?User $actor): void
    {
        DB::transaction(function () use ($unit, $actor) {
            $sales = StockMovement::withoutGlobalScopes()
                ->where('reference_type', OrderItem::class)
                ->where('reference_id', $unit->id)
                ->where('type', StockMovement::TYPE_SALE)
                ->get();

            foreach ($sales as $sale) {
                $this->apply($sale->stockItem, StockMovement::TYPE_REVERSAL, abs((float) $sale->quantity), $actor, 'Order un-delivered', OrderItem::class, $unit->id);
            }
            // Delete the sale rows so a later re-delivery starts clean, but
            // keep one reversal row as the audit trail of what happened.
            StockMovement::withoutGlobalScopes()
                ->where('reference_type', OrderItem::class)
                ->where('reference_id', $unit->id)
                ->where('type', StockMovement::TYPE_SALE)
                ->delete();

            $unit->cost_amount = null;
            $unit->save();
        });
    }

    private function apply(StockItem $item, string $type, float $signedQty, ?User $actor, ?string $note, ?string $refType = null, ?int $refId = null): StockMovement
    {
        return DB::transaction(function () use ($item, $type, $signedQty, $actor, $note, $refType, $refId) {
            $locked = StockItem::withoutGlobalScopes()->lockForUpdate()->find($item->id);
            $locked->quantity_on_hand = round((float) $locked->quantity_on_hand + $signedQty, 3);
            $locked->save();

            return $this->write($locked, $type, $signedQty, $type === StockMovement::TYPE_SALE ? (float) $locked->unit_cost : null, $actor, $note, $refType, $refId);
        });
    }

    private function write(StockItem $item, string $type, float $qty, ?float $unitCost, ?User $actor, ?string $note, ?string $refType = null, ?int $refId = null): StockMovement
    {
        return StockMovement::create([
            'editor_id' => $item->editor_id,
            'stock_item_id' => $item->id,
            'type' => $type,
            'quantity' => round($qty, 3),
            'unit_cost' => $unitCost,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'user_id' => $actor?->id,
            'note' => $note,
            'occurred_at' => now(),
        ]);
    }
}
