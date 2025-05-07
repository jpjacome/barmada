<nav x-data="{ open: false }" class="navigation">
    <!-- Primary Navigation Menu -->
    <div class="navigation-container">
        <div class="navigation-content">
            <div class="navigation-left">
                <!-- Logo -->
                <div class="navigation-logo">
                    <span href="{{ route('dashboard') }}">
                        <x-application-logo class="logo-image" alt="Barmada Logo" />
                    </span>
                </div>

                <!-- Navigation Links -->
                
            </div>

            <!-- Settings Dropdown -->
            <div class="navigation-right">
                <!-- Theme Toggle Switch (always visible) -->
                <div class="theme-toggle">
                    <form action="{{ route('settings.toggle-theme') }}" method="POST">
                        @csrf
                        <label class="theme-switch">
                            <input type="checkbox" onchange="this.form.submit()" {{ session('theme', 'light') === 'dark' ? 'checked' : '' }}>
                            <span class="slider"></span>
                        </label>
                    </form>
                </div>
                <!-- Navigation Links -->
                <div class="navigation-links">
                    @if(Auth::check() && Auth::user()->is_admin)
                        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                            {{ __('Admin Dashboard') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.editors')" :active="request()->routeIs('admin.editors')">
                            {{ __('Establishments') }}
                        </x-nav-link>
                    @elseif(Auth::check() && Auth::user()->is_editor)
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            <i class="bi bi-house-door-fill" style="font-size: 1.2em;"></i>
                        </x-nav-link>
                        <x-nav-link :href="route('tables.index')" :active="request()->routeIs('tables.*')">
                            {{ __('Tables') }}
                        </x-nav-link>
                        <x-nav-link :href="route('products.index')" :active="request()->routeIs('products.index')">
                            {{ __('Products') }}
                        </x-nav-link>
                        <x-nav-link :href="route('all-orders')" :active="request()->routeIs('all-orders')">
                            {{ __('Orders') }}
                        </x-nav-link>
                        <a href="{{ route('orders.create') }}" class="nav-new-order-button">
                            <span class="nav-button-text">New Order</span>
                        </a>
                    @elseif(Auth::check() && Auth::user()->is_staff)
                        <x-nav-link :href="route('tables.index')" :active="request()->routeIs('tables.*')">
                            {{ __('Tables') }}
                        </x-nav-link>
                        <x-nav-link :href="route('all-orders')" :active="request()->routeIs('all-orders')">
                            {{ __('Orders') }}
                        </x-nav-link>
                        <a href="{{ route('orders.create') }}" class="nav-new-order-button">
                            <span class="nav-button-text">New Order</span>
                        </a>
                    @endif
                </div>
                
                @auth
                @if(Auth::user()->is_admin || Auth::user()->is_editor)
                <a href="{{ route('staff.index') }}" class="dropdown-trigger" title="Staff">
                    <i class="bi bi-person"></i>
                </a>
                <a href="{{ route('analytics.dashboard', ['editor' => Auth::user()->id]) }}" class="dropdown-trigger" title="Analytics">
                    <i class="bi bi-bar-chart"></i>
                </a>
                @endif
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="dropdown-trigger">
                            <i class="bi bi-gear"></i>

                            <div class="dropdown-trigger-icon">
                                <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                          d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                          clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('settings.index')">
                            {{ __('Settings') }}
                        </x-dropdown-link>

                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
                @else
                {{-- Guests: simple Log in button (desktop) --}}
                <a href="{{ route('login') }}" class="dropdown-trigger" title="Login/Register">
                    <i class="bi bi-person" style="font-size: 2rem;"></i>
                </a>
                @endauth

            </div>

            <!-- Hamburger -->
            @auth
            <div class="navigation-hamburger">
                <button @click="open = ! open" class="hamburger-button" :aria-expanded="open">
                    <svg class="hamburger-icon" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open}" class="hamburger-icon-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': !open}" class="hamburger-icon-close" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            @endauth
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    @auth
    <div x-show="open" 
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95" 
         class="responsive-navigation">
        <div class="responsive-navigation-content">
            @if(Auth::check() && Auth::user()->is_admin)
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    {{ __('Admin Dashboard') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.editors')" :active="request()->routeIs('admin.editors')">
                    {{ __('Establishments') }}
                </x-responsive-nav-link>
            @elseif(Auth::check() && Auth::user()->is_editor)
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    <i class="bi bi-house-door-fill" style="font-size: 1.2em;"></i>
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('tables.index')" :active="request()->routeIs('tables.*')">
                    {{ __('Tables') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('products.index')" :active="request()->routeIs('products.index')">
                    {{ __('Products') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('all-orders')" :active="request()->routeIs('all-orders')">
                    {{ __('Orders') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('staff.index')" :active="request()->routeIs('staff.index')">
                    <i class="bi bi-person"></i>
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('orders.create')" class="responsive-new-order">
                    {{ __('New Order') }}
                </x-responsive-nav-link>
            @elseif(Auth::check() && Auth::user()->is_staff)
                <x-responsive-nav-link :href="route('tables.index')" :active="request()->routeIs('tables.*')">
                    {{ __('Tables') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('all-orders')" :active="request()->routeIs('all-orders')">
                    {{ __('Orders') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('orders.create')" class="responsive-new-order">
                    {{ __('New Order') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="responsive-settings">
            <div class="responsive-settings-header">
                <div class="responsive-settings-name">{{ Auth::check() ? Auth::user()->name : 'Guest' }}</div>
                <div class="responsive-settings-email">{{ Auth::check() ? Auth::user()->email : '' }}</div>
            </div>

            <div class="responsive-settings-links">
                @if(Auth::check())
                    <x-responsive-nav-link :href="route('profile.edit')">
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    <!-- Authentication -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-responsive-nav-link :href="route('logout')"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                @else
                    <x-responsive-nav-link :href="route('login')">
                        {{ __('Log in') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('register')">
                        {{ __('Register') }}
                    </x-responsive-nav-link>
                @endif
            </div>
        </div>
    </div>
    @endauth
</nav>
