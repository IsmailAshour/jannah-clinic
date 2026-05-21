<?php

namespace App\Http\Controllers;

use App\Domain\Settings\Services\SettingService;
use Illuminate\Http\JsonResponse;

class PwaManifestController extends Controller
{
    /**
     * Dynamic web app manifest. Reads the clinic name + uploaded logo from
     * SettingService so when the admin updates either via /admin/settings,
     * subsequent installs (and existing installs after the manifest re-fetches)
     * pick up the new branding without a deploy.
     */
    public function __invoke(SettingService $settings): JsonResponse
    {
        $fullName = (string) $settings->get('clinic_name', (string) config('clinic.name', 'عيادة جنّة'));
        // Fixed short brand name for the home-screen icon — keeps the launcher
        // label clean regardless of the longer clinic display name.
        $shortName = 'جنة';
        // Dedicated transparent-bg PWA icon (overrides whatever clinic_logo_path
        // points to — that asset is JPEG with a white background and looks
        // wrong as a launcher icon, especially in masked / round modes).
        $iconUrl = url('/images/jannah_logo-removebg.png');

        return response()->json([
            'name' => $shortName,
            'short_name' => $shortName,
            'description' => 'احجز موعدك في '.$fullName.' بسهولة',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'lang' => 'ar',
            'dir' => 'rtl',
            'background_color' => '#ffffff',
            'theme_color' => '#0B4F2F',
            'icons' => [
                [
                    'src' => $iconUrl,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $iconUrl,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $iconUrl,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ], 200, [
            // Browsers expect this MIME for .webmanifest; charset matters for the Arabic name.
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
        ]);
    }
}
