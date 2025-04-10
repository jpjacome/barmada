<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Order Confirmation') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if (session('success'))
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                            <p class="font-bold">Success!</p>
                            <p>{{ session('success') }}</p>
                        </div>
                    @endif
                    
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-green-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        
                        <h3 class="text-2xl font-bold mb-2">Thank You!</h3>
                        <p class="text-lg mb-6">Your order has been received and will be processed shortly.</p>
                        
                        <div class="mb-6">
                            <p class="text-gray-600">A staff member will serve your order as soon as possible.</p>
                            <p class="text-gray-600">Please remain at your table.</p>
                        </div>
                        
                        <div class="flex justify-center space-x-4">
                            <a href="{{ route('orders.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Place Another Order
                            </a>
                            <a href="{{ url('/') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                                Return to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 