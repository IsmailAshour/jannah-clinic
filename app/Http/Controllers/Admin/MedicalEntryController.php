<?php

namespace App\Http\Controllers\Admin;

use App\Domain\MedicalRecord\Services\AuditLogger;
use App\Domain\MedicalRecord\Services\MedicalEntryService;
use App\Domain\MedicalRecord\Services\PrescriptionService;
use App\Enums\MedicalAuditAction;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MedicalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MedicalEntryController extends Controller
{
    public function store(
        Request $request,
        Appointment $appointment,
        MedicalEntryService $entries,
        PrescriptionService $prescriptions,
    ): RedirectResponse {
        Gate::authorize('create', [MedicalEntry::class, $appointment]);

        $data = $request->validate([
            'visible_summary' => 'required|string',
            'staff_notes' => 'nullable|string',
            'prescriptions' => 'array',
            'prescriptions.*.medication_name' => 'required|string|max:255',
            'prescriptions.*.dosage' => 'required|string|max:255',
            'prescriptions.*.frequency' => 'required|string|max:255',
            'prescriptions.*.duration' => 'required|string|max:255',
            'prescriptions.*.notes' => 'nullable|string',
            'return_to' => 'nullable|string|max:1024',
        ]);

        $entry = $entries->create($appointment, $request->user(), $data);
        $prescriptions->syncForEntry($entry, $data['prescriptions'] ?? []);

        return $this->redirectAfterSave($data['return_to'] ?? null, $entry)
            ->with('success', 'تم حفظ السجل الطبي.');
    }

    public function create(Appointment $appointment): Response|RedirectResponse
    {
        Gate::authorize('create', [MedicalEntry::class, $appointment]);

        $appointment->load('customer', 'medicalEntry.prescriptions');

        // If an entry already exists for this appointment, redirect to its edit
        // page (the unique FK constraint forbids a second). Otherwise render the
        // form in "new" mode — no DB write happens until the doctor submits.
        if ($appointment->medicalEntry) {
            return redirect()->route('admin.medical-entries.edit', $appointment->medicalEntry);
        }

        return Inertia::render('Admin/MedicalEntries/Edit', [
            'entry' => null,
            'prescriptions' => [],
            'appointment' => [
                'id' => $appointment->id,
                'start_at' => $appointment->start_at->toIso8601String(),
            ],
            'customer' => [
                'id' => $appointment->customer->id,
                'name' => $appointment->customer->name,
            ],
        ]);
    }

    public function edit(MedicalEntry $entry, AuditLogger $audit): Response
    {
        Gate::authorize('view', $entry);
        $entry->load(['appointment.customer', 'appointment.medicalAttachments.uploader:id,name', 'prescriptions']);

        $audit->record(MedicalAuditAction::EntryViewed, $entry, $entry->appointment->customer);

        $attachments = [];
        foreach ($entry->appointment->medicalAttachments as $a) {
            /** @var \App\Models\MedicalAttachment $a */
            /** @var \App\Models\User|null $uploader */
            $uploader = $a->uploader;
            $attachments[] = [
                'id' => $a->id,
                'title' => $a->title,
                'original_filename' => $a->original_filename,
                'mime_type' => $a->mime_type,
                'file_size' => $a->file_size,
                'file_url' => route('admin.appointments.medical-attachments.file', [
                    'appointment' => $entry->appointment->id,
                    'attachment' => $a->id,
                ]),
                'uploaded_by_name' => $uploader?->name,
                'created_at' => $a->created_at?->toIso8601String(),
            ];
        }

        return Inertia::render('Admin/MedicalEntries/Edit', [
            'entry' => [
                'id' => $entry->id,
                'visible_summary' => $entry->visible_summary,
                'staff_notes' => $entry->staff_notes,
            ],
            'prescriptions' => $entry->prescriptions->map->only([
                'id', 'medication_name', 'dosage', 'frequency', 'duration', 'notes',
            ]),
            'attachments' => $attachments,
            'appointment' => [
                'id' => $entry->appointment->id,
                'start_at' => $entry->appointment->start_at->toIso8601String(),
            ],
            'customer' => [
                'id' => $entry->appointment->customer->id,
                'name' => $entry->appointment->customer->name,
            ],
        ]);
    }

    public function update(
        Request $request,
        MedicalEntry $entry,
        MedicalEntryService $entries,
        PrescriptionService $prescriptions,
    ): RedirectResponse {
        Gate::authorize('update', $entry);

        $data = $request->validate([
            'visible_summary' => 'required|string',
            'staff_notes' => 'nullable|string',
            'prescriptions' => 'array',
            'prescriptions.*.id' => 'nullable|integer|exists:prescriptions,id',
            'prescriptions.*.medication_name' => 'required|string|max:255',
            'prescriptions.*.dosage' => 'required|string|max:255',
            'prescriptions.*.frequency' => 'required|string|max:255',
            'prescriptions.*.duration' => 'required|string|max:255',
            'prescriptions.*.notes' => 'nullable|string',
            'return_to' => 'nullable|string|max:1024',
        ]);

        $entries->update($entry, $data);
        $prescriptions->syncForEntry($entry, $data['prescriptions'] ?? []);

        return $this->redirectAfterSave($data['return_to'] ?? null, $entry)
            ->with('success', 'تم تحديث السجل الطبي.');
    }

    /**
     * Honour the form's optional return_to ONLY if it's a same-origin path
     * (starts with a single `/`, never `//` which would be protocol-relative).
     * Anything else falls back to the canonical edit page. This blocks open
     * redirects via crafted Referer / query-string values.
     */
    private function redirectAfterSave(?string $returnTo, MedicalEntry $entry): RedirectResponse
    {
        if (
            is_string($returnTo)
            && $returnTo !== ''
            && str_starts_with($returnTo, '/')
            && ! str_starts_with($returnTo, '//')
        ) {
            return redirect($returnTo);
        }

        return redirect()->route('admin.medical-entries.edit', $entry);
    }
}
