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

    /**
     * Unified per-appointment detail page: customer + doctor + service + status
     * controls + Payment receipt inline + before/after photos. Lets staff
     * approve a receipt AND confirm/cancel/complete the appointment from one
     * surface instead of bouncing between /admin/payments and /admin/appointments.
     */
    public function show(Appointment $appointment): Response
    {
        $appointment->load([
            'customer:id,name,email,phone',
            'doctor:id,user_id,specialty',
            'doctor.user:id,name',
            'service:id,name,duration_minutes',
            'serviceAddress',
            'payment.receipts' => fn ($q) => $q->orderByDesc('id'),
            'photos.uploader:id,name',
            'medicalAttachments.uploader:id,name',
            'medicalEntry.prescriptions',
            'medicalEntry.author:id,name',
            'reminders' => fn ($q) => $q->orderBy('sent_at'),
        ]);

        /** @var \App\Models\User $authedUser */
        $authedUser = request()->user();
        $isDoctor = $authedUser->role === \App\Enums\UserRole::Doctor;
        $isManager = $authedUser->role === \App\Enums\UserRole::Manager;
        $canWriteMedical = $isDoctor || $isManager;

        $medicalEntryData = null;
        if ($appointment->medicalEntry) {
            $entry = $appointment->medicalEntry;
            $prescriptions = [];
            foreach ($entry->prescriptions as $p) {
                /** @var \App\Models\Prescription $p */
                $prescriptions[] = [
                    'id' => $p->id,
                    'medication_name' => $p->medication_name,
                    'dosage' => $p->dosage,
                    'frequency' => $p->frequency,
                    'duration' => $p->duration,
                    'notes' => $p->notes,
                ];
            }
            /** @var \App\Models\User|null $author */
            $author = $entry->author;
            $medicalEntryData = [
                'id' => $entry->id,
                'visible_summary' => $entry->visible_summary,
                // staff_notes is encrypted + sensitive — staff (manager/doctor) only.
                'staff_notes' => ($isManager || $isDoctor) ? $entry->staff_notes : null,
                'author_name' => $author?->name,
                'created_at' => $entry->created_at->toIso8601String(),
                'updated_at' => $entry->updated_at->toIso8601String(),
                'prescriptions' => $prescriptions,
            ];
        }

        $photos = [];
        foreach ($appointment->photos as $p) {
            /** @var \App\Models\AppointmentPhoto $p */
            /** @var User|null $uploader */
            $uploader = $p->uploader;
            $photos[] = [
                'id' => $p->id,
                'kind' => $p->kind->value,
                'caption' => $p->caption,
                'mime_type' => $p->mime_type,
                'file_url' => route('admin.appointments.photos.file', ['appointment' => $appointment->id, 'photo' => $p->id]),
                'created_at' => $p->created_at->toIso8601String(),
                'uploaded_by_name' => $uploader?->name,
            ];
        }

        $receipts = [];
        if ($appointment->payment) {
            foreach ($appointment->payment->receipts as $r) {
                /** @var \App\Models\PaymentReceipt $r */
                $receipts[] = [
                    'id' => $r->id,
                    'mime_type' => $r->mime_type,
                    'file_size' => $r->file_size,
                    'created_at' => $r->created_at->toIso8601String(),
                    'file_url' => route('admin.payments.receipt-file', ['payment' => $appointment->payment->id, 'receipt' => $r->id]),
                ];
            }
        }

        return Inertia::render('Admin/Appointments/Show', [
            'appointment' => [
                'id' => $appointment->id,
                'start_at' => $appointment->start_at->toIso8601String(),
                'end_at' => $appointment->end_at->toIso8601String(),
                'status' => $appointment->status->value,
                'delivery_mode' => $appointment->delivery_mode->value,
                'whatsapp_phone' => $appointment->whatsapp_phone,
                'price_at_booking' => $appointment->price_at_booking,
                'home_surcharge_amount' => $appointment->home_surcharge_amount,
                'cancellation_reason' => $appointment->cancellation_reason,
                'customer' => [
                    'id' => $appointment->customer->id,
                    'name' => $appointment->customer->name,
                    'email' => $appointment->customer->email,
                    'phone' => $appointment->customer->phone,
                ],
                'doctor' => [
                    'id' => $appointment->doctor->id,
                    'name' => $appointment->doctor->user->name,
                    'specialty' => $appointment->doctor->specialty,
                ],
                'service' => [
                    'id' => $appointment->service->id,
                    'name' => $appointment->service->name,
                    'duration_minutes' => $appointment->service->duration_minutes,
                ],
                'service_address' => $this->serializeAddress($appointment),
            ],
            'payment' => $appointment->payment ? [
                'id' => $appointment->payment->id,
                'amount' => $appointment->payment->amount,
                'status' => $appointment->payment->status->value,
                'rejection_reason' => $appointment->payment->rejection_reason,
                'verified_at' => $appointment->payment->verified_at?->toIso8601String(),
                'refund_reference' => $appointment->payment->refund_reference,
                'receipts' => $receipts,
            ] : null,
            'photos' => $photos,
            'medicalAttachments' => $this->serializeMedicalAttachments($appointment),
            'medicalEntry' => $medicalEntryData,
            'reminders' => $this->serializeReminders($appointment),
            'customerHasEmail' => $appointment->customer->email !== null && $appointment->customer->email !== '',
            'canWriteMedical' => $canWriteMedical,
            'canViewMedical' => $canWriteMedical,
        ]);
    }

    /**
     * @return array{address_text: string, location_note: string|null, lat: float|null, lng: float|null}|null
     */
    private function serializeAddress(Appointment $appointment): ?array
    {
        /** @var \App\Models\ServiceAddress|null $addr */
        $addr = $appointment->serviceAddress;
        if ($addr === null) {
            return null;
        }

        return [
            'address_text' => $addr->address_text,
            'location_note' => $addr->location_note,
            'lat' => $addr->lat !== null ? (float) $addr->lat : null,
            'lng' => $addr->lng !== null ? (float) $addr->lng : null,
        ];
    }

    /**
     * @return list<array{id:int,title:string|null,original_filename:string,mime_type:string,file_size:int,file_url:string,uploaded_by_name:string|null,created_at:string|null}>
     */
    private function serializeMedicalAttachments(Appointment $appointment): array
    {
        $out = [];
        foreach ($appointment->medicalAttachments as $a) {
            /** @var \App\Models\MedicalAttachment $a */
            /** @var \App\Models\User|null $uploader */
            $uploader = $a->uploader;
            $out[] = [
                'id' => $a->id,
                'title' => $a->title,
                'original_filename' => $a->original_filename,
                'mime_type' => $a->mime_type,
                'file_size' => $a->file_size,
                'file_url' => route('admin.appointments.medical-attachments.file', [
                    'appointment' => $appointment->id,
                    'attachment' => $a->id,
                ]),
                'uploaded_by_name' => $uploader?->name,
                'created_at' => $a->created_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id:int,kind:string,kind_label:string,sent_at:string,recipient_email:string}>
     */
    private function serializeReminders(Appointment $appointment): array
    {
        $out = [];
        foreach ($appointment->reminders as $r) {
            /** @var \App\Models\AppointmentReminder $r */
            $out[] = [
                'id' => $r->id,
                'kind' => $r->kind->value,
                'kind_label' => $r->kind->labelAr(),
                'sent_at' => $r->sent_at->toIso8601String(),
                'recipient_email' => $r->recipient_email,
            ];
        }

        return $out;
    }

    public function transition(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('manage', $appointment);

        $v = $request->validate([
            'status' => ['required', new Enum(AppointmentStatus::class)],
            'reason' => ['nullable', 'string', 'max:500', 'required_if:status,cancelled'],
        ]);

        if (AppointmentStatus::from($v['status']) === AppointmentStatus::Rescheduled) {
            return back()->withErrors(['appointment' => 'استخدم مسار إعادة الجدولة بدلاً من تغيير الحالة يدويًا.']);
        }

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
