<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Calendar feed for the admin dashboard's month view. Returns every
     * appointment whose start_at falls within [from, to] in a tight JSON
     * shape so the calendar can render dots-per-day and a per-day list
     * without separate per-row lookups.
     *
     * Booked/Active statuses only — cancelled, rejected, rescheduled, and
     * no_show are excluded so the calendar reflects what actually consumes
     * a slot. completed appointments stay visible so admins see historical
     * context inside the current month.
     */
    public function calendar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();

        $appts = Appointment::query()
            ->whereBetween('start_at', [$from, $to])
            ->whereIn('status', [
                AppointmentStatus::Requested,
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
            ])
            ->with([
                'customer:id,name',
                'service:id,name',
                'doctor:id,user_id',
                'doctor.user:id,name',
            ])
            ->orderBy('start_at')
            ->get(['id', 'customer_id', 'doctor_profile_id', 'service_id', 'start_at', 'status', 'delivery_mode']);

        return response()->json($appts->map(fn (Appointment $a) => [
            'id' => $a->id,
            'start_at' => $a->start_at->toIso8601String(),
            'date' => $a->start_at->toDateString(),
            'time' => $a->start_at->format('H:i'),
            'status' => $a->status->value,
            'delivery_mode' => $a->delivery_mode->value,
            'customer_name' => $a->customer->name,
            'doctor_name' => $a->doctor->user->name,
            'service_name' => $a->service->name,
        ])->values());
    }
}
