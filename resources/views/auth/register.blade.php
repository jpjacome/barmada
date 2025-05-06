<x-guest-layout>
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-card-header">
                <div class="auth-logo">
                    <img src="{{ asset('images/logo-icon-dark.png') }}" class="w-20 h-20" alt="Barmada Logo">
                </div>
                <h2 class="auth-title">{{ __('Register') }}</h2>
                <p class="auth-subtitle">{{ __('Create a new account to get started') }}</p>
            
            </div>

            <div class="auth-card-body">
                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <!-- Name -->
                    <div class="auth-form-group">
                        <label for="name" class="auth-form-label">{{ __('Name') }}</label>
                        <input id="name" class="auth-form-input" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" />
                        @error('name')
                            <div class="auth-form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Email Address -->
                    <div class="auth-form-group">
                        <label for="email" class="auth-form-label">{{ __('Email') }}</label>
                        <input id="email" class="auth-form-input" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" />
                        @error('email')
                            <div class="auth-form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="auth-form-group">
                        <label for="password" class="auth-form-label">{{ __('Password') }}</label>
                        <input id="password" class="auth-form-input" type="password" name="password" required autocomplete="new-password" />
                        @error('password')
                            <div class="auth-form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div class="auth-form-group">
                        <label for="password_confirmation" class="auth-form-label">{{ __('Confirm Password') }}</label>
                        <input id="password_confirmation" class="auth-form-input" type="password" name="password_confirmation" required autocomplete="new-password" />
                        @error('password_confirmation')
                            <div class="auth-form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <button class="auth-button">
                        {{ __('Register') }}
                    </button>
                </form>
            </div>

            <div class="auth-card-footer">
                <p class="auth-footer-text">
                    {{ __('Already have an account?') }}
                    <a href="{{ route('login') }}" class="auth-footer-link">{{ __('Log in') }}</a>
                </p>
            </div>
        </div>
    </div>
</x-guest-layout>
