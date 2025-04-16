<x-guest-layout>
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-card-header">
                <div class="auth-logo">
                    <x-application-logo class="w-20 h-20 fill-current" />
                </div>
                <h2 class="auth-title">{{ __('Confirm Password') }}</h2>
                <p class="auth-subtitle">{{ __('This is a secure area of the application. Please confirm your password before continuing.') }}</p>
            </div>

            <div class="auth-card-body">
                <form method="POST" action="{{ route('password.confirm') }}">
                    @csrf

                    <!-- Password -->
                    <div class="auth-form-group">
                        <label for="password" class="auth-form-label">{{ __('Password') }}</label>
                        <input id="password" class="auth-form-input" type="password" name="password" required autocomplete="current-password" />
                        @error('password')
                            <div class="auth-form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <button class="auth-button">
                        {{ __('Confirm') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
