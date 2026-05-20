<?php

namespace App\Http\Controllers\Public;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $featuredServices = Service::query()
            ->where('is_active', true)
            ->withCount(['appointments' => fn ($q) => $q->where('created_at', '>=', now()->subDays(30))])
            ->orderByDesc('appointments_count')
            ->orderBy('display_order')
            ->limit(4)
            ->with('category:id,name,color_variant')
            ->get();

        $featuredDoctor = DoctorProfile::query()
            ->where('is_bookable', true)
            ->orderByDesc('rating_average')
            ->orderBy('display_order')
            ->with('user:id,name')
            ->first();

        $tips = (array) config('clinic.tips', []);
        $tip = $tips === [] ? null : $tips[array_rand($tips)];

        $greetingName = null;
        $nextAppointment = null;
        if ($request->user()) {
            $greetingName = $request->user()->name;
            $nextAppointment = Appointment::query()
                ->where('customer_id', $request->user()->id)
                ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Requested])
                ->where('start_at', '>=', now())
                ->orderBy('start_at')
                ->with(['service:id,name', 'doctor.user:id,name'])
                ->first();
        }

        return Inertia::render('Public/Home', [
            'featuredServices' => $featuredServices,
            'featuredDoctor' => $featuredDoctor,
            'tip' => $tip,
            'greetingName' => $greetingName,
            'nextAppointment' => $nextAppointment,
        ]);
    }
}
