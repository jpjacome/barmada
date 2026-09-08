<?php

namespace App\Livewire;

use App\Inventory\StockLedger;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Stock on hand, receiving, waste, counts and recipes — one screen,
 * built for a phone in one hand while walking the shelves.
 */
class InventoryPanel extends Component
{
    use AuthorizesRequests;

    // New item form
    public $name = '';
    public $unit = 'unit';
    public $reorderLevel = '';

    // Movement form
    public $movementItemId = null;
    public $movementType = 'purchase';
    public $movementQty = '';
    public $movementCost = '';
    public $movementNote = '';

    // Recipe editor
    public $recipeProductId = null;
    public $recipeStockItemId = null;
    public $recipeQty = '';

    public $status = '';

    public function addItem()
    {
        $this->authorize('create', StockItem::class);

        $this->validate([
            'name' => 'required|string|min:2|max:120',
            'unit' => 'required|in:'.implode(',', StockItem::UNITS),
            'reorderLevel' => 'nullable|numeric|min:0',
        ]);

        StockItem::create([
            'name' => trim($this->name),
            'unit' => $this->unit,
            'reorder_level' => $this->reorderLevel !== '' ? $this->reorderLevel : null,
        ]);

        $this->reset(['name', 'unit', 'reorderLevel']);
        $this->unit = 'unit';
        $this->status = __('Stock item added.');
    }

    public function recordMovement(StockLedger $ledger)
    {
        $this->validate([
            'movementItemId' => 'required|integer',
            'movementType' => 'required|in:purchase,waste,count,adjustment',
            'movementQty' => 'required|numeric',
            'movementCost' => 'nullable|numeric|min:0',
            'movementNote' => 'nullable|string|max:255',
        ]);

        $item = StockItem::findOrFail($this->movementItemId); // tenant-bounded
        $this->authorize('update', $item);

        $qty = (float) $this->movementQty;
        $actor = Auth::user();
        $note = $this->movementNote !== '' ? $this->movementNote : null;

        match ($this->movementType) {
            'purchase' => $ledger->receive($item, abs($qty), (float) ($this->movementCost ?: $item->unit_cost), $actor, $note),
            'waste' => $ledger->waste($item, abs($qty), $actor, $note),
            'count' => $ledger->count($item, $qty, $actor, $note),
            'adjustment' => $ledger->adjust($item, $qty, $actor, $note ?? __('Manual adjustment')),
        };

        $this->reset(['movementQty', 'movementCost', 'movementNote']);
        $this->status = __('Movement recorded for :item.', ['item' => $item->name]);
    }

    public function addComponent()
    {
        $this->validate([
            'recipeProductId' => 'required|integer',
            'recipeStockItemId' => 'required|integer',
            'recipeQty' => 'required|numeric|gt:0',
        ]);

        $product = Product::findOrFail($this->recipeProductId);
        $item = StockItem::findOrFail($this->recipeStockItemId);
        $this->authorize('manage', $item);
        abort_unless((int) $product->editor_id === (int) $item->editor_id, 403);

        ProductComponent::updateOrCreate(
            ['product_id' => $product->id, 'stock_item_id' => $item->id],
            ['quantity_per_unit' => (float) $this->recipeQty],
        );

        $this->reset(['recipeStockItemId', 'recipeQty']);
        $this->status = __('Recipe updated for :product.', ['product' => $product->name]);
    }

    public function removeComponent($componentId)
    {
        $component = ProductComponent::with('stockItem')->findOrFail($componentId);
        $this->authorize('manage', $component->stockItem);
        $component->delete();
        $this->status = __('Component removed.');
    }

    public function toggleActive($itemId)
    {
        $item = StockItem::findOrFail($itemId);
        $this->authorize('manage', $item);
        $item->is_active = ! $item->is_active;
        $item->save();
    }

    public function render()
    {
        $items = StockItem::orderBy('name')->get();
        $recipe = $this->recipeProductId
            ? ProductComponent::with('stockItem')->where('product_id', $this->recipeProductId)->get()
            : collect();

        return view('livewire.inventory-panel', [
            'items' => $items,
            'lowItems' => $items->filter->isLow()->values(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'recipe' => $recipe,
            'recentMovements' => StockMovement::with(['stockItem', 'user'])->latest('occurred_at')->limit(20)->get(),
            'currency' => Auth::user()->venueSettingsUser()->currencySymbol(),
        ]);
    }
}
