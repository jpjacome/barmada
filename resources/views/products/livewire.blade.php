<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products') }}
        </h2>
    </x-slot>

    <!-- Use Bootstrap Icons which is already included in the main layout -->

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @livewire('products-list')
                </div>
            </div>
            
            <div class="mt-6">
                <a href="{{ route('dashboard') }}" class="text-blue-500 hover:underline">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</x-app-layout> 