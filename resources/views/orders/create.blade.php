<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Order') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium mb-6">Place Your Order</h3>
                    
                    @if ($errors->any())
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    
                    <form method="POST" action="{{ route('orders.store') }}">
                        @csrf
                        
                        <!-- Table Selection -->
                        <div class="mb-6">
                            <label for="table_id" class="block text-sm font-medium text-gray-700">Select Table</label>
                            <select name="table_id" id="table_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Select a Table --</option>
                                @foreach ($tables as $table)
                                    <option value="{{ $table->id }}">Table {{ $table->id }}</option>
                                @endforeach
                            </select>
                            @if ($tables->isEmpty())
                                <p class="mt-2 text-sm text-red-600">No tables available. Please contact a staff member.</p>
                            @endif
                        </div>
                        
                        <!-- Products List -->
                        <div class="mb-6">
                            <h4 class="text-md font-medium mb-3">Select Products</h4>
                            
                            <div class="flex flex-wrap gap-4">
                                @foreach ($products as $product)
                                    <div class="p-4 border rounded-lg shadow-sm hover:shadow-md transition-shadow m-4 w-48 h-48 flex flex-col justify-between">
                                        <div class="flex flex-col items-center text-center mb-2">
                                            @if($product->icon_type === 'bootstrap')
                                                <i class="{{ $product->icon_value ?? 'bi-box' }} text-blue-600 text-3xl mb-2"></i>
                                            @else
                                                <img src="{{ asset('storage/' . $product->icon_value) }}" alt="{{ $product->name }}" class="w-10 h-10 object-contain mb-2">
                                            @endif
                                            <label for="product_{{ $product->id }}" class="font-medium text-lg">{{ $product->name }}</label>
                                            <span class="text-green-600 font-medium text-xl">${{ number_format($product->price, 2) }}</span>
                                        </div>
                                        <div class="flex items-center justify-center mt-auto">
                                            <input 
                                                type="number" 
                                                name="products[{{ $product->id }}]" 
                                                id="product_{{ $product->id }}" 
                                                min="0" 
                                                value="0" 
                                                class="w-20 py-2 px-3 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-center"
                                            >
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            
                            @if ($products->isEmpty())
                                <p class="mt-2 text-sm text-red-600">No products available.</p>
                            @endif
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Place Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <a href="{{ url('/') }}" class="text-blue-500 hover:underline">Back to Home</a>
            </div>
        </div>
    </div>
</x-app-layout> 