import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Brand body face. Figtree was Breeze scaffolding the design
                // system explicitly retired.
                sans: ['Inter Tight', ...defaultTheme.fontFamily.sans],
                serif: ['Crimson Text', ...defaultTheme.fontFamily.serif],
            },
        },
    },

    plugins: [forms],
};
