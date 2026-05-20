<?php

namespace App\Domain\Auth\Services;

use Illuminate\Http\Request;

class IntentResolver
{
    public function resolve(Request $request, ?string $intent): string
    {
        if ($intent === null || $intent === '') {
            return route('portal.home');
        }

        return match ($intent) {
            'booking' => $this->bookingTarget($request),
            'appointments' => route('portal.appointments.index'),
            'loyalty' => route('portal.loyalty.index'),
            'medical-record' => route('portal.medical-record.index'),
            'profile' => route('portal.profile.edit'),
            'settings' => route('portal.settings.index'),
            default => route('portal.home'),
        };
    }

    private function bookingTarget(Request $request): string
    {
        $params = [];
        foreach (['service', 'doctor', 'category'] as $key) {
            if ($request->filled($key)) {
                $params[$key] = $request->input($key);
            }
        }

        return route('portal.booking.create', $params);
    }
}
