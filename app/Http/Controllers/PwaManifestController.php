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
        $name = (string) $settings->get('clinic_name', (string) config('clinic.name', 'عيادة جنّة'));
        $logoPath = (string) $settings->get('clinic_logo_path', '');
        $iconUrl = $logoPath !== ''
            ? url('/storage/'.$logoPath)
            : url('/images/clinic-logo.jpg');

        return response()->json([
            'name' => $name,
            'short_name' => mb_substr($name, 0, 12),
            'description' => 'احجز موعدك في '.$name.' بسهولة',
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
