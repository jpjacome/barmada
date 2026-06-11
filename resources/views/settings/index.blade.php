@extends('layouts.app')

@section('content')
<link href="{{ asset('css/settings.css') }}" rel="stylesheet">
<div class="settings-container">
    <div class="settings-main">
        <div class="settings-header">
            <div>
                <h2 class="settings-title">{{ __('Settings') }}</h2>
                <p class="settings-subtitle">{{ __('Manage your application preferences and appearance.') }}</p>
            </div>
        </div>
        @if (session('success'))
            <div class="settings-success-message">
                <i class="settings-message-icon bi bi-check-circle"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="settings-error-message">
                <i class="settings-message-icon bi bi-exclamation-circle"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif
        <div class="settings-section">
            <h3 class="settings-section-title">Theme Settings</h3>
            <div class="settings-card">
                <div class="settings-theme-container">
                    <span class="settings-form-label">Current Theme: {{ session('theme', 'light') === 'light' ? 'Light' : 'Dark' }}</span>
                    <form action="{{ route('settings.toggle-theme') }}" method="POST" class="settings-theme-form">
                        @csrf
                        <button type="submit" class="settings-button">
                            Switch to {{ session('theme', 'light') === 'light' ? 'Dark' : 'Light' }} Theme
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @if(auth()->user() && auth()->user()->is_editor)
        <div class="settings-section">
            <h3 class="settings-section-title">Business Settings</h3>
            <div class="settings-card">
                <p class="settings-card-description">Currency and language used on your guest (QR) ordering pages, payment screens, analytics and exports.</p>
                <form action="{{ route('settings.update-business') }}" method="POST" class="settings-form">
                    @csrf
                    <div class="settings-form-group">
                        <label for="currency_symbol" class="settings-form-label">Currency symbol</label>
                        <select name="currency_symbol" id="currency_symbol" class="settings-form-input">
                            @foreach(['$', '€', '£', 'S/', 'Bs.', 'Q', '₡', 'MX$', 'COP$', 'AR$'] as $symbol)
                                <option value="{{ $symbol }}" {{ auth()->user()->currencySymbol() === $symbol ? 'selected' : '' }}>{{ $symbol }}</option>
                            @endforeach
                        </select>
                        @error('currency_symbol')
                            <p class="settings-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="settings-form-group">
                        <label for="locale" class="settings-form-label">Guest menu language</label>
                        <select name="locale" id="locale" class="settings-form-input">
                            <option value="es" {{ auth()->user()->guestLocale() === 'es' ? 'selected' : '' }}>Español</option>
                            <option value="en" {{ auth()->user()->guestLocale() === 'en' ? 'selected' : '' }}>English</option>
                        </select>
                        @error('locale')
                            <p class="settings-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="settings-form-group">
                        <label for="business_timezone" class="settings-form-label">Venue timezone</label>
                        @php
                            $commonTimezones = [
                                'UTC', 'America/Guayaquil', 'America/Bogota', 'America/Lima', 'America/Mexico_City',
                                'America/Argentina/Buenos_Aires', 'America/Santiago', 'America/Sao_Paulo', 'America/Caracas',
                                'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
                                'Europe/Madrid', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Europe/Rome',
                                'Europe/Lisbon', 'Atlantic/Canary',
                            ];
                            $currentTz = auth()->user()->businessTimezone();
                            if (! in_array($currentTz, $commonTimezones)) {
                                array_unshift($commonTimezones, $currentTz);
                            }
                        @endphp
                        <select name="business_timezone" id="business_timezone" class="settings-form-input">
                            @foreach($commonTimezones as $tz)
                                <option value="{{ $tz }}" {{ $currentTz === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                            @endforeach
                        </select>
                        <p class="settings-form-helper">Used to bucket your analytics by your local clock.</p>
                        @error('business_timezone')
                            <p class="settings-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="settings-form-group">
                        <label for="day_cutoff_hour" class="settings-form-label">Business day starts at</label>
                        <select name="day_cutoff_hour" id="day_cutoff_hour" class="settings-form-input">
                            @for($hour = 0; $hour <= 12; $hour++)
                                <option value="{{ $hour }}" {{ auth()->user()->dayCutoffHour() === $hour ? 'selected' : '' }}>{{ sprintf('%02d:00', $hour) }}</option>
                            @endfor
                        </select>
                        <p class="settings-form-helper">Sales after midnight but before this hour count toward the previous day — a Friday night ends on "Friday".</p>
                        @error('day_cutoff_hour')
                            <p class="settings-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="settings-form-actions">
                        <button type="submit" class="settings-button">Save Business Settings</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
        @if(auth()->user() && auth()->user()->is_admin)
        <div class="settings-section">
            <h3 class="settings-section-title">Logo Settings</h3>
            <div class="settings-card">
                <h4 class="settings-card-title">Light Theme Logo</h4>
                <p class="settings-card-description">This logo will be displayed when the light theme is active.</p>
                <div class="settings-logo-container">
                    @php
                        $lightPngExists = file_exists(public_path('images/logo-light.png'));
                        $lightSvgExists = file_exists(public_path('images/logo-light.svg'));
                        $originalLightSvgExists = file_exists(public_path('images/logo.svg'));
                        $lightLogoPath = null;
                        if ($lightSvgExists) {
                            $lightLogoPath = 'images/logo-light.svg';
                        } elseif ($originalLightSvgExists) {
                            $lightLogoPath = 'images/logo.svg';
                        } elseif ($lightPngExists) {
                            $lightLogoPath = 'images/logo-light.png';
                        }
                    @endphp
                    @if($lightLogoPath)
                        <img src="{{ asset($lightLogoPath) }}" alt="Light Theme Logo" class="settings-logo-image">
                    @else
                        <div class="settings-logo-placeholder">
                            <i class="bi bi-image"></i>
                            <p>No light theme logo uploaded yet</p>
                        </div>
                    @endif
                </div>
                <form action="{{ route('settings.update-logo') }}" method="POST" enctype="multipart/form-data" class="settings-form">
                    @csrf
                    <input type="hidden" name="theme" value="light">
                    <div class="settings-form-group">
                        <label for="logo_light" class="settings-form-label">Upload Light Theme Logo</label>
                        <input type="file" name="logo" id="logo_light" class="settings-form-input" accept="image/png,image/jpeg,image/gif,image/svg+xml">
                        <p class="settings-form-helper">PNG, JPG, GIF or SVG. Max size: 2MB</p>
                        @error('logo')
                            <p class="settings-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="settings-form-actions">
                        <button type="submit" class="settings-button">
                            Update Light Theme Logo
                        </button>
                    </div>
                </form>
            </div>
            <div class="settings-card">
                <h4 class="settings-card-title">Dark Theme Logo</h4>
                <p class="settings-card-description">This logo will be displayed when the dark theme is active.</p>
                <div class="settings-logo-container">
                    @php
                        $darkPngExists = file_exists(public_path('images/logo-dark.png'));
                        $darkSvgExists = file_exists(public_path('images/logo-dark.svg'));
                        $originalDarkSvgExists = file_exists(public_path('images/logowhite.svg'));
                        $darkLogoPath = null;
                        if ($darkSvgExists) {
                            $darkLogoPath = 'images/logo-dark.svg';
                        } elseif ($originalDarkSvgExists) {
                            $darkLogoPath = 'images/logowhite.svg';
                        } elseif ($darkPngExists) {
                            $darkLogoPath = 'images/logo-dark.png';
                        }
                    @endphp
                    @if($darkLogoPath)
                        <img src="{{ asset($darkLogoPath) }}" alt="Dark Theme Logo" class="settings-logo-image">
                    @else
                        <div class="settings-logo-placeholder">
                            <i class="bi bi-image"></i>
                            <p>No dark theme logo uploaded yet</p>
                        </div>
                    @endif
                </div>
                <form action="{{ route('settings.update-logo') }}" method="POST" enctype="multipart/form-data" class="settings-form">
                    @csrf
                    <input type="hidden" name="theme" value="dark">
                    <div class="settings-form-group">
                        <label for="logo_dark" class="settings-form-label">Upload Dark Theme Logo</label>
                        <input type="file" name="logo" id="logo_dark" class="settings-form-input" accept="image/png,image/jpeg,image/gif,image/svg+xml">
                        <p class="settings-form-helper">PNG, JPG, GIF or SVG. Max size: 2MB</p>
                        @error('logo')
                            <p class="settings-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="settings-form-actions">
                        <button type="submit" class="settings-button">
                            Update Dark Theme Logo
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection