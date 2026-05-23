<?php

namespace App\Http\Controllers\Public;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = ServiceCategory::query()
            ->where('is_active', true)
            ->withCount(['services' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('display_order')
            ->limit(6)
            ->get(['id', 'name', 'slug', 'color_variant', 'icon_key']);

        $doctors = DoctorProfile::query()
            ->where('is_bookable', true)
            ->orderBy('display_order')
            ->limit(4)
            ->with('user:id,name')
            ->get(['id', 'user_id', 'specialty', 'image_path', 'team_role']);

        $featuredServices = Service::query()
            ->where('is_active', true)
            ->where('is_featured', true)
            ->with('category:id,name,color_variant,icon_key')
            ->orderByRaw('image_path IS NULL')
            ->orderBy('display_order')
            ->limit(4)
            ->get(['id', 'category_id', 'name', 'description', 'base_price', 'duration_minutes', 'image_path']);

        $tips = (array) config('clinic.tips', []);
        $tip = $tips === [] ? null : $tips[array_rand($tips)];

        $greetingName = null;
        $upcomingAppointments = [];
        $loyaltyBalance = 0;
        if ($request->user()) {
            $user = $request->user();
            $greetingName = $user->name;
            $upcomingAppointments = Appointment::query()
                ->where('customer_id', $user->id)
                ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Requested])
                ->where('start_at', '>=', now())
                ->orderBy('start_at')
                ->limit(3)
                ->with(['service:id,name', 'doctor.user:id,name', 'payment:id,appointment_id,status'])
                ->get();
            $loyaltyBalance = $user->customerProfile
                ? (int) $user->customerProfile->loyalty_balance
                : 0;
        }

        return Inertia::render('Public/Home', [
            'categories' => $categories,
            'featuredServices' => $featuredServices,
            'doctors' => $doctors,
            'tip' => $tip,
            'greetingName' => $greetingName,
            'upcomingAppointments' => $upcomingAppointments,
            'loyaltyBalance' => $loyaltyBalance,
        ]);
    }
}
