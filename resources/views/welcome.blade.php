@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/homepage.css') }}">
<div class="homepage-hero-2col">
    <div class="hero-col hero-col-image">
        <img src="/images/logo1.svg" alt="Barmada Logo" class="hero-logo">
    </div>
    <div class="hero-col hero-col-text">
        <div class="container">
            <h1 class="hero-title">Revolutionize Your Bar & Restaurant</h1>
            <h2 class="hero-highlight">Let Your Tables <span class="highlight-accent">Order Themselves!</span></h2>
            <p class="hero-desc">Barmada makes managing orders effortless for owners and a breeze for customers. No more waiting for staff, no more mistakes—just fast, contactless, and accurate service every time.</p>
        </div>
    </div>
</div>
<div class="container-1"></div>
<div class="homepage-features">
        <div class="feature-box">            
            <div class="container">
                <i class="bi bi-qr-code-scan feature-icon"></i>
                <h3>QR Table Ordering</h3>
            </div>
            <p>Customers scan, order, and pay right from their table—no app, no hassle.</p>
        </div>
        <div class="feature-box">
            <div class="container">
                <i class="bi bi-people feature-icon"></i>
                <h3>Multi-Editor Dashboard</h3>
            </div>
            <p>Manage multiple venues or locations with ease. Each business gets its own secure dashboard.</p>
        </div>
        <div class="feature-box">
            <div class="container">            
                <i class="bi bi-lightning-charge feature-icon"></i>
                <h3>Lightning-Fast Service</h3>
            </div>    
            <p>Orders go straight to your team—no more missed tables or slowdowns. Happier guests, more sales!</p>
        </div>
</div>
    <div class="homepage-cta">
        @if (Route::has('login'))
            <a href="{{ route('login') }}" class="btn btn-primary btn-lg homepage-btn">Get Started</a>
        @endif
    </div>

<!-- Customer POV Features Section -->
<div class="userpov">
    <h2>Tap, Order, <span class="highlight-accent">Enjoy</span>—No Delays</h2>
    <div class="container customer-pov-section">
        <div class="row">
            <div class="card customer-pov-card">
                <i class="bi bi-qr-code-scan"></i>
                <h4>Instant Ordering</h4>
                <p>Scan the table QR code and order your food and drinks in seconds—no app or signup required.</p>
            </div>
            <div class="card customer-pov-card">
                <i class="bi bi-cup-straw"></i>
                <h4>Easy Customization</h4>
                <p>Customize your order, add notes, and see the menu with images and prices—just like you want it.</p>
            </div>
            <div class="card customer-pov-card">
                <i class="bi bi-clock-history"></i>
                <h4>No Waiting</h4>
                <p>Order goes straight to the bar—no need to wait for staff. Enjoy faster service and more time with friends!</p>
            </div>
        </div>
        <div class="image-container">
            <img src="../images/bg1.png" alt="">
        </div>
    </div>
</div>

<div class="homepage-below">    
    <div class="container-2">
        <video class="bg-video" autoplay loop muted playsinline id="container2-bg-video">
            <source src="{{ asset('videos/vid1.mp4') }}" type="video/mp4">
        </video>
        <div class="homepage-secondary-features mt-5">
            <ul class="list-inline">
                <li class="list-inline-item" id='inline-item-1'><i class="bi bi-shield-check"></i> Secure & Private</li>
                <li class="list-inline-item" id='inline-item-2'><i class="bi bi-phone"></i> Mobile Friendly</li>
                <li class="list-inline-item" id='inline-item-3'><i class="bi bi-bar-chart"></i> Real-Time Analytics</li>
            </ul>
            <div class="text-container">
                <div class="text">
                    <p class='features-text' id='features-text'></p>
                </div>
            </div>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var vid = document.getElementById('container2-bg-video');
    if (vid) {
        vid.playbackRate = 0.5;
    }
    const featureTexts = {
        1: "Barmada is designed with security and privacy at its core. All user data, orders, and business information are protected using Laravel’s robust authentication, authorization, and encryption features. Access to sensitive features is restricted by user roles and middleware, ensuring only authorized personnel can view or modify critical data. Activity logs and audit trails provide transparency and accountability, while secure session management and encrypted connections keep your business and customer information safe at all times.",
        2: "Barmada is fully mobile friendly, offering a seamless experience on any device. The interface automatically adapts to smartphones and tablets, ensuring that both customers and staff can access all features with ease. Touch-optimized controls, responsive layouts, and fast load times make managing orders and navigating menus effortless, whether you’re behind the bar or at the table.",
        3: "Barmada provides real-time analytics to help you make informed decisions instantly. Track sales, monitor order trends, and view live activity dashboards as events happen. With up-to-the-minute data on customer preferences and business performance, you can optimize operations, spot opportunities, and respond to changes as they occur—all from a single, intuitive dashboard."
    };
    const textContainer = document.querySelector('.homepage-secondary-features .text-container .text');
    const textP = document.getElementById('features-text');
    let active = null;
    const items = [1,2,3].map(idx => document.getElementById('inline-item-' + idx));

    function showText(idx) {
        textP.textContent = featureTexts[idx];
        textContainer.classList.add('text-active');
        items.forEach((item, i) => {
            if (i === idx - 1) {
                item.classList.add('active-feature');
            } else {
                item.classList.remove('active-feature');
            }
        });
    }
    function hideText() {
        textContainer.classList.remove('text-active');
        textP.textContent = '';
        items.forEach(item => item.classList.remove('active-feature'));
    }

    [1,2,3].forEach(idx => {
        const item = document.getElementById('inline-item-' + idx);
        item.addEventListener('mouseenter', function() {
            if (active === null) showText(idx);
        });
        item.addEventListener('mouseleave', function() {
            if (active === null) hideText();
        });
        item.addEventListener('click', function() {
            if (active === idx) {
                active = null;
                hideText();
            } else {
                active = idx;
                showText(idx);
            }
        });
    });
});
</script>
</div>

<div class="contact-wrapper">
    <div class="contact-logo">
        <img src="/images/logo-light.svg" alt="Barmada Logo">
        <div class="company-footer-info">
            support@barmada.com<br>
            &copy; 2025 Barmada. All rights reserved.
        </div>
    </div>
    <div class="whatsapp-container">
        <a href="https://wa.me/593979136467?text=Hello%2C%20I%27m%20interested%20in%20Barmada%20-%20Bar%20Management%20Dashboard" target="_blank" rel="noopener" class="btn whatsapp-btn">
            <img src="/images/icon-whatsapp.png" alt="WhatsApp" class="whatsapp-icon" />
            Contact us
        </a>
    </div>
</div>
@endsection
