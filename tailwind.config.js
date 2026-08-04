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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#EAF2FA',
                    100: '#CFE3F5',
                    200: '#9FC7EB',
                    300: '#6FABE1',
                    400: '#3F8FD7',
                    500: '#1F76C4',
                    600: '#145C9E',
                    700: '#104A7F',
                    800: '#0C3760',
                    900: '#082540',
                },
            },
        },
    },

    plugins: [forms],
};
