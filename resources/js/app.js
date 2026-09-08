import './bootstrap';
import '../css/app.css';

// Alpine powers the auth pages (multi-step register, password toggles).
// It is bundled here once; the register view used to load a second copy
// from a CDN, which double-initialised it.
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
