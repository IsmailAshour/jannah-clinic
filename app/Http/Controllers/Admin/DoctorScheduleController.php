<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\Slots\SlotGrid;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPhoto;
use App\Models\DoctorProfile;
use App\Models\DoctorScheduleSlot;
use App\Models\ScheduleException;
use App\Models\ScheduleExceptionSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DoctorScheduleController extends Controller
{
    public function editSchedule(DoctorProfile $doctor): Response
    {
        /** @var array<int,list<string>> $slots */
        $slots = [];
        foreach (range(0, 6) as $weekday) {
            $slots[$weekday] = [];
        }
        foreach ($doctor->scheduleSlots()->orderBy('slot_start')->get() as $row) {
            /** @var DoctorScheduleSlot $row */
            $slots[(int) $row->weekday][] = (string) $row->slot_start;
        }

        /** @var Collection<int,ScheduleException> $exceptionModels */
        $exceptionModels = $doctor->scheduleExceptions()
            ->with('slots')
            ->orderBy('date')
            ->get();
        $exceptions = $exceptionModels
            ->map(fn (ScheduleException $ex) => [
                'id' => $ex->id,
                'date' => $ex->date->format('Y-m-d'),
                'type' => $ex->type,
                'note' => $ex->note,
                'slots' => $ex->slots->pluck('slot_start')->map(fn ($s) => (string) $s)->all(),
            ])
            ->all();

        /** @var User $user */
        $user = $doctor->user;

        return Inertia::render('Admin/Doctors/Schedule', [
            'doctor' => [
                'id' => $doctor->id,
                'name' => $user->name,
            ],
            'grid' => [
                'morning' => SlotGrid::morning(),
                'evening' => SlotGrid::evening(),
            ],
            'slots' => $slots,
            'exceptions' => $exceptions,
        ]);
    }

    public function saveSchedule(Request $request, DoctorProfile $doctor): RedirectResponse
    {
        $slots = $request->input('slots');
        if (! is_array($slots)) {
            return back()->withErrors(['schedule' => 'فترة غير صالحة.']);
        }

        /** @var array<int,list<string>> $clean */
        $clean = [];
        foreach ($slots as $weekday => $values) {
            if (! is_numeric($weekday) || (int) $weekday < 0 || (int) $weekday > 6 || (string) (int) $weekday !== (string) $weekday) {
                return back()->withErrors(['schedule' => 'فترة غير صالحة.']);
            }
            if (! is_array($values)) {
                return back()->withErrors(['schedule' => 'فترة غير صالحة.']);
            }
            $set = [];
            foreach ($values as $value) {
                if (! is_string($value) || ! SlotGrid::isValid($value)) {
                    return back()->withErrors(['schedule' => 'فترة غير صالحة.']);
                }
                $set[$value] = true;
            }
            $clean[(int) $weekday] = array_keys($set);
        }

        DB::transaction(function () use ($doctor, $clean): void {
            foreach (range(0, 6) as $weekday) {
                $doctor->scheduleSlots()->where('weekday', $weekday)->delete();
                $values = $clean[$weekday] ?? [];
                if ($values === []) {
                    continue;
                }
                $now = now();
                DoctorScheduleSlot::insert(array_map(fn (string $s) => [
                    'doctor_profile_id' => $doctor->id,
                    'weekday' => $weekday,
                    'slot_start' => $s,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $values));
            }
        });

        return back()->with('success', 'تم حفظ الجدول');
    }

    public function addException(Request $request, DoctorProfile $doctor): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', 'in:closed,custom'],
            'slots' => ['array', 'required_if:type,custom', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_array($value)) {
                    return;
                }
                foreach ($value as $slot) {
                    if (! is_string($slot) || ! SlotGrid::isValid($slot)) {
                        $fail('فترة غير صالحة.');

                        return;
                    }
                }
            }],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $date = CarbonImmutable::parse($data['date'])->toDateString();

        DB::transaction(function () use ($doctor, $data, $date): void {
            /** @var ScheduleException $exception */
            $exception = $doctor->scheduleExceptions()
                ->whereDate('date', $date)
                ->first()
                ?? $doctor->scheduleExceptions()->make(['date' => $date]);

            $exception->type = $data['type'];
            $exception->note = $data['note'] ?? null;
            $exception->save();

            $exception->slots()->delete();

            if ($data['type'] === 'custom') {
                /** @var list<string> $values */
                $values = array_values(array_unique($data['slots'] ?? []));
                $now = now();
                ScheduleExceptionSlot::insert(array_map(fn (string $s) => [
                    'schedule_exception_id' => $exception->id,
                    'slot_start' => $s,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $values));
            }
        });

        return back()->with('success', 'تمت إضافة الاستثناء');
    }

    public function deleteException(DoctorProfile $doctor, ScheduleException $exception): RedirectResponse
    {
        abort_unless($exception->doctor_profile_id === $doctor->id, 404);
        $exception->delete();

        return back()->with('success', 'تم حذف الاستثناء');
    }

    /**
     * Day-view for a single doctor — timeline of every appointment on the
     * given date (default: today). Used by the doctor to plan their day and
     * by managers to monitor capacity.
     */
    public function day(Request $request, DoctorProfile $doctor): Response
    {
        $dateInput = (string) $request->input('date', '');
        $date = $dateInput !== ''
            ? CarbonImmutable::parse($dateInput)
            : CarbonImmutable::today();

        $doctor->load('user:id,name');

        $appointments = Appointment::query()
            ->where('doctor_profile_id', $doctor->id)
            ->whereBetween('start_at', [$date->startOfDay(), $date->endOfDay()])
            ->with([
                'customer:id,name,phone,email',
                'service:id,name,duration_minutes',
                'photos' => fn ($q) => $q->orderBy('id'),
                'photos.uploader:id,name',
            ])
            ->orderBy('start_at')
            ->get();

        // Reshape for the page — keep payload small + predictable in TS shape.
        $items = [];
        foreach ($appointments as $a) {
            /** @var Appointment $a */
            $photos = [];
            foreach ($a->photos as $p) {
                /** @var AppointmentPhoto $p */
                /** @var User|null $uploader */
                $uploader = $p->uploader;
                $photos[] = [
                    'id' => $p->id,
                    'kind' => $p->kind->value,
                    'caption' => $p->caption,
                    'mime_type' => $p->mime_type,
                    'file_url' => route('admin.appointments.photos.file', ['appointment' => $a->id, 'photo' => $p->id]),
                    'created_at' => $p->created_at->toIso8601String(),
                    'uploaded_by_name' => $uploader?->name,
                ];
            }
            $items[] = [
                'id' => $a->id,
                'start_at' => $a->start_at->toIso8601String(),
                'end_at' => $a->end_at->toIso8601String(),
                'time' => $a->start_at->format('H:i'),
                'status' => $a->status->value,
                'delivery_mode' => $a->delivery_mode->value,
                'whatsapp_phone' => $a->whatsapp_phone,
                'price_at_booking' => $a->price_at_booking,
                'customer' => [
                    'id' => $a->customer->id,
                    'name' => $a->customer->name,
                    'phone' => $a->customer->phone,
                    'email' => $a->customer->email,
                ],
                'service' => [
                    'name' => $a->service->name,
                    'duration_minutes' => $a->service->duration_minutes,
                ],
                'photos' => $photos,
            ];
        }

        return Inertia::render('Admin/Doctors/Day', [
            'doctor' => [
                'id' => $doctor->id,
                'name' => $doctor->user->name,
                'specialty' => $doctor->specialty,
            ],
            'date' => $date->toDateString(),
            'prev_date' => $date->subDay()->toDateString(),
            'next_date' => $date->addDay()->toDateString(),
            'today' => CarbonImmutable::today()->toDateString(),
            'appointments' => $items,
        ]);
    }
}
