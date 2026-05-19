import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
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
                /* ── JannahClinic design tokens ── */
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

                /* ── shadcn-vue semantic tokens (TW3 compat) ── */
                background:          'var(--background)',
                foreground:          'var(--foreground)',
                card:                { DEFAULT: 'var(--card)', foreground: 'var(--card-foreground)' },
                popover:             { DEFAULT: 'var(--popover)', foreground: 'var(--popover-foreground)' },
                primary:             { DEFAULT: 'var(--primary)', foreground: 'var(--primary-foreground)' },
                secondary:           { DEFAULT: 'var(--secondary)', foreground: 'var(--secondary-foreground)' },
                muted:               { DEFAULT: 'var(--muted)', foreground: 'var(--muted-foreground)' },
                shadcn_accent:       { DEFAULT: 'var(--accent)', foreground: 'var(--accent-foreground)' },
                destructive:         { DEFAULT: 'var(--destructive)', foreground: 'var(--primary-foreground)' },
                border:              'var(--border)',
                input:               'var(--input)',
                ring:                'var(--ring)',
                'chart-1':           'var(--chart-1)',
                'chart-2':           'var(--chart-2)',
                'chart-3':           'var(--chart-3)',
                'chart-4':           'var(--chart-4)',
                'chart-5':           'var(--chart-5)',
                sidebar:             {
                    DEFAULT:             'var(--sidebar)',
                    foreground:          'var(--sidebar-foreground)',
                    primary:             'var(--sidebar-primary)',
                    'primary-foreground':'var(--sidebar-primary-foreground)',
                    accent:              'var(--sidebar-accent)',
                    'accent-foreground': 'var(--sidebar-accent-foreground)',
                    border:              'var(--sidebar-border)',
                    ring:                'var(--sidebar-ring)',
                },
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
            /* ── shadcn-vue animation keyframes (TW3 compat, replaces tw-animate-css) ── */
            keyframes: {
                'accordion-down': {
                    from: { height: '0' },
                    to:   { height: 'var(--reka-accordion-content-height, auto)' },
                },
                'accordion-up': {
                    from: { height: 'var(--reka-accordion-content-height, auto)' },
                    to:   { height: '0' },
                },
                'fade-in': {
                    from: { opacity: '0' },
                    to:   { opacity: '1' },
                },
                'fade-out': {
                    from: { opacity: '1' },
                    to:   { opacity: '0' },
                },
                'zoom-in': {
                    from: { opacity: '0', transform: 'scale(0.95)' },
                    to:   { opacity: '1', transform: 'scale(1)' },
                },
                'zoom-out': {
                    from: { opacity: '1', transform: 'scale(1)' },
                    to:   { opacity: '0', transform: 'scale(0.95)' },
                },
                'slide-in-from-top': {
                    from: { opacity: '0', transform: 'translateY(-10px)' },
                    to:   { opacity: '1', transform: 'translateY(0)' },
                },
                'slide-in-from-bottom': {
                    from: { opacity: '0', transform: 'translateY(10px)' },
                    to:   { opacity: '1', transform: 'translateY(0)' },
                },
                'slide-in-from-left': {
                    from: { opacity: '0', transform: 'translateX(-10px)' },
                    to:   { opacity: '1', transform: 'translateX(0)' },
                },
                'slide-in-from-right': {
                    from: { opacity: '0', transform: 'translateX(10px)' },
                    to:   { opacity: '1', transform: 'translateX(0)' },
                },
                'slide-out-to-top': {
                    from: { opacity: '1', transform: 'translateY(0)' },
                    to:   { opacity: '0', transform: 'translateY(-10px)' },
                },
                'slide-out-to-bottom': {
                    from: { opacity: '1', transform: 'translateY(0)' },
                    to:   { opacity: '0', transform: 'translateY(10px)' },
                },
                'slide-out-to-left': {
                    from: { opacity: '1', transform: 'translateX(0)' },
                    to:   { opacity: '0', transform: 'translateX(-10px)' },
                },
                'slide-out-to-right': {
                    from: { opacity: '1', transform: 'translateX(0)' },
                    to:   { opacity: '0', transform: 'translateX(10px)' },
                },
                'caret-blink': {
                    '0%,70%,100%': { opacity: '1' },
                    '20%,50%':     { opacity: '0' },
                },
            },
            animation: {
                'accordion-down':      'accordion-down 0.2s ease-out',
                'accordion-up':        'accordion-up 0.2s ease-out',
                'fade-in':             'fade-in 0.1s ease-out',
                'fade-out':            'fade-out 0.1s ease-out',
                'zoom-in':             'zoom-in 0.1s ease-out',
                'zoom-out':            'zoom-out 0.1s ease-out',
                'slide-in-from-top':   'slide-in-from-top 0.2s ease-out',
                'slide-in-from-bottom':'slide-in-from-bottom 0.2s ease-out',
                'slide-in-from-left':  'slide-in-from-left 0.2s ease-out',
                'slide-in-from-right': 'slide-in-from-right 0.2s ease-out',
                'slide-out-to-top':    'slide-out-to-top 0.2s ease-out',
                'slide-out-to-bottom': 'slide-out-to-bottom 0.2s ease-out',
                'slide-out-to-left':   'slide-out-to-left 0.2s ease-out',
                'slide-out-to-right':  'slide-out-to-right 0.2s ease-out',
                'caret-blink':         'caret-blink 1.25s ease-out infinite',
            },
        },
    },

    plugins: [
        forms,
        /* shadcn-vue TW3 compatibility: data-state variants + animate-in/out utilities */
        plugin(function ({ addVariant, addUtilities, matchUtilities, theme }) {
            // data-open / data-closed variants (shadcn-vue v2 uses these)
            addVariant('data-open', [
                '&[data-state="open"]',
                '&[data-open]:not([data-open="false"])',
            ]);
            addVariant('data-closed', [
                '&[data-state="closed"]',
                '&[data-closed]:not([data-closed="false"])',
            ]);
            addVariant('data-checked', '&[data-state="checked"]');
            addVariant('data-unchecked', '&[data-state="unchecked"]');
            addVariant('data-selected', '&[data-selected="true"]');
            addVariant('data-disabled', [
                '&[data-disabled="true"]',
                '&[data-disabled]:not([data-disabled="false"])',
            ]);
            addVariant('data-active', '&[data-state="active"]');
            addVariant('data-horizontal', '&[data-orientation="horizontal"]');
            addVariant('data-vertical', '&[data-orientation="vertical"]');

            // animate-in / animate-out base classes
            addUtilities({
                '.animate-in': {
                    animationName: 'fade-in',
                    animationDuration: '0.1s',
                    animationTimingFunction: 'ease-out',
                    animationFillMode: 'both',
                },
                '.animate-out': {
                    animationName: 'fade-out',
                    animationDuration: '0.1s',
                    animationTimingFunction: 'ease-out',
                    animationFillMode: 'both',
                },
                // fade-in-0 / fade-out-0 (opacity zero start/end)
                '.fade-in-0': { '--tw-enter-opacity': '0' },
                '.fade-out-0': { '--tw-exit-opacity': '0' },
                // zoom-in-95 / zoom-out-95
                '.zoom-in-95': { '--tw-enter-scale': '0.95' },
                '.zoom-out-95': { '--tw-exit-scale': '0.95' },
                // slide-in-from-* (10px variants used by sheet)
                '.slide-in-from-top-10': {
                    animationName: 'slide-in-from-top',
                    '--slide-from': '-10px',
                },
                '.slide-in-from-bottom-10': {
                    animationName: 'slide-in-from-bottom',
                    '--slide-from': '10px',
                },
                '.slide-in-from-left-10': {
                    animationName: 'slide-in-from-left',
                    '--slide-from': '-10px',
                },
                '.slide-in-from-right-10': {
                    animationName: 'slide-in-from-right',
                    '--slide-from': '10px',
                },
                '.slide-out-to-top-10': {
                    animationName: 'slide-out-to-top',
                    '--slide-to': '-10px',
                },
                '.slide-out-to-bottom-10': {
                    animationName: 'slide-out-to-bottom',
                    '--slide-to': '10px',
                },
                '.slide-out-to-left-10': {
                    animationName: 'slide-out-to-left',
                    '--slide-to': '-10px',
                },
                '.slide-out-to-right-10': {
                    animationName: 'slide-out-to-right',
                    '--slide-to': '10px',
                },
            });
        }),
    ],
};
