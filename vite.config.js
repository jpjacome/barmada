import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// The auth pages (layouts/guest.blade.php) are the only consumers of the
// Vite bundle; every other page loads hand-written CSS from public/css.
// This file was missing entirely, so `npm run build` could not resolve
// Laravel's @vite directive and the auth bundle was unbuildable as shipped.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
