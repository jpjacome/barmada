<div class="products-container">
    <h2 class="products-title">{{ __('Inventory') }}</h2>

    @if($status)
        <div class="product-status-message" style="margin-bottom:1rem;">{{ $status }}</div>
    @endif

    @if($lowItems->isNotEmpty())
        <div class="product-status-message" style="margin-bottom:1rem;border-color:var(--color-danger);">
            <strong>{{ __('Low stock') }}:</strong>
            {{ $lowItems->map(fn ($i) => $i->name.' ('.rtrim(rtrim(number_format($i->quantity_on_hand, 3, '.', ''), '0'), '.').' '.$i->unit.')')->join(', ') }}
        </div>
    @endif

    {{-- On hand --}}
    <div class="products-table-container" style="margin-bottom:1.5rem;">
        <table class="products-table">
            <thead>
                <tr>
                    <th class="product-cell">{{ __('Item') }}</th>
                    <th class="product-cell">{{ __('On hand') }}</th>
                    <th class="product-cell">{{ __('Reorder at') }}</th>
                    <th class="product-cell">{{ __('Unit cost') }}</th>
                    <th class="product-cell">{{ __('Value') }}</th>
                    <th class="product-cell">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr wire:key="stock-{{ $item->id }}" @if($item->isLow()) style="color:var(--color-danger-text, var(--color-danger));" @endif>
                        <td class="product-cell product-name-cell"><span class="product-name">{{ $item->name }}</span> <span class="product-price">{{ $item->unit }}</span>@if(!$item->is_active) <em>({{ __('inactive') }})</em>@endif</td>
                        <td class="product-cell">{{ rtrim(rtrim(number_format($item->quantity_on_hand, 3, '.', ''), '0'), '.') }}</td>
                        <td class="product-cell">{{ $item->reorder_level !== null ? rtrim(rtrim(number_format($item->reorder_level, 3, '.', ''), '0'), '.') : '—' }}</td>
                        <td class="product-cell">{{ $currency }}{{ number_format($item->unit_cost, 4) }}</td>
                        <td class="product-cell">{{ $currency }}{{ number_format($item->quantity_on_hand * $item->unit_cost, 2) }}</td>
                        <td class="product-cell product-actions">
                            <button type="button" class="product-edit-button" wire:click="$set('movementItemId', {{ $item->id }})" aria-label="{{ __('Record movement') }}">{{ __('Move') }}</button>
                            @if(auth()->user()->is_editor || auth()->user()->is_admin)
                                <button type="button" class="product-delete-button" wire:click="toggleActive({{ $item->id }})">{{ $item->is_active ? __('Retire') : __('Restore') }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="product-cell product-empty-message" colspan="6">{{ __('No stock items yet. Add what you buy and count — bottles, kegs, limes.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.25rem;">
        {{-- Add item --}}
        <form wire:submit.prevent="addItem" class="product-form">
            <h3 class="products-subtitle">{{ __('New stock item') }}</h3>
            <div class="product-form-group">
                <label class="product-form-label" for="inv-name">{{ __('Name') }}</label>
                <input id="inv-name" type="text" class="product-form-input" wire:model="name" placeholder="{{ __('e.g. Gin (750 ml bottle)') }}">
                @error('name')<span class="product-form-error">{{ $message }}</span>@enderror
            </div>
            <div class="product-form-group">
                <label class="product-form-label" for="inv-unit">{{ __('Counted in') }}</label>
                <select id="inv-unit" class="product-form-input" wire:model="unit">
                    @foreach(\App\Models\StockItem::UNITS as $u)<option value="{{ $u }}">{{ $u }}</option>@endforeach
                </select>
            </div>
            <div class="product-form-group">
                <label class="product-form-label" for="inv-reorder">{{ __('Reorder when at or below') }}</label>
                <input id="inv-reorder" type="number" step="0.001" min="0" class="product-form-input" wire:model="reorderLevel">
                @error('reorderLevel')<span class="product-form-error">{{ $message }}</span>@enderror
            </div>
            <button type="submit" class="product-edit-button">{{ __('Add item') }}</button>
        </form>

        {{-- Movement --}}
        <form wire:submit.prevent="recordMovement" class="product-form">
            <h3 class="products-subtitle">{{ __('Record a movement') }}</h3>
            <div class="product-form-group">
                <label class="product-form-label" for="inv-mitem">{{ __('Item') }}</label>
                <select id="inv-mitem" class="product-form-input" wire:model="movementItemId">
                    <option value="">—</option>
                    @foreach($items->where('is_active', true) as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>@endforeach
                </select>
                @error('movementItemId')<span class="product-form-error">{{ $message }}</span>@enderror
            </div>
            <div class="product-form-group">
                <label class="product-form-label" for="inv-mtype">{{ __('Type') }}</label>
                <select id="inv-mtype" class="product-form-input" wire:model.live="movementType">
                    <option value="purchase">{{ __('Purchase (received)') }}</option>
                    <option value="waste">{{ __('Waste / breakage') }}</option>
                    <option value="count">{{ __('Physical count (set on-hand)') }}</option>
                    <option value="adjustment">{{ __('Adjustment (signed)') }}</option>
                </select>
            </div>
            <div class="product-form-group">
                <label class="product-form-label" for="inv-mqty">{{ $movementType === 'count' ? __('Counted quantity') : __('Quantity') }}</label>
                <input id="inv-mqty" type="number" step="0.001" class="product-form-input" wire:model="movementQty">
                @error('movementQty')<span class="product-form-error">{{ $message }}</span>@enderror
            </div>
            @if($movementType === 'purchase')
                <div class="product-form-group">
                    <label class="product-form-label" for="inv-mcost">{{ __('Cost per unit') }} ({{ $currency }})</label>
                    <input id="inv-mcost" type="number" step="0.0001" min="0" class="product-form-input" wire:model="movementCost">
                </div>
            @endif
            <div class="product-form-group">
                <label class="product-form-label" for="inv-mnote">{{ __('Note') }}</label>
                <input id="inv-mnote" type="text" class="product-form-input" wire:model="movementNote" maxlength="255">
            </div>
            <button type="submit" class="product-edit-button">{{ __('Record') }}</button>
        </form>

        {{-- Recipe --}}
        <form wire:submit.prevent="addComponent" class="product-form">
            <h3 class="products-subtitle">{{ __('Recipes') }}</h3>
            <p class="product-bootstrap-icon-help">{{ __('What one sold unit consumes. Depletion happens when the order is delivered.') }}</p>
            <div class="product-form-group">
                <label class="product-form-label" for="inv-rproduct">{{ __('Product') }}</label>
                <select id="inv-rproduct" class="product-form-input" wire:model.live="recipeProductId">
                    <option value="">—</option>
                    @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                </select>
            </div>
            @if($recipeProductId)
                <ul style="list-style:none;padding:0;margin:0 0 0.75rem;">
                    @forelse($recipe as $component)
                        <li wire:key="component-{{ $component->id }}" style="display:flex;justify-content:space-between;gap:0.5rem;padding:0.25rem 0;">
                            <span>{{ rtrim(rtrim(number_format($component->quantity_per_unit, 4, '.', ''), '0'), '.') }} {{ $component->stockItem?->unit }} · {{ $component->stockItem?->name }}</span>
                            @if(auth()->user()->is_editor || auth()->user()->is_admin)
                                <button type="button" class="product-delete-button" wire:click="removeComponent({{ $component->id }})" aria-label="{{ __('Remove') }}">×</button>
                            @endif
                        </li>
                    @empty
                        <li class="product-empty-message">{{ __('No recipe yet — this product does not deplete stock.') }}</li>
                    @endforelse
                </ul>
                <div class="product-form-group">
                    <label class="product-form-label" for="inv-ritem">{{ __('Stock item') }}</label>
                    <select id="inv-ritem" class="product-form-input" wire:model="recipeStockItemId">
                        <option value="">—</option>
                        @foreach($items->where('is_active', true) as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>@endforeach
                    </select>
                    @error('recipeStockItemId')<span class="product-form-error">{{ $message }}</span>@enderror
                </div>
                <div class="product-form-group">
                    <label class="product-form-label" for="inv-rqty">{{ __('Quantity per sold unit') }}</label>
                    <input id="inv-rqty" type="number" step="0.0001" min="0" class="product-form-input" wire:model="recipeQty">
                    @error('recipeQty')<span class="product-form-error">{{ $message }}</span>@enderror
                </div>
                <button type="submit" class="product-edit-button">{{ __('Add to recipe') }}</button>
            @endif
        </form>
    </div>

    {{-- Recent movements --}}
    <h3 class="products-subtitle" style="margin-top:1.5rem;">{{ __('Recent movements') }}</h3>
    <div class="products-table-container">
        <table class="products-table">
            <thead>
                <tr>
                    <th class="product-cell">{{ __('When') }}</th>
                    <th class="product-cell">{{ __('Item') }}</th>
                    <th class="product-cell">{{ __('Type') }}</th>
                    <th class="product-cell">{{ __('Qty') }}</th>
                    <th class="product-cell">{{ __('By') }}</th>
                    <th class="product-cell">{{ __('Note') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentMovements as $m)
                    <tr wire:key="mv-{{ $m->id }}">
                        <td class="product-cell">{{ \App\Support\VenueClock::format(\App\Support\VenueClock::venueFor(auth()->user()), $m->occurred_at, 'd/m H:i') }}</td>
                        <td class="product-cell">{{ $m->stockItem?->name }}</td>
                        <td class="product-cell">{{ __(ucfirst($m->type)) }}</td>
                        <td class="product-cell">{{ ($m->quantity > 0 ? '+' : '') . rtrim(rtrim(number_format($m->quantity, 3, '.', ''), '0'), '.') }} {{ $m->stockItem?->unit }}</td>
                        <td class="product-cell">{{ $m->user?->first_name ?: $m->user?->name ?: '—' }}</td>
                        <td class="product-cell">{{ $m->note }}</td>
                    </tr>
                @empty
                    <tr><td class="product-cell product-empty-message" colspan="6">{{ __('No movements yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
