<div class="product-card {{ $product->is_available ? '' : 'product-sold-out' }}">
    <div class="product-container">
        <div class="product-icon">
            @if($product->icon_type === 'bootstrap')
                <i class="{{ $product->icon_value ?? 'bi-box' }}"></i>
            @else
                <img src="{{ asset('storage/' . $product->icon_value) }}" alt="{{ $product->name }}">
            @endif
            <!-- Info Button -->
            <button type="button" class="product-info-btn"
                onclick="showProductInfoModal(
                    @js($product->name),
                    @js($product->photo ? asset('storage/' . $product->photo) : asset('images/logo-light.png')),
                    @js($product->description ?: __('Description not available'))
                )"
                title="{{ __('View details') }}">
                <i class="bi bi-info-circle"></i>
            </button>
        </div>
        <div class="product-info">
            <label for="product_{{ $product->id }}" class="product-name">{{ $product->name }}</label>
            <span class="product-price">{{ $currency }}{{ number_format($product->price, 2) }}</span>
        </div>
    </div>
    @if($product->is_available)
    <div class="quantity-controls">
        <button type="button" class="quantity-button minus" onclick="decrementQuantity('product_{{ $product->id }}')">-</button>
        <input
            type="number"
            name="products[{{ $product->id }}]"
            id="product_{{ $product->id }}"
            min="0"
            value="0"
            class="product-quantity"
            data-price="{{ $product->price }}"
            data-name="{{ $product->name }}"
            onchange="validateQuantity(this)"
        >
        <button type="button" class="quantity-button plus" onclick="incrementQuantity('product_{{ $product->id }}')">+</button>
    </div>
    @else
    <div class="quantity-controls">
        <span class="sold-out-badge">{{ __('Sold out') }}</span>
    </div>
    @endif
</div>
