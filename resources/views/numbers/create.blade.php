<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Submit a Number') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if (session('success'))
                        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('numbers.store') }}">
                        @csrf
                        <div class="mb-4">
                            <label for="value" class="block text-gray-700 text-sm font-bold mb-2">Enter a Number:</label>
                            <input type="number" name="value" id="value" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                                required>
                            @error('value')
                                <p class="text-red-500 text-xs italic">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Send Number
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="mt-6 flex space-x-4">
                <a href="{{ route('numbers.index') }}" class="text-blue-500 hover:underline">
                    View all numbers
                </a>
                <a href="{{ route('numbers.live') }}" class="text-blue-500 hover:underline">
                    View with JS polling
                </a>
                <a href="{{ route('numbers.livewire') }}" class="text-blue-500 hover:underline">
                    View with Livewire
                </a>
            </div>
        </div>
    </div>
</x-app-layout> 