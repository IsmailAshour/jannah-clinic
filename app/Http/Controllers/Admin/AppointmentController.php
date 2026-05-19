<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\Exceptions\InvalidBookingException;
use App\Domain\Booking\Exceptions\InvalidTransitionException;
use App\Domain\Booking\Exceptions\SlotUnavailableException;
use App\Domain\Booking\Services\AppointmentTransitionService;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Appointment::query()
            ->with(['customer:id,name', 'doctor.user:id,name', 'service:id,name', 'serviceAddress'])
            ->orderByDesc('start_at');

        if ($request->filled('status') && in_array($request->input('status'), array_column(AppointmentStatus::cases(), 'value'), true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('doctor')) {
            $query->where('doctor_profile_id', (int) $request->input('doctor'));
        }

        if ($request->filled('date')) {
            $query->whereDate('start_at', $request->input('date'));
        }

        $appointments = $query->paginate(20)->withQueryString();

        /** @var list<array{id:int,name:string}> $doctors */
        $doctors = DoctorProfile::with('user:id,name')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(function (DoctorProfile $d) {
                /** @var User $user */
                $user = $d->user;

                return ['id' => $d->id, 'name' => $user->name];
            })
            ->all();

        $statusOptions = array_map(fn (AppointmentStatus $s) => ['value' => $s->value], AppointmentStatus::cases());

        return Inertia::render('Admin/Appointments/Index', [
            'appointments' => $appointments,
            'doctors' => $doctors,
            'statusOptions' => $statusOptions,
            'filters' => $request->only(['status', 'doctor', 'date']),
        ]);
    }

    public function transition(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('manage', $appointment);

        $v = $request->validate([
            'status' => ['required', new Enum(AppointmentStatus::class)],
            'reason' => ['nullable', 'string', 'max:500', 'required_if:status,cancelled'],
        ]);

        try {
            app(AppointmentTransitionService::class)->transition(
                $appointment,
                AppointmentStatus::from($v['status']),
                $v['reason'] ?? null
            );
        } catch (InvalidTransitionException $e) {
            return back()->withErrors(['appointment' => $e->getMessage()]);
        } catch (SlotUnavailableException $e) {
            return back()->withErrors(['appointment' => $e->getMessage()]);
        } catch (InvalidBookingException $e) {
            return back()->withErrors(['appointment' => $e->getMessage()]);
        }

        return back()->with('success', 'تم تحديث حالة الموعد.');
    }
}
