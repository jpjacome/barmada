<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Order') }}
        </h2>
    </x-slot>

    <link href="{{ asset('css/create-order.css') }}" rel="stylesheet">

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="order-container">
                <h1 class="order-title">Place Your Order</h1>
                
                @if ($errors->any())
                    <div class="error-container">
                        <ul class="error-list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <form method="POST" action="{{ route('orders.store') }}" class="order-form">
                    @csrf
                    
                    <!-- Table Selection -->
                    <div class="form-group">
                        <label for="table_id" class="form-label">Select Table</label>
                        <select name="table_id" id="table_id" class="form-select" {{ $selectedTableId ? 'disabled' : '' }}>
                            <option value="">-- Select a Table --</option>
                            @foreach ($tables as $table)
                                <option value="{{ $table->id }}" {{ $selectedTableId == $table->id ? 'selected' : '' }}>Table {{ $table->id }}</option>
                            @endforeach
                        </select>
                        @if ($tables->isEmpty())
                            <p class="form-error">No tables available. Please contact a staff member.</p>
                        @endif
                        @if($selectedTableId)
                            <input type="hidden" name="table_id" value="{{ $selectedTableId }}">
                        @endif
                    </div>
                    
                    <!-- Products List -->
                    <div class="products-section">
                        <h3 class="products-title">Select Products</h3>
                        
                        <div class="products-grid">
                            @foreach ($products as $product)
                                <div class="product-card">
                                    <div class="product-icon">
                                        @if($product->icon_type === 'bootstrap')
                                            <i class="{{ $product->icon_value ?? 'bi-box' }}"></i>
                                        @else
                                            <img src="{{ asset('storage/' . $product->icon_value) }}" alt="{{ $product->name }}">
                                        @endif
                                    </div>
                                    <label for="product_{{ $product->id }}" class="product-name">{{ $product->name }}</label>
                                    <span class="product-price">${{ number_format($product->price, 2) }}</span>
                                    <div class="quantity-controls">
                                        <button type="button" class="quantity-button minus" onclick="decrementQuantity('product_{{ $product->id }}')">-</button>
                                        <input 
                                            type="number" 
                                            name="products[{{ $product->id }}]" 
                                            id="product_{{ $product->id }}" 
                                            min="0" 
                                            value="0" 
                                            class="product-quantity"
                                            onchange="validateQuantity(this)"
                                        >
                                        <button type="button" class="quantity-button plus" onclick="incrementQuantity('product_{{ $product->id }}')">+</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        @if ($products->isEmpty())
                            <p class="form-error">No products available.</p>
                        @endif
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="form-actions">
                        <a href="{{ url('/') }}" class="back-link">
                            <svg xmlns="http://www.w3.org/2000/svg" class="back-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 12H5M12 19l-7-7 7-7"/>
                            </svg>
                            Back to Dashboard
                        </a>
                        <button type="submit" class="submit-button">
                            Place Order
                            <svg xmlns="http://www.w3.org/2000/svg" class="submit-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </form>
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
        }
    </script>
</x-app-layout> 