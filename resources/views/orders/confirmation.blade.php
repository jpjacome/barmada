<x-app-layout>
<link href="{{ asset('css/confirmation.css') }}" rel="stylesheet">

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Confirmación de Pedido') }}
        </h2>
    </x-slot>


    <div class="confirmation-container py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if (session('success'))
                        <div class="success-alert" role="alert">
                            <p class="success-alert-title">¡Éxito!</p>
                            <p class="success-alert-message">{{ session('success') }}</p>
                        </div>
                    @endif
                    
                    <div class="confirmation-card">
                        <svg xmlns="http://www.w3.org/2000/svg" class="success-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        
                        <h3 class="confirmation-title">¡Gracias!</h3>
                        <p class="confirmation-message">Tu pedido ha sido recibido y será procesado en breve.</p>
                        
                        <div class="confirmation-details">
                            <p>Un miembro del personal servirá tu pedido lo antes posible.</p>
                            <p>Por favor, permanece en tu mesa.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>