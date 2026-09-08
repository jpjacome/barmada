<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@hasSection('title')@yield('title') - Barmada@else{{ __('Barmada - Bar Management Dashboard') }}@endif</title>
        @hasSection('meta_description')
            <meta name="description" content="@yield('meta_description')">
        @endif
        @yield('head_extra')

        {{-- Fonts: one CDN, the two brand families only. Figtree was Breeze
             scaffolding the design system retired; Google's "Inter" was a
             second copy of a font nothing referenced. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=crimson-text:400,600,700|inter-tight:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <!-- Styles - using relative paths that are confirmed working -->
        <link href="{{ asset('css/general-' . session('theme', 'light') . '.css') }}" rel="stylesheet">
        @if (!request()->is('/'))
            <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
        @endif
        <!-- Component-specific Styles -->
        <link href="{{ asset('css/navigation.css') }}" rel="stylesheet">
        <link href="{{ asset('css/settings.css') }}" rel="stylesheet">
        <link href="{{ asset('css/footer.css') }}" rel="stylesheet">
        <link href="{{ asset('css/analytics.css') }}" rel="stylesheet">
        
        <!-- Livewire Styles -->
        @livewireStyles
    </head>
    <body class="theme-{{ session('theme', 'light') }}">
        @if (request()->is('/'))
            @include('layouts.navigation')
            @yield('content')
        @else
        <div class="app-container">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @hasSection('header')
                <header class="page-header">
                    <div class="page-header-content">
                        @yield('header')
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="main-content">
                @yield('content')
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
        @endif
        
        <!-- Stacked Scripts -->
        @stack('scripts')
        
        <!-- Application Scripts -->
        <script src="{{ asset('js/order-timer.js') }}"></script>

        {{-- Plain @livewireScripts. The old @fixedLivewireScripts directive
             was a Blade::directive, which runs at COMPILE time: it froze
             asset() and csrf_token() into the cached view, so a layout
             compiled on one host (a CLI test run, a deploy script) served
             that host's Livewire URL to every page until view:clear. It
             existed for a sub-folder deployment that no longer exists. --}}
        @livewireScripts
    </body>
</html>
