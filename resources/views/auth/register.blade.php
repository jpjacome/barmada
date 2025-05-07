<x-guest-layout>
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">
    <link href="{{ asset('css/register-multistep.css') }}" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <div class="reg-form-outer">
        <div class="reg-form-card" x-data="{ step: 1 }">
            <div class="reg-form-header">
                <div class="reg-form-logo">
                    <img src="{{ asset('images/logo-icon-dark.png') }}" class="reg-form-logo-img" alt="Barmada Logo">
                </div>
                <template x-if="step === 1">
                    <div>
                        <h2 class="reg-form-title">Welcome to Barmada!</h2>
                        <p class="reg-form-subtitle">You're just a few steps away from making your bar or restaurant smarter and more efficient.<br>Let's get your business set up!</p>
                    </div>
                </template>
                <template x-if="step === 2">
                    <div>
                        <h2 class="reg-form-title">Business Details</h2>
                        <p class="reg-form-subtitle">Tell us about your venue to set up your digital tables</p>
                    </div>
                </template>
                <template x-if="step === 3">
                    <div>
                        <h2 class="reg-form-title">Secure your account</h2>
                        <p class="reg-form-subtitle">Set a password to protect your business dashboard</p>
                    </div>
                </template>
            </div>
            <div class="reg-form-body">
                <form method="POST" action="{{ route('register') }}" x-data="{ step: 1, canNext1: false, canNext2: false, tableCount: Number({{ old('table_count', 1) }}), validateStep1() { this.canNext1 = document.getElementById('username').value.trim() && document.getElementById('email').value.trim(); }, validateStep2() { this.canNext2 = document.getElementById('business_name').value.trim() && this.tableCount >= 1 && this.tableCount <= 50; } }" @input.debounce.200="validateStep1(); validateStep2();">
                    @csrf
                    <!-- Step 1: Username, Email -->
                    <div id='form-flex' x-show="step === 1" style="display: none;" x-transition>
                        <div class="reg-form-group">
                            <label for="username" class="reg-form-label">{{ __('Username') }}</label>
                            <input id="username" class="reg-form-input" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" pattern="^[a-zA-Z0-9_\-]+$" title="Username must be a single word, no spaces"
                                @keydown.enter.prevent="if(canNext1){step = 2}" @input="validateStep1()" />
                            @error('username')
                                <div class="reg-form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="reg-form-group">
                            <label for="email" class="reg-form-label">{{ __('Email') }}</label>
                            <input id="email" class="reg-form-input" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                                @keydown.enter.prevent="if(canNext1){step = 2}" @input="validateStep1()" />
                            @error('email')
                                <div class="reg-form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="button" class="reg-form-btn" :disabled="!canNext1" :class="{'disabled': !canNext1}" @click="if(canNext1){step = 2}">Next</button>
                    </div>
                    <!-- Step 2: Business Name, Table Count -->
                    <div x-show="step === 2" style="display: none;" x-transition>
                        <div class="reg-form-group">
                            <label for="business_name" class="reg-form-label">{{ __('Business Name') }}</label>
                            <input id="business_name" class="reg-form-input" type="text" name="business_name" value="{{ old('business_name') }}" required
                                @keydown.enter.prevent="if(canNext2){step = 3}" @input="validateStep2()" />
                            @error('business_name')
                                <div class="reg-form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="reg-form-group">
                            <label for="table_count" class="reg-form-label">{{ __('Number of Tables') }}</label>
                            <div class="reg-form-table-count-row">
                                <button type="button" class="reg-form-btn reg-form-table-count-btn" @click="if(tableCount > 1) tableCount--; document.getElementById('table_count').value = tableCount; validateStep2()">-</button>
                                <input id="table_count" class="reg-form-input reg-form-table-count-input" type="number" name="table_count" min="1" max="50" :value="tableCount" 
                                    @input="tableCount = Math.max(1, Math.min(50, Number($event.target.value))); validateStep2()" @keydown.enter.prevent="if(canNext2){step = 3}" required />
                                <button type="button" class="reg-form-btn reg-form-table-count-btn" @click="if(tableCount < 50) tableCount++; document.getElementById('table_count').value = tableCount; validateStep2()">+</button>
                            </div>
                            @error('table_count')
                                <div class="reg-form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="reg-form-btn-row">
                            <button type="button" class="reg-form-btn" @click="step = 1">Back</button>
                            <button type="button" class="reg-form-btn" :disabled="!canNext2" :class="{'disabled': !canNext2}" @click="if(canNext2){step = 3}">Next</button>
                        </div>
                    </div>
                    <!-- Step 3: Password, Confirm Password -->
                    <div x-show="step === 3" style="display: none;" x-transition>
                        <div class="reg-form-group">
                            <label for="password" class="reg-form-label">{{ __('Password') }}</label>
                            <input id="password" class="reg-form-input" type="password" name="password" required autocomplete="new-password"
                                @keydown.enter.prevent="document.getElementById('password_confirmation').focus()" />
                            @error('password')
                                <div class="reg-form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="reg-form-group">
                            <label for="password_confirmation" class="reg-form-label">{{ __('Confirm Password') }}</label>
                            <input id="password_confirmation" class="reg-form-input" type="password" name="password_confirmation" required autocomplete="new-password"
                                @keydown.enter.prevent="event.target.form.submit()" />
                            @error('password_confirmation')
                                <div class="reg-form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="reg-form-btn-row">
                            <button type="button" class="reg-form-btn" @click="step = 2">Back</button>
                            <button type="submit" class="reg-form-btn">{{ __('Register') }}</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="reg-form-footer">
                <p class="reg-form-footer-text">
                    {{ __('Already have an account?') }}
                    <a href="{{ route('login') }}" class="reg-form-footer-link">{{ __('Log in') }}</a>
                </p>
            </div>
        </div>
    </div>
</x-guest-layout>
