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
        if ($request->has('service') && ! $request->has('services')) {
            $request->merge(['services' => [$request->input('service')]]);
        }

        $data = $request->validate([
            'doctor' => ['required', 'exists:doctor_profiles,id'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['integer', 'distinct', 'exists:services,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
        $doctor = DoctorProfile::findOrFail($data['doctor']);
        $services = Service::whereIn('id', $data['services'])->get();
        $from = CarbonImmutable::parse($data['from']);
        $to = CarbonImmutable::parse($data['to']);
        abort_if($from->diffInDays($to) > 62, 422, 'Range too large.');

        return response()->json($svc->availableDatesForServices($doctor, $services, $from, $to));
    }
}
