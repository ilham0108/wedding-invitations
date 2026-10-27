import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './config/navigation.php',
    ],

    theme: {
        extend: {
            colors: {
                ink: { DEFAULT: '#1f2d2a', soft: '#3a4a46' },
                muted: '#6b7a74',
                paper: '#f6f4ef',
                line: '#e5e2da',
                accent: { DEFAULT: '#c8634b', hover: '#b25540', soft: '#f8e9e4' },
                sage: { DEFAULT: '#365149', soft: '#e7ece8' },
                // Marketing site palette (botanical: olive buttons on warm cream).
                olive: { DEFAULT: '#4a573f', dark: '#3a4532', soft: '#e8e5da', line: '#d9d4c7' },
                cream: { DEFAULT: '#f7f4ee', deep: '#efeae0' },
            },
            fontFamily: {
                sans: ['"DM Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Playfair Display"', ...defaultTheme.fontFamily.serif],
            },
            boxShadow: {
                soft: '0 1px 2px rgb(31 45 42 / 0.04), 0 4px 16px rgb(31 45 42 / 0.04)',
            },
            borderRadius: {
                xl: '0.875rem',
            },
        },
    },

    plugins: [forms],
};
