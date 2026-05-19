<?php

namespace App\Http\Controllers\Booking;

use App\Domain\Booking\Services\AvailabilityService;
use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $svc): JsonResponse
    {
        $data = $request->validate([
            'doctor' => ['required', 'exists:doctor_profiles,id'],
            'service' => ['required', 'exists:services,id'],
            'date' => ['required', 'date'],
        ]);
        $doctor = DoctorProfile::findOrFail($data['doctor']);
        $service = Service::findOrFail($data['service']);
        $slots = $svc->slotsFor($doctor, $service, CarbonImmutable::parse($data['date']));

        return response()->json(array_map(fn ($s) => [
            'start' => $s['start']->toIso8601String(),
            'end' => $s['end']->toIso8601String(),
            'label' => $s['start']->format('H:i'),
        ], $slots));
    }
}
