<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Barmada - Bar Management Dashboard</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <!-- General Styles -->
        <link href="{{ asset('css/general-' . (session('theme', 'light')) . '.css') }}" rel="stylesheet">
        
        <!-- Component-specific Styles -->
        <link href="{{ asset('css/navigation.css') }}" rel="stylesheet">
        <link href="{{ asset('css/settings.css') }}" rel="stylesheet">
        
        <!-- Livewire Styles -->
        @livewireStyles

        <!-- Alpine.js -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    </head>
    <body class="font-sans antialiased theme-{{ session('theme', 'light') }}">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
        
        <!-- Livewire Scripts -->
        @livewireScripts
        
        <!-- Stacked Scripts -->
        @stack('scripts')
        
        <!-- Application Scripts -->
        <script src="{{ asset('js/app.js') }}"></script>
        <script src="{{ asset('js/order-timer.js') }}"></script>
    </body>
</html>
