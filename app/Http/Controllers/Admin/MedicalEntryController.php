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
        ]);

        $entry = $entries->create($appointment, $request->user(), $data);
        $prescriptions->syncForEntry($entry, $data['prescriptions'] ?? []);

        return redirect()->route('admin.medical-entries.edit', $entry)
            ->with('success', 'تم حفظ السجل الطبي.');
    }

    public function create(Appointment $appointment, Request $request): RedirectResponse
    {
        Gate::authorize('create', [MedicalEntry::class, $appointment]);

        $entry = MedicalEntry::firstOrCreate(
            ['appointment_id' => $appointment->id],
            ['author_id' => $request->user()->id, 'visible_summary' => '—'],
        );

        return redirect()->route('admin.medical-entries.edit', $entry);
    }

    public function edit(MedicalEntry $entry, AuditLogger $audit): Response
    {
        Gate::authorize('view', $entry);
        $entry->load(['appointment.customer', 'prescriptions']);

        $audit->record(MedicalAuditAction::EntryViewed, $entry, $entry->appointment->customer);

        return Inertia::render('Admin/MedicalEntries/Edit', [
            'entry' => [
                'id' => $entry->id,
                'visible_summary' => $entry->visible_summary,
                'staff_notes' => $entry->staff_notes,
            ],
            'prescriptions' => $entry->prescriptions->map->only([
                'id', 'medication_name', 'dosage', 'frequency', 'duration', 'notes',
            ]),
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
        ]);

        $entries->update($entry, $data);
        $prescriptions->syncForEntry($entry, $data['prescriptions'] ?? []);

        return redirect()->route('admin.medical-entries.edit', $entry)
            ->with('success', 'تم تحديث السجل الطبي.');
    }
}
