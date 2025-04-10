<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h2 class="text-2xl font-bold mb-6">Golems Bar Management</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Tables Management Card -->
                        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200 hover:border-blue-500 transition-colors duration-200">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                                        <i class="bi bi-table" style="font-size: 2rem;"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-semibold">Tables</h3>
                                        <p class="text-gray-500">Manage your bar tables</p>
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <a href="{{ route('tables.index') }}" class="text-blue-500 hover:text-blue-700 font-semibold">
                                        Manage Tables →
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Products Card -->
                        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200 hover:border-blue-500 transition-colors duration-200">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                                        <i class="bi bi-shop" style="font-size: 2rem;"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-semibold">Products</h3>
                                        <p class="text-gray-500">Manage your product catalog</p>
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <a href="{{ route('products.index') }}" class="text-blue-500 hover:text-blue-700 font-semibold">
                                        Manage Products →
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Orders Card -->
                        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200 hover:border-blue-500 transition-colors duration-200">
                            <div class="p-6">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-green-100 text-green-500">
                                        <i class="bi bi-cart" style="font-size: 2rem;"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-semibold">Orders</h3>
                                        <p class="text-gray-500">Manage customer orders</p>
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <a href="{{ route('orders.create') }}" class="text-blue-500 hover:text-blue-700 font-semibold">
                                        Place Order →
                                    </a>
                                    <span class="mx-2 text-gray-400">|</span>
                                    <a href="{{ route('orders.index') }}" class="text-blue-500 hover:text-blue-700 font-semibold">
                                        View All Orders →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8 p-4 bg-blue-50 rounded-lg">
                        <h3 class="font-semibold text-blue-800">Development Notes</h3>
                        <p class="text-blue-600">
                            This application is currently in development. Table management is available, with menu and order functionality coming soon.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
