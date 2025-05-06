@extends('layouts.app')

@section('content')
<link href="{{ asset('css/create-order.css') }}" rel="stylesheet">

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="order-container">
            <h1 class="order-title">Realizar Pedido</h1>
            
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
                
                <!-- Selección de Mesa -->
                <div class="form-group">
                    <select name="table_id" id="table_id" class="form-select" {{ $selectedTableId ? 'disabled' : '' }}>
                        <option value="">-- Selecciona una Mesa --</option>
                        @foreach ($tables as $table)
                            <option value="{{ $table->id }}" {{ $selectedTableId == $table->id ? 'selected' : '' }}>Mesa {{ $table->table_number }}</option>
                        @endforeach
                    </select>
                    @if ($tables->isEmpty())
                        <p class="form-error">No hay mesas disponibles. Por favor, contacta a un miembro del personal.</p>
                    @endif
                    @if($selectedTableId)
                        <input type="hidden" name="table_id" value="{{ $selectedTableId }}">
                    @endif
                </div>
                
                <!-- Lista de Productos -->
                <div class="products-section">
                    <h3 class="products-title">Seleccionar Productos</h3>
                    
                    @php
                        // Agrupar productos por categoría y ordenar categorías por sort_order
                        $groupedProducts = $products->groupBy(function ($product) {
                            return $product->category->name ?? 'Sin Categoría';
                        });

                        // Obtener categorías y ordenarlas por sort_order
                        $sortedCategories = \App\Models\Category::orderBy('sort_order')->get();
                    @endphp

                    @foreach ($sortedCategories as $category)
                        @if (isset($groupedProducts[$category->name]))
                            <div class="category-section">
                                <h2 class="category-title">{{ $category->name }}</h2>
                                
                                <div class="products-grid">
                                    @foreach ($groupedProducts[$category->name] as $product)
                                        <div class="product-card">
                                            <div class="product-container">
                                                <div class="product-icon">
                                                    @if($product->icon_type === 'bootstrap')
                                                        <i class="{{ $product->icon_value ?? 'bi-box' }}"></i>
                                                    @else
                                                        <img src="{{ asset('storage/' . $product->icon_value) }}" alt="{{ $product->name }}">
                                                    @endif
                                                </div>
                                                <div class="product-info">
                                                    <label for="product_{{ $product->id }}" class="product-name">{{ $product->name }}</label>
                                                    <span class="product-price">${{ number_format($product->price, 2) }}</span>
                                                </div>
                                            </div>
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
                            </div>
                        @endif
                    @endforeach

                    @if ($products->isEmpty())
                        <p class="form-error">No hay productos disponibles.</p>
                    @endif
                </div>
                
                <!-- Botón de Enviar -->
                <div class="form-actions">
                    <button type="submit" class="submit-button">
                        Realizar Pedido
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
@endsection