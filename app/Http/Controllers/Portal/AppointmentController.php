<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Booking\Exceptions\InvalidBookingException;
use App\Domain\Booking\Exceptions\InvalidTransitionException;
use App\Domain\Booking\Exceptions\SlotUnavailableException;
use App\Domain\Booking\Services\AppointmentTransitionService;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $appointments = Appointment::query()
            ->where('customer_id', $request->user()->id)
            ->with(['services:id,name', 'doctor.user:id,name', 'serviceAddress', 'payment:id,appointment_id,status,amount'])
            ->orderByDesc('start_at')
            ->paginate(20);

        return Inertia::render('Portal/Appointments/Index', [
            'appointments' => $appointments,
        ]);
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('cancel', $appointment);

        $v = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            app(AppointmentTransitionService::class)->transition(
                $appointment,
                AppointmentStatus::Cancelled,
                $v['reason'],
                $request->user(),
            );
        } catch (InvalidTransitionException $e) {
            return back()->withErrors(['appointment' => $e->getMessage()]);
        }

        return back()->with('success', 'تم إلغاء الموعد.');
    }

    public function reschedule(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('reschedule', $appointment);

        $v = $request->validate([
            'start' => ['required', 'date'],
        ]);

        $startAt = CarbonImmutable::parse($v['start']);

        try {
            app(AppointmentTransitionService::class)->reschedule($appointment, $startAt, $request->user());
        } catch (InvalidTransitionException $e) {
            return back()->withErrors(['appointment' => $e->getMessage()]);
        } catch (SlotUnavailableException $e) {
            return back()->withErrors(['appointment' => $e->getMessage()]);
        } catch (InvalidBookingException $e) {
            return back()->withErrors(['appointment' => $e->getMessage()]);
        }

        return back()->with('success', 'تم إعادة جدولة الموعد بنجاح.');
    }
}
