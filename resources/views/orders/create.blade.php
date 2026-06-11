@extends('layouts.app')

@section('content')
<link href="{{ asset('css/create-order.css') }}" rel="stylesheet">

@php
    // Use the currentEditorId passed from the controller, fallback to the first product's editor_id if needed
    $currentEditorId = $currentEditorId ?? ($products->first()->editor_id ?? null);
    // Venue currency symbol (set by the controller; defensive fallback).
    $currency = $currency ?? '$';
@endphp

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="order-container">
            <h1 class="order-title">{{ __('Place Order') }}</h1>

            @if ($errors->any())
                <div class="error-container">
                    <ul class="error-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ isset($unique_token) ? route('order.guest.store', ['unique_token' => $unique_token]) : route('orders.store') }}" class="order-form" id="order-form">
                @csrf

                <!-- Table selection -->
                <div class="form-group">
                    <select name="table_id" id="table_id" class="form-select" {{ $selectedTableId ? 'disabled' : '' }}>
                        <option value="">{{ __('-- Select a Table --') }}</option>
                        @foreach ($tables as $table)
                            <option value="{{ $table->id }}" {{ $selectedTableId == $table->id ? 'selected' : '' }}>{{ __('Table') }} {{ $table->table_number }}</option>
                        @endforeach
                    </select>
                    @if ($tables->isEmpty())
                        <p class="form-error">{{ __('No tables available. Please contact a staff member.') }}</p>
                    @endif
                    @if($selectedTableId)
                        <input type="hidden" name="table_id" value="{{ $selectedTableId }}">
                    @endif
                </div>

                <!-- Product list -->
                <div class="products-section">
                    <h3 class="products-title">{{ __('Select Products') }}</h3>

                    @php
                        // Group products by category. Products without a category
                        // fall into a sentinel group so they are ALWAYS rendered
                        // (previously they silently disappeared from the menu). [F-2]
                        $uncategorizedKey = '__uncategorized__';
                        $sortedCategories = $currentEditorId
                            ? \App\Models\Category::where('editor_id', $currentEditorId)->orderBy('sort_order')->get()
                            : collect();

                        // Only include products whose category belongs to the current editor or is uncategorized
                        $categoryIds = $sortedCategories->pluck('id')->all();
                        $filteredProducts = $products->filter(function ($product) use ($categoryIds) {
                            return is_null($product->category_id) || in_array($product->category_id, $categoryIds);
                        });
                        $groupedProducts = $filteredProducts->groupBy(function ($product) use ($uncategorizedKey) {
                            return $product->category->name ?? $uncategorizedKey;
                        });
                    @endphp

                    @foreach ($sortedCategories as $category)
                        @if (isset($groupedProducts[$category->name]))
                            <div class="category-section">
                                <h2 class="category-title">{{ $category->name }}</h2>

                                <div class="products-grid">
                                    @foreach ($groupedProducts[$category->name] as $product)
                                        @include('orders.partials.product-card', ['product' => $product, 'currency' => $currency])
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                    {{-- Products without a category are shown in their own group. [F-2] --}}
                    @if (isset($groupedProducts[$uncategorizedKey]) && $groupedProducts[$uncategorizedKey]->isNotEmpty())
                        <div class="category-section">
                            <h2 class="category-title">{{ __('Others') }}</h2>
                            <div class="products-grid">
                                @foreach ($groupedProducts[$uncategorizedKey] as $product)
                                    @include('orders.partials.product-card', ['product' => $product, 'currency' => $currency])
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($products->isEmpty())
                        <p class="form-error">{{ __('No products available.') }}</p>
                    @endif
                </div>

                <!-- Sticky cart bar: running total + review step [M-3a] -->
                <div class="cart-bar" id="cart-bar">
                    <div class="cart-bar-info">
                        <span class="cart-bar-items">{{ __('Items') }}: <strong id="cart-count">0</strong></span>
                        <span class="cart-bar-total">{{ __('Total') }}: <strong id="cart-total">{{ $currency }}0.00</strong></span>
                    </div>
                    <button type="button" class="submit-button" id="cart-review-btn" disabled>
                        {{ __('Review order') }}
                        <svg xmlns="http://www.w3.org/2000/svg" class="submit-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Product Info Modal -->
<div id="product-info-modal" class="product-info-modal">
    <div class="product-info-modal-content">
        <button onclick="closeProductInfoModal()" class="product-info-modal-close"><i class="bi bi-x"></i></button>
        <h2 id="modal-product-name"></h2>
        <img id="modal-product-image" src="" alt="Product Image" />
        <div id="modal-product-description"></div>
    </div>
</div>

<!-- Order Review Modal [M-3a] -->
<div id="order-review-modal" class="product-info-modal">
    <div class="product-info-modal-content order-review-content">
        <h2>{{ __('Your order') }}</h2>
        <ul id="order-review-list" class="order-review-list"></ul>
        <div class="order-review-total">{{ __('Total') }}: <strong id="order-review-total"></strong></div>
        <div class="order-review-actions">
            <button type="button" id="review-cancel" class="review-cancel-btn">{{ __('Cancel') }}</button>
            <button type="button" id="review-confirm" class="submit-button">{{ __('Confirm order') }}</button>
        </div>
    </div>
</div>

<script>
    function incrementQuantity(id) {
        const input = document.getElementById(id);
        input.value = parseInt(input.value) + 1;
        validateQuantity(input);
    }

    function decrementQuantity(id) {
        const input = document.getElementById(id);
        input.value = Math.max(0, parseInt(input.value) - 1);
        validateQuantity(input);
    }

    function validateQuantity(input) {
        if (input.value < 0) input.value = 0;
        updateCartBar();
    }

    function showProductInfoModal(name, photo, description) {
        document.getElementById('modal-product-name').textContent = name;
        var img = document.getElementById('modal-product-image');
        if(photo) {
            img.src = photo;
            img.style.display = 'block';
        } else {
            img.src = "{{ asset('images/logo1.png') }}";
            img.style.display = 'block';
        }
        document.getElementById('modal-product-description').textContent = description || @js(__('Description not available'));
        document.getElementById('product-info-modal').classList.add('active');
    }

    function closeProductInfoModal() {
        document.getElementById('product-info-modal').classList.remove('active');
    }

    // ---- Cart bar + review step ----
    const CURRENCY = @js($currency);

    function cartItems() {
        const items = [];
        document.querySelectorAll('.product-quantity').forEach(function (input) {
            const qty = parseInt(input.value) || 0;
            if (qty > 0) {
                items.push({
                    name: input.dataset.name,
                    price: parseFloat(input.dataset.price) || 0,
                    qty: qty
                });
            }
        });
        return items;
    }

    function updateCartBar() {
        const items = cartItems();
        const count = items.reduce(function (sum, item) { return sum + item.qty; }, 0);
        const total = items.reduce(function (sum, item) { return sum + item.qty * item.price; }, 0);
        document.getElementById('cart-count').textContent = count;
        document.getElementById('cart-total').textContent = CURRENCY + total.toFixed(2);
        document.getElementById('cart-review-btn').disabled = count === 0;
    }

    function openReviewModal() {
        const items = cartItems();
        if (items.length === 0) return;
        const list = document.getElementById('order-review-list');
        list.innerHTML = '';
        let total = 0;
        items.forEach(function (item) {
            total += item.qty * item.price;
            const li = document.createElement('li');
            const label = document.createElement('span');
            label.textContent = item.qty + ' × ' + item.name;
            const price = document.createElement('span');
            price.textContent = CURRENCY + (item.qty * item.price).toFixed(2);
            li.appendChild(label);
            li.appendChild(price);
            list.appendChild(li);
        });
        document.getElementById('order-review-total').textContent = CURRENCY + total.toFixed(2);
        document.getElementById('order-review-modal').classList.add('active');
    }

    document.getElementById('cart-review-btn').addEventListener('click', openReviewModal);
    document.getElementById('review-cancel').addEventListener('click', function () {
        document.getElementById('order-review-modal').classList.remove('active');
    });
    document.getElementById('review-confirm').addEventListener('click', function () {
        document.getElementById('order-form').submit();
    });
    document.querySelectorAll('.product-quantity').forEach(function (input) {
        input.addEventListener('input', updateCartBar);
        input.addEventListener('change', updateCartBar);
    });
    updateCartBar();

    // Close modal on click or touch outside modal content
    function handleOutsideModal(e) {
        [['product-info-modal', '.product-info-modal-content'], ['order-review-modal', '.order-review-content']].forEach(function (pair) {
            var modal = document.getElementById(pair[0]);
            if (modal.classList.contains('active')) {
                var modalContent = modal.querySelector(pair[1]);
                if (!modalContent.contains(e.target)) {
                    modal.classList.remove('active');
                }
            }
        });
    }
    window.addEventListener('click', handleOutsideModal);
    window.addEventListener('touchstart', handleOutsideModal);

    // Add this to prevent modal from closing immediately after opening
    const infoButtons = document.querySelectorAll('.product-info-btn');
    infoButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    document.getElementById('cart-review-btn').addEventListener('click', function(e) { e.stopPropagation(); });

    // Ensure close button does not propagate event
    document.querySelector('.product-info-modal-close').addEventListener('click', function(e) {
        e.stopPropagation();
        closeProductInfoModal();
    });
</script>
@endsection
