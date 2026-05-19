<?php

namespace App\Http\Controllers\Booking;

use App\Domain\Booking\Services\AvailabilityService;
use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailableDaysController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $svc): JsonResponse
    {
        $data = $request->validate([
            'doctor' => ['required', 'exists:doctor_profiles,id'],
            'service' => ['required', 'exists:services,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
        $doctor = DoctorProfile::findOrFail($data['doctor']);
        $service = Service::findOrFail($data['service']);
        $from = CarbonImmutable::parse($data['from']);
        $to = CarbonImmutable::parse($data['to']);
        abort_if($from->diffInDays($to) > 62, 422, 'Range too large.');

        return response()->json($svc->availableDatesFor($doctor, $service, $from, $to));
    }
}
