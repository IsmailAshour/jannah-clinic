import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,vue}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Cairo', 'ui-sans-serif', 'system-ui', 'sans-serif', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand:           'var(--color-brand)',
                'brand-hover':   'var(--color-brand-hover)',
                'brand-active':  'var(--color-brand-active)',
                accent:          'var(--color-accent)',
                'surface-page':  'var(--color-surface-page)',
                'surface-card':  'var(--color-surface-card)',
                'surface-sunken':'var(--color-surface-sunken)',
                'text-primary':  'var(--color-text-primary)',
                'text-secondary':'var(--color-text-secondary)',
                'text-tertiary': 'var(--color-text-tertiary)',
                'border-default':'var(--color-border-default)',
                'border-strong': 'var(--color-border-strong)',
                success:         'var(--color-success)',
                warning:         'var(--color-warning)',
                danger:          'var(--color-danger)',
                info:            'var(--color-info)',
                amount:          'var(--color-amount)',
            },
            borderRadius: {
                sm: 'var(--radius-sm)',
                md: 'var(--radius-md)',
                lg: 'var(--radius-lg)',
                xl: 'var(--radius-xl)',
            },
            boxShadow: {
                xs: 'var(--shadow-xs)',
                sm: 'var(--shadow-sm)',
                md: 'var(--shadow-md)',
                lg: 'var(--shadow-lg)',
            },
            transitionDuration: {
                fast:   '100ms',
                normal: '200ms',
                slow:   '300ms',
            },
        },
    },

    plugins: [forms],
};
