@extends('layouts.app')

@section('content')
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
                <a href="{{ route('dashboard') }}" class="text-blue-500 hover:underline">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
@endsection