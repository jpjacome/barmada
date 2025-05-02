@extends('layouts.app')

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-semibold mb-4">Numbers</h3>
                    
                    <div class="space-y-2">
                        @forelse ($numbers as $number)
                            <div class="p-3 bg-gray-100 rounded">
                                <span class="font-semibold">{{ $number->value }}</span>
                                <span class="text-gray-500 text-sm ml-2">{{ $number->created_at->diffForHumans() }}</span>
                            </div>
                        @empty
                            <p class="text-gray-500">No numbers have been submitted yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex space-x-4">
                <a href="{{ route('numbers.create') }}" class="text-blue-500 hover:underline">
                    Submit a new number
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
@endsection