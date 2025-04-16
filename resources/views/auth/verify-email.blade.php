<x-guest-layout>
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-card-header">
                <div class="auth-logo">
                    <x-application-logo class="w-20 h-20 fill-current" />
                </div>
                <h2 class="auth-title">{{ __('Verify Email') }}</h2>
                <p class="auth-subtitle">{{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you?') }}</p>
            </div>

            <div class="auth-card-body auth-email-verification">
                @if (session('status') == 'verification-link-sent')
                    <div class="auth-success-message">
                        {{ __('A new verification link has been sent to the email address you provided during registration.') }}
                    </div>
                @endif

                <div class="auth-form-group">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button class="auth-button">
                            {{ __('Resend Verification Email') }}
                        </button>
                    </form>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="auth-button">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
