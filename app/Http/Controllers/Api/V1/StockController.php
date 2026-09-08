<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Inventory\StockLedger;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Inventory for the staff app: the shelf list for a physical count,
 * receiving, waste, and each product's recipe. Tenant-bounded by
 * EditorScope; authorised by StockItemPolicy.
 */
class StockController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', StockItem::class);

        $items = StockItem::orderBy('name')
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->get();

        return response()->json(['stock_items' => $items->map(fn ($i) => $this->row($i))]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', StockItem::class);

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'sku' => 'nullable|string|max:40',
            'unit' => 'required|in:'.implode(',', StockItem::UNITS),
            'reorder_level' => 'nullable|numeric|min:0',
        ]);

        $item = StockItem::create($validated + ['editor_id' => $request->user()->effectiveEditorId()]);

        return response()->json(['stock_item' => $this->row($item)], 201);
    }

    public function update(Request $request, StockItem $stockItem)
    {
        $this->authorize('manage', $stockItem);

        $validated = $request->validate([
            'name' => 'sometimes|string|min:2|max:120',
            'sku' => 'nullable|string|max:40',
            'unit' => 'sometimes|in:'.implode(',', StockItem::UNITS),
            'reorder_level' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $stockItem->fill($validated)->save();

        return response()->json(['stock_item' => $this->row($stockItem->refresh())]);
    }

    /** POST /stock-items/{id}/movements — purchase | waste | count | adjustment */
    public function move(Request $request, StockItem $stockItem, StockLedger $ledger)
    {
        $this->authorize('update', $stockItem);

        $validated = $request->validate([
            'type' => 'required|in:purchase,waste,count,adjustment',
            'quantity' => 'required|numeric',
            'unit_cost' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        $qty = (float) $validated['quantity'];
        $actor = $request->user();
        $note = $validated['note'] ?? null;

        $movement = match ($validated['type']) {
            'purchase' => $ledger->receive($stockItem, abs($qty), (float) ($validated['unit_cost'] ?? $stockItem->unit_cost), $actor, $note),
            'waste' => $ledger->waste($stockItem, abs($qty), $actor, $note),
            'count' => $ledger->count($stockItem, $qty, $actor, $note),
            'adjustment' => $ledger->adjust($stockItem, $qty, $actor, $note ?? 'Manual adjustment'),
        };

        return response()->json([
            'movement' => $this->movementRow($movement),
            'stock_item' => $this->row($stockItem->refresh()),
        ], 201);
    }

    public function movements(Request $request, StockItem $stockItem)
    {
        $this->authorize('view', $stockItem);

        $rows = StockMovement::where('stock_item_id', $stockItem->id)
            ->latest('occurred_at')->latest('id')
            ->limit(min(200, (int) $request->integer('limit', 50) ?: 50))
            ->get();

        return response()->json(['movements' => $rows->map(fn ($m) => $this->movementRow($m))]);
    }

    public function components(Product $product)
    {
        $this->authorize('view', $product);

        return response()->json(['components' => $this->componentRows($product)]);
    }

    /** PUT /products/{product}/components — replace the whole recipe. */
    public function setComponents(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'components' => 'present|array|max:50',
            'components.*.stock_item_id' => 'required|integer',
            'components.*.quantity_per_unit' => 'required|numeric|gt:0',
        ]);

        $itemIds = collect($validated['components'])->pluck('stock_item_id')->map(fn ($v) => (int) $v);
        // Every referenced item must resolve within the caller's tenant.
        $known = StockItem::whereIn('id', $itemIds)->pluck('id');
        abort_if($known->count() !== $itemIds->unique()->count(), 422, __('Unknown stock item.'));

        ProductComponent::where('product_id', $product->id)->whereNotIn('stock_item_id', $itemIds)->delete();
        foreach ($validated['components'] as $c) {
            ProductComponent::updateOrCreate(
                ['product_id' => $product->id, 'stock_item_id' => (int) $c['stock_item_id']],
                ['quantity_per_unit' => (float) $c['quantity_per_unit']],
            );
        }

        return response()->json(['components' => $this->componentRows($product)]);
    }

    private function componentRows(Product $product): array
    {
        return ProductComponent::with('stockItem')->where('product_id', $product->id)->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'stock_item_id' => $c->stock_item_id,
                'stock_item_name' => $c->stockItem?->name,
                'unit' => $c->stockItem?->unit,
                'quantity_per_unit' => (float) $c->quantity_per_unit,
            ])->values()->all();
    }

    private function row(StockItem $i): array
    {
        return [
            'id' => $i->id,
            'name' => $i->name,
            'sku' => $i->sku,
            'unit' => $i->unit,
            'quantity_on_hand' => (float) $i->quantity_on_hand,
            'reorder_level' => $i->reorder_level !== null ? (float) $i->reorder_level : null,
            'unit_cost' => (float) $i->unit_cost,
            'is_low' => $i->isLow(),
            'is_active' => (bool) $i->is_active,
        ];
    }

    private function movementRow(StockMovement $m): array
    {
        return [
            'id' => $m->id,
            'type' => $m->type,
            'quantity' => (float) $m->quantity,
            'unit_cost' => $m->unit_cost !== null ? (float) $m->unit_cost : null,
            'reference_type' => $m->reference_type,
            'reference_id' => $m->reference_id,
            'user_id' => $m->user_id,
            'note' => $m->note,
            'occurred_at' => $m->occurred_at?->toIso8601String(),
        ];
    }
}
