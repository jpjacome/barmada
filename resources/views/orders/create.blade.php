@extends('layouts.app')

@section('content')
<link href="{{ asset('css/create-order.css') }}" rel="stylesheet">

@php
    // Use the currentEditorId passed from the controller, fallback to the first product's editor_id if needed
    $currentEditorId = $currentEditorId ?? ($products->first()->editor_id ?? null);
@endphp

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
            
            <form method="POST" action="{{ isset($unique_token) ? route('order.guest.store', ['unique_token' => $unique_token]) : route('orders.store') }}" class="order-form">
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
                        $filteredProducts = $products;
                        $groupedProducts = $filteredProducts->groupBy(function ($product) {
                            return $product->category->name ?? 'Sin Categoría';
                        });
                        $sortedCategories = $currentEditorId
                            ? \App\Models\Category::where('editor_id', $currentEditorId)->orderBy('sort_order')->get()
                            : collect();

                        // Only include products whose category belongs to the current editor or is uncategorized
                        $categoryIds = $sortedCategories->pluck('id')->all();
                        $filteredProducts = $products->filter(function ($product) use ($categoryIds) {
                            return is_null($product->category_id) || in_array($product->category_id, $categoryIds);
                        });
                        $groupedProducts = $filteredProducts->groupBy(function ($product) {
                            return $product->category->name ?? 'Sin Categoría';
                        });
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
                                                    <!-- Info Button -->
                                                    <button type="button" class="product-info-btn"
                                                        onclick="showProductInfoModal(
                                                            @js($product->name),
                                                            @js($product->photo ? asset('storage/' . $product->photo) : asset('images/logo-light.png')),
                                                            @js($product->description ?: 'photo not available')
                                                        )"
                                                        title="Ver detalles">
                                                        <i class="bi bi-info-circle"></i>
                                                    </button>
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

<!-- Product Info Modal -->
<div id="product-info-modal" class="product-info-modal">
    <div class="product-info-modal-content">
        <button onclick="closeProductInfoModal()" class="product-info-modal-close"><i class="bi bi-x"></i></button>
        <h2 id="modal-product-name"></h2>
        <img id="modal-product-image" src="" alt="Product Image" />
        <div id="modal-product-description"></div>
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
        document.getElementById('modal-product-description').textContent = description || 'Photo not available';
        document.getElementById('product-info-modal').classList.add('active');
    }

    function closeProductInfoModal() {
        document.getElementById('product-info-modal').classList.remove('active');
    }

    // Close modal on click or touch outside modal content
    function handleOutsideModal(e) {
        var modal = document.getElementById('product-info-modal');
        var modalContent = document.querySelector('.product-info-modal-content');
        if (modal.classList.contains('active')) {
            if (!modalContent.contains(e.target)) {
                closeProductInfoModal();
            }
        }
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

    // Ensure close button does not propagate event
    document.querySelector('.product-info-modal-close').addEventListener('click', function(e) {
        e.stopPropagation();
        closeProductInfoModal();
    });
</script>
@endsection