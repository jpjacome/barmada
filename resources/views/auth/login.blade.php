<x-guest-layout>
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-card-header">
                <div class="auth-logo">
                    <x-application-logo class="w-20 h-20 fill-current" />
                </div>
                <h2 class="auth-title">{{ __('Log in') }}</h2>
                <p class="auth-subtitle">{{ __('Welcome back! Please enter your credentials to access your account.') }}</p>
            </div>

            <div class="auth-card-body">
                <!-- Session Status -->
                @if (session('status'))
                    <div class="auth-success-message">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email Address -->
                    <div class="auth-form-group">
                        <label for="email" class="auth-form-label">{{ __('Email') }}</label>
                        <input id="email" class="auth-form-input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
                        @error('email')
                            <div class="auth-form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="auth-form-group">
                        <label for="password" class="auth-form-label">{{ __('Password') }}</label>
                        <input id="password" class="auth-form-input" type="password" name="password" required autocomplete="current-password" />
                        @error('password')
                            <div class="auth-form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Remember Me -->
                    <div class="auth-form-checkbox-wrapper">
                        <input id="remember_me" type="checkbox" class="auth-form-checkbox" name="remember">
                        <label for="remember_me" class="auth-form-checkbox-label">{{ __('Remember me') }}</label>
                    </div>

                    @if (Route::has('password.request'))
                        <a class="auth-forgot-password" href="{{ route('password.request') }}">
                            {{ __('Forgot your password?') }}
                        </a>
                    @endif

                    <button class="auth-button">
                        {{ __('Log in') }}
                    </button>
                </form>
            </div>

            <div class="auth-card-footer">
                <p class="auth-footer-text">
                    {{ __("Don't have an account?") }}
                    <a href="{{ route('register') }}" class="auth-footer-link">{{ __('Sign up') }}</a>
                </p>
            </div>
        </div>
    </div>
</x-guest-layout>
