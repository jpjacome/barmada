<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <!-- Help identify errors -->
        <meta name="public-url-check" content="{{ url('/') }}">
        <!-- For Livewire asset URL -->
        <meta name="livewire-asset-path" content="{{ asset('/') }}">

        <title>Barmada - Bar Management Dashboard</title>

        <!-- Script interceptor - load first to catch all problematic scripts -->
        <script src="{{ asset('script-interceptor.js') }}"></script>
        
        <!-- Debug mode check -->
        @if(request()->has('debug'))
        <script>
            console.log('Debug mode activated');
            window.DEBUG_MODE = true;
        </script>
        @endif

        <!-- No base tag needed - using relative URLs -->
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <!-- Styles - using relative paths that are confirmed working -->
        <link href="{{ asset('css/general-' . session('theme', 'light') . '.css') }}" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
        
        <!-- Component-specific Styles -->
        <link href="{{ asset('css/navigation.css') }}" rel="stylesheet">
        <link href="{{ asset('css/settings.css') }}" rel="stylesheet">
        <link href="{{ asset('css/footer.css') }}" rel="stylesheet">
        
        <!-- Livewire Styles -->
        @livewireStyles
        {{-- DEBUG: show APP_URL the framework is using --}}
        {!! '<!-- APP_URL = '.e(config('app.url')).' -->' !!}

        <!-- Scripts -->
        <script>
            // Define the base URL for assets - using the asset helper
            window.assetBaseUrl = "{{ asset('') }}";
            if (window.DEBUG_MODE) {
                console.log('Asset base URL:', window.assetBaseUrl);
                console.log('Current location:', window.location.href);
                console.log('Meta public URL:', document.querySelector('meta[name="public-url-check"]').content);
            }
            
            // Handle the "public" script error
            window.addEventListener('error', function(e) {
                if (e.filename && e.filename.includes('public') && e.message && e.message.includes('Unexpected token')) {
                    console.error('Detected error loading "public" script. This is likely caused by Livewire URL issues.');
                    if (window.DEBUG_MODE) {
                        alert('Detected issue with script loading. Check console for details.');
                    }
                }
            }, true);
        </script>
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
        
        
        <!-- Stacked Scripts -->
        @stack('scripts')
        
        <!-- Application Scripts -->
        
        
        @fixedLivewireScripts   {{-- our new directive from AppServiceProvider --}}
    </body>
</html>
