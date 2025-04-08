<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Live Numbers with Livewire') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @livewire('numbers-list')
                </div>
            </div>
            
            <div class="mt-6">
                <a href="{{ route('numbers.create') }}" class="text-blue-500 hover:underline">
                    Submit a new number
                </a>
                |
                <a href="{{ route('numbers.index') }}" class="text-blue-500 hover:underline">
                    Regular Numbers View
                </a>
                |
                <a href="{{ route('numbers.live') }}" class="text-blue-500 hover:underline">
                    JavaScript Polling View
                </a>
            </div>
        </div>
    </div>
</x-app-layout> 