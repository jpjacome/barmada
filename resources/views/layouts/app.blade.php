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

        <!-- Styles -->
        <link href="{{ asset('css/general-' . (session('theme', 'light')) . '.css') }}" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
        
        <!-- Component-specific Styles -->
        <link href="{{ asset('css/navigation.css') }}" rel="stylesheet">
        <link href="{{ asset('css/settings.css') }}" rel="stylesheet">
        <link href="{{ asset('css/footer.css') }}" rel="stylesheet">
        
        @livewireStyles

        <!-- Alpine.js -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        
        <!-- Scripts -->
        <script src="{{ asset('js/app.js') }}" defer></script>
    </head>
    <body class="theme-{{ session('theme', 'light') }}">
        <div class="app-container">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="page-header">
                    <div class="page-header-content">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="main-content">
                {{ $slot }}
            </main>
            
            <!-- Footer -->
            <footer class="app-footer">
                <div class="app-footer-content">
                    <a href="https://github.com/jpjacome/barmada" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-github app-footer-icon"></i>GitHub
                    </a>
                    <span class="app-footer-separator">|</span>
                    <span>created by <a href="http://drpixel.it.nf" target="_blank" rel="noopener noreferrer">Dr. Pixel</a></span>
                </div>
            </footer>
        </div>
        
        @livewireScripts
        
        <!-- Stacked Scripts -->
        @stack('scripts')
        
        <!-- Application Scripts -->
        <script src="{{ asset('js/order-timer.js') }}"></script>
    </body>
</html>
