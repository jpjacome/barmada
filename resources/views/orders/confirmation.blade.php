@extends('layouts.app')

@section('content')
<link href="{{ asset('css/confirmation.css') }}" rel="stylesheet">
<div class="confirmation-container py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                @if (session('success'))
                    <div class="success-alert" role="alert">
                        <p class="success-alert-title">{{ __('Success!') }}</p>
                        <p class="success-alert-message">{{ __(session('success')) }}</p>
                    </div>
                @endif
                <div class="confirmation-card">
                    <svg xmlns="http://www.w3.org/2000/svg" class="success-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <h3 class="confirmation-title">{{ __('Thank you!') }}</h3>
                    <p class="confirmation-message">
                        @if(!empty($tableNumber))
                            {{ __('Order received for Table :number.', ['number' => $tableNumber]) }}
                        @else
                            {{ __('Your order has been received and will be processed shortly.') }}
                        @endif
                    </p>
                    <div class="confirmation-details">
                        <p>{{ __('A staff member will serve your order as soon as possible.') }}</p>
                        <p>{{ __('Please remain at your table.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
