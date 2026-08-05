const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Noto Sans JP"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#f0f7ff',
                    100: '#e0efff',
                    200: '#b9dcff',
                    300: '#7cbeff',
                    400: '#369cff',
                    500: '#0d7ee8',
                    600: '#0066cc',
                    700: '#0052a3',
                    800: '#054585',
                    900: '#0a3a6e',
                },
            },
        },
    },

    plugins: [require('@tailwindcss/forms')],
};
