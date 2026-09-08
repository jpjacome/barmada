<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            // The guest pages have no operator navigation and no admin
            // "dashboard" branding — just the venue's own name where the
            // caller has it (table/session/order controllers), falling
            // back to the product name.
            $__guestVenueName = $venueName
                ?? (isset($table) && $table && $table->editor
                    ? ($table->editor->business_name ?: $table->editor->name)
                    : null);
        @endphp

        <title>{{ $__guestVenueName ?: 'Barmada' }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|crimson-text:400,600,700|inter-tight:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Icons (used throughout the guest flow: receipt, chat, plus icons) -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <!-- Theme styles (same variables the operator layout uses) -->
        <link href="{{ asset('css/general-' . session('theme', 'light') . '.css') }}" rel="stylesheet">
        <link href="{{ asset('css/footer.css') }}" rel="stylesheet">

        <!-- Per-page styles -->
        @yield('styles')
    </head>
    <body class="theme-{{ session('theme', 'light') }} guest-order-body">
        <header class="guest-order-header" style="padding: var(--spacing-4, 1rem) var(--spacing-6, 1.5rem); text-align: center;">
            <span class="guest-order-venue-name" style="font-weight: var(--font-weight-bold, 700); letter-spacing: 0.04em; color: var(--color-accents, #555); text-transform: uppercase; font-size: var(--text-sm, 0.9rem);">
                {{ $__guestVenueName ?: 'Barmada' }}
            </span>
        </header>

        <main class="guest-order-main">
            @yield('content')
        </main>

        <footer class="guest-order-footer" style="text-align:center; padding: var(--spacing-4, 1rem); color: var(--color-accents2, #999); font-size: var(--text-xs, 0.75rem);">
            {{ __('Powered by Barmada') }}
        </footer>

        @stack('scripts')
    </body>
</html>
