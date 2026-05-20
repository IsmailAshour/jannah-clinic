<?php

namespace App\Http\Controllers\Portal;

use App\Domain\MedicalRecord\Services\AuditLogger;
use App\Enums\MedicalAuditAction;
use App\Http\Controllers\Controller;
use App\Models\MedicalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MedicalRecordController extends Controller
{
    public function index(Request $request, AuditLogger $audit): Response
    {
        $user = $request->user();
        $profile = $user->customerProfile;
        $entries = MedicalEntry::query()
            ->whereHas('appointment', fn ($q) => $q->where('customer_id', $user->id))
            ->with(['appointment:id,start_at', 'prescriptions'])
            ->latest()
            ->paginate(20);

        if ($profile && ($profile->chronic_conditions || $profile->allergies)) {
            $audit->record(MedicalAuditAction::ProfileMedicalViewed, $profile, $user);
        }

        return Inertia::render('Portal/MedicalRecord/Index', [
            'medicalProfile' => [
                'chronic_conditions' => $profile?->chronic_conditions,
                'allergies' => $profile?->allergies,
            ],
            'entries' => $entries->through(fn ($e) => [
                'id' => $e->id,
                'date' => $e->appointment->start_at->toIso8601String(),
                'visible_summary' => $e->visible_summary,
                'prescriptions' => $e->prescriptions->map(fn ($p) => [
                    'medication_name' => $p->medication_name,
                    'dosage' => $p->dosage,
                    'frequency' => $p->frequency,
                    'duration' => $p->duration,
                    'notes' => $p->notes,
                ])->all(),
            ]),
        ]);
    }

    public function show(MedicalEntry $entry, Request $request, AuditLogger $audit): Response
    {
        if ($entry->appointment->customer_id !== $request->user()->id) {
            abort(404);
        }
        $entry->load(['appointment', 'prescriptions']);
        $audit->record(MedicalAuditAction::EntryViewed, $entry, $request->user());

        return Inertia::render('Portal/MedicalRecord/Show', [
            'entry' => [
                'id' => $entry->id,
                'date' => $entry->appointment->start_at->toIso8601String(),
                'visible_summary' => $entry->visible_summary,
                'prescriptions' => $entry->prescriptions->map(fn ($p) => [
                    'medication_name' => $p->medication_name,
                    'dosage' => $p->dosage,
                    'frequency' => $p->frequency,
                    'duration' => $p->duration,
                    'notes' => $p->notes,
                ])->all(),
            ],
        ]);
    }
}
