<x-guest-layout>
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-card-header">
                <div class="auth-logo">
                    <x-application-logo class="w-20 h-20 fill-current" />
                </div>
                <h2 class="auth-title">{{ __('Forgot Password') }}</h2>
                <p class="auth-subtitle">{{ __('No problem. Just let us know your email address and we will email you a password reset link.') }}</p>
            </div>

            <div class="auth-card-body">
                <!-- Session Status -->
                @if (session('status'))
                    <div class="auth-success-message">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf

                    <!-- Email Address -->
                    <div class="auth-form-group">
                        <label for="email" class="auth-form-label">{{ __('Email') }}</label>
                        <input id="email" class="auth-form-input" type="email" name="email" value="{{ old('email') }}" required autofocus />
                        @error('email')
                            <div class="auth-form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <button class="auth-button">
                        {{ __('Email Password Reset Link') }}
                    </button>
                </form>
            </div>

            <div class="auth-card-footer">
                <p class="auth-footer-text">
                    <a href="{{ route('login') }}" class="auth-footer-link">
                        {{ __('Back to login') }}
                    </a>
                </p>
            </div>
        </div>
    </div>
</x-guest-layout>
