<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\ScheduleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DoctorScheduleController extends Controller
{
    public function editSchedule(DoctorProfile $doctor): Response
    {
        return Inertia::render('Admin/Doctors/Schedule', [
            'doctor' => $doctor->load('user'),
            'schedules' => $doctor->schedules()->orderBy('weekday')->get(),
            'exceptions' => $doctor->scheduleExceptions()->orderBy('date')->get(),
        ]);
    }

    public function saveSchedule(Request $request, DoctorProfile $doctor): RedirectResponse
    {
        $request->validate([
            'schedules' => ['array'],
            'schedules.*.weekday' => ['required', 'integer', 'between:0,6'],
            'schedules.*.morning_enabled' => ['boolean'],
            'schedules.*.evening_enabled' => ['boolean'],
            'schedules.*.morning_start' => ['nullable', 'date_format:H:i'],
            'schedules.*.morning_end' => ['nullable', 'date_format:H:i'],
            'schedules.*.evening_start' => ['nullable', 'date_format:H:i'],
            'schedules.*.evening_end' => ['nullable', 'date_format:H:i'],
            'schedules.*.slot_interval_minutes' => ['required', 'integer', 'min:5'],
        ]);

        foreach ($request->input('schedules', []) as $row) {
            DoctorSchedule::updateOrCreate(
                [
                    'doctor_profile_id' => $doctor->id,
                    'weekday' => $row['weekday'],
                ],
                [
                    'morning_enabled' => $row['morning_enabled'] ?? false,
                    'morning_start' => $row['morning_start'] ?? null,
                    'morning_end' => $row['morning_end'] ?? null,
                    'evening_enabled' => $row['evening_enabled'] ?? false,
                    'evening_start' => $row['evening_start'] ?? null,
                    'evening_end' => $row['evening_end'] ?? null,
                    'slot_interval_minutes' => $row['slot_interval_minutes'],
                ]
            );
        }

        return back();
    }

    public function addException(Request $request, DoctorProfile $doctor): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', 'in:closed,custom_hours'],
            'custom_start' => ['nullable', 'date_format:H:i'],
            'custom_end' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $doctor->scheduleExceptions()->updateOrCreate(
            ['date' => $data['date']],
            [
                'type' => $data['type'],
                'custom_start' => $data['custom_start'] ?? null,
                'custom_end' => $data['custom_end'] ?? null,
                'note' => $data['note'] ?? null,
            ]
        );

        return back();
    }

    public function deleteException(DoctorProfile $doctor, ScheduleException $exception): RedirectResponse
    {
        abort_unless($exception->doctor_profile_id === $doctor->id, 404);
        $exception->delete();

        return back();
    }
}
