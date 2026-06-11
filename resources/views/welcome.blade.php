@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/homepage.css') }}">

<div class="homepage">

    <!-- Hero -->
    <section class="hp-hero" aria-labelledby="hp-hero-title">
        <div class="hp-hero-media">
            <div class="hp-hero-glow" aria-hidden="true"></div>
            <img src="{{ asset('images/logo1.png') }}" alt="Barmada logo" class="hp-hero-logo d-light">
            <img src="{{ asset('images/logo1-dark.png') }}" alt="Barmada logo" class="hp-hero-logo d-dark">
        </div>
        <div class="hp-hero-copy">
            <p class="hp-eyebrow">Bar &amp; Restaurant Management</p>
            <h1 class="hp-title" id="hp-hero-title">Revolutionize Your Bar &amp; Restaurant</h1>
            <p class="hp-tagline">Let Your Tables <span class="hp-tagline-accent">Order Themselves!</span></p>
            <p class="hp-lede">Barmada makes managing orders effortless for owners and a breeze for customers. No more waiting for staff, no more mistakes&mdash;just fast, contactless, and accurate service every time. No commissions, no subscriptions&mdash;your hardware, your server, your data.</p>
            <div class="hp-actions">
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="hp-btn hp-btn-primary">Get Started <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                @endif
                <a href="#hp-features" class="hp-btn hp-btn-ghost">See how it works</a>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="hp-section" id="hp-features" aria-labelledby="hp-features-title">
        <div class="hp-section-head" data-reveal>
            <p class="hp-eyebrow">Why Barmada</p>
            <h2 class="hp-heading" id="hp-features-title">Everything Your Venue Needs</h2>
        </div>
        <div class="hp-feature-grid">
            <article class="hp-card" data-reveal>
                <span class="hp-card-icon"><i class="bi bi-qr-code-scan" aria-hidden="true"></i></span>
                <h3>QR Table Ordering</h3>
                <p>Customers scan and order right from their table&mdash;no app, no account, no payment wall. The bar collects payment the way it always has.</p>
            </article>
            <article class="hp-card" data-reveal style="--hp-reveal-delay: 80ms;">
                <span class="hp-card-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                <h3>Multi-Editor Dashboard</h3>
                <p>Manage multiple venues or locations with ease. Each business gets its own secure dashboard.</p>
            </article>
            <article class="hp-card" data-reveal style="--hp-reveal-delay: 160ms;">
                <span class="hp-card-icon"><i class="bi bi-lightning-charge" aria-hidden="true"></i></span>
                <h3>Lightning-Fast Service</h3>
                <p>Orders go straight to your team&mdash;no more missed tables or slowdowns. Happier guests, more sales!</p>
            </article>
        </div>
        @if (Route::has('login'))
            <div class="hp-cta" data-reveal>
                <a href="{{ route('login') }}" class="hp-btn hp-btn-primary hp-btn-lg">Get Started</a>
            </div>
        @endif
    </section>

    <div class="hp-divider" aria-hidden="true">
        <span class="hp-divider-line"></span>
        <img src="{{ asset('images/logo-icon.svg') }}" alt="" class="hp-divider-mark d-light">
        <img src="{{ asset('images/logo-icon-dark.svg') }}" alt="" class="hp-divider-mark d-dark">
        <span class="hp-divider-line"></span>
    </div>

    <!-- Customer POV -->
    <section class="hp-section" aria-labelledby="hp-pov-title">
        <div class="hp-section-head" data-reveal>
            <p class="hp-eyebrow">For Your Guests</p>
            <h2 class="hp-heading" id="hp-pov-title">Tap, Order, <span class="hp-heading-accent">Enjoy</span>&mdash;No Delays</h2>
        </div>
        <div class="hp-pov-layout">
            <div class="hp-pov-cards">
                <article class="hp-pov-card" data-reveal>
                    <span class="hp-card-icon"><i class="bi bi-qr-code-scan" aria-hidden="true"></i></span>
                    <div>
                        <h3>Instant Ordering</h3>
                        <p>Scan the table QR code and order your food and drinks in seconds&mdash;no app or signup required.</p>
                    </div>
                </article>
                <article class="hp-pov-card" data-reveal style="--hp-reveal-delay: 80ms;">
                    <span class="hp-card-icon"><i class="bi bi-cup-straw" aria-hidden="true"></i></span>
                    <div>
                        <h3>Visual Menu</h3>
                        <p>Browse the menu with photos, descriptions and prices, build your round, and review the total before sending&mdash;all in seconds.</p>
                    </div>
                </article>
                <article class="hp-pov-card" data-reveal style="--hp-reveal-delay: 160ms;">
                    <span class="hp-card-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                    <div>
                        <h3>No Waiting</h3>
                        <p>Order goes straight to the bar&mdash;no need to wait for staff. Enjoy faster service and more time with friends!</p>
                    </div>
                </article>
            </div>
            <div class="hp-pov-media" data-reveal>
                <img src="{{ asset('images/bg1.png') }}" alt="Guests ordering from their phones at a bar table" loading="lazy" decoding="async">
            </div>
        </div>
    </section>

    <!-- Trust Band -->
    <section class="hp-trust" aria-labelledby="hp-trust-title">
        <video class="hp-trust-video" autoplay loop muted playsinline preload="metadata" poster="{{ asset('images/bg2.png') }}" id="hp-trust-video">
            <source src="{{ asset('videos/vid1.mp4') }}" type="video/mp4">
        </video>
        <div class="hp-trust-inner">
            <h2 class="hp-heading hp-trust-title" id="hp-trust-title">Built for <span class="hp-heading-accent">Peace of Mind</span></h2>
            <div class="hp-trust-pills">
                <button type="button" class="hp-pill" id="hp-pill-1" aria-controls="hp-trust-panel" aria-expanded="false">
                    <i class="bi bi-shield-check" aria-hidden="true"></i> Secure &amp; Private
                </button>
                <button type="button" class="hp-pill" id="hp-pill-2" aria-controls="hp-trust-panel" aria-expanded="false">
                    <i class="bi bi-phone" aria-hidden="true"></i> Mobile Friendly
                </button>
                <button type="button" class="hp-pill" id="hp-pill-3" aria-controls="hp-trust-panel" aria-expanded="false">
                    <i class="bi bi-bar-chart" aria-hidden="true"></i> Built-in Analytics
                </button>
            </div>
            <div class="hp-trust-panel" id="hp-trust-panel" role="region" aria-live="polite" aria-label="Feature details">
                <p class="hp-trust-text is-muted" id="hp-trust-text">Select a feature above to learn more.</p>
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section class="hp-contact" aria-label="Contact">
        <div class="hp-contact-brand">
            <img src="{{ asset('images/logo-light.svg') }}" alt="Barmada" class="hp-contact-logo d-light">
            <img src="{{ asset('images/logo-dark.svg') }}" alt="Barmada" class="hp-contact-logo d-dark">
            <p class="hp-contact-copy">&copy; {{ date('Y') }} Barmada. All rights reserved.</p>
        </div>
        <a href="https://wa.me/593979136467?text=Hello%2C%20I%27m%20interested%20in%20Barmada%20-%20Bar%20Management%20Dashboard" target="_blank" rel="noopener" class="hp-btn hp-btn-whatsapp">
            <img src="{{ asset('images/icon-whatsapp.png') }}" alt="" class="hp-whatsapp-icon">
            Contact us
        </a>
    </section>

    <footer class="app-footer">
        <div class="app-footer-content">
            <a href="https://github.com/jpjacome/barmada" target="_blank" rel="noopener noreferrer">
                <i class="bi bi-github app-footer-icon"></i>GitHub
            </a>
            <span class="app-footer-separator">|</span>
            <span>created by <a href="http://drpixel.it.nf" target="_blank" rel="noopener noreferrer">Dr. Pixel</a></span>
        </div>
    </footer>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var vid = document.getElementById('hp-trust-video');
    if (vid) {
        vid.playbackRate = 0.5;
    }

    // --- Trust band: interactive feature panel ---
    var featureTexts = {
        1: "Barmada is designed with security and privacy at its core. All user data, orders, and business information are protected using Laravel’s robust authentication, authorization, and encryption features. Access to sensitive features is restricted by user roles and middleware, ensuring only authorized personnel can view or modify critical data. Activity logs and audit trails provide transparency and accountability, while secure session management and encrypted connections keep your business and customer information safe at all times.",
        2: "Barmada is fully mobile friendly, offering a seamless experience on any device. The interface automatically adapts to smartphones and tablets, ensuring that both customers and staff can access all features with ease. Touch-optimized controls, responsive layouts, and fast load times make managing orders and navigating menus effortless, whether you’re behind the bar or at the table.",
        3: "Barmada ships with an analytics dashboard built for bar owners: daily, weekly and monthly sales, top and least-selling products, category breakdowns, peak hours, session durations and table turnover. Export everything to PDF or CSV whenever you need it—no extra modules, no premium tier."
    };
    var defaultText = 'Select a feature above to learn more.';
    var panel = document.getElementById('hp-trust-panel');
    var textP = document.getElementById('hp-trust-text');
    var pills = [1, 2, 3].map(function (i) { return document.getElementById('hp-pill-' + i); });
    var active = null;

    function render(idx) {
        if (idx === null) {
            textP.textContent = defaultText;
            textP.classList.add('is-muted');
            panel.classList.remove('is-active');
        } else {
            textP.textContent = featureTexts[idx];
            textP.classList.remove('is-muted');
            panel.classList.add('is-active');
        }
        pills.forEach(function (pill, i) {
            pill.setAttribute('aria-expanded', idx === i + 1 ? 'true' : 'false');
        });
    }

    pills.forEach(function (pill, i) {
        var idx = i + 1;
        pill.addEventListener('mouseenter', function () {
            if (active === null) render(idx);
        });
        pill.addEventListener('mouseleave', function () {
            if (active === null) render(null);
        });
        pill.addEventListener('click', function () {
            active = (active === idx) ? null : idx;
            render(active);
        });
    });

    // --- Scroll reveal (motion-safe, no-JS safe) ---
    var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var revealEls = document.querySelectorAll('[data-reveal]');
    if (!prefersReduced && 'IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

        revealEls.forEach(function (el) {
            // Skip elements already in the viewport so nothing flashes on load
            var rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) return;
            el.classList.add('hp-reveal');
            io.observe(el);
        });
    }
});
</script>
@endsection
