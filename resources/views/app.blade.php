<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0B4F2F">

        {{-- PWA: Chrome's 'Install app' prompt needs a manifest + a service
             worker. iOS Safari uses the apple-* tags for 'Add to Home Screen'. --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ $seoClinicName }}">
        <link rel="apple-touch-icon" href="{{ $seoImageUrl }}">

        {{-- Title: per-page Inertia <Head> can override via <title inertia>. --}}
        <title inertia>{{ $seoClinicName }}</title>

        {{-- Standard SEO --}}
        <meta name="description" content="{{ $seoDescription }}">
        <link rel="canonical" href="{{ $seoUrl }}">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">

        {{-- Open Graph (Facebook / WhatsApp / Telegram / LinkedIn) --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $seoClinicName }}">
        <meta property="og:locale" content="{{ $seoLocale }}">
        <meta property="og:title" content="{{ $seoClinicName }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:image" content="{{ $seoImageUrl }}">
        <meta property="og:image:alt" content="{{ $seoClinicName }}">
        <meta property="og:url" content="{{ $seoUrl }}">

        {{-- Twitter Card --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoClinicName }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $seoImageUrl }}">

        {{-- JSON-LD MedicalBusiness — keys are escaped with @@ because Blade 11+ owns the @context directive. --}}
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "MedicalBusiness",
            "name": @json($seoClinicName),
            "description": @json($seoDescription),
            "image": @json($seoImageUrl),
            "url": @json(url('/'))
        }
        </script>

        <!-- Fonts: Cairo self-hosted via Vite asset pipeline (see resources/css/app.css) -->

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
