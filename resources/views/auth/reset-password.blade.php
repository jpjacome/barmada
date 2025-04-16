<x-guest-layout>
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-card-header">
                <div class="auth-logo">
                    <x-application-logo class="w-20 h-20 fill-current" />
                </div>
                <h2 class="auth-title">{{ __('Reset Password') }}</h2>
                <p class="auth-subtitle">{{ __('Create a new secure password for your account') }}</p>
            </div>

            <div class="auth-card-body">
                <form method="POST" action="{{ route('password.store') }}">
                    @csrf

                    <!-- Password Reset Token -->
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    <!-- Email Address -->
                    <div class="auth-form-group">
                        <label for="email" class="auth-form-label">{{ __('Email') }}</label>
                        <input id="email" class="auth-form-input" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" />
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
                        {{ __('Reset Password') }}
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
