<?php

namespace App\Domain\MedicalRecord\Services;

use App\Domain\Notification\Services\NotificationService;
use App\Enums\MedicalAuditAction;
use App\Models\Appointment;
use App\Models\MedicalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MedicalEntryService
{
    public function __construct(
        private AuditLogger $audit,
        private NotificationService $notifications,
    ) {}

    public function create(Appointment $appointment, User $author, array $data): MedicalEntry
    {
        return DB::transaction(function () use ($appointment, $author, $data) {
            $entry = MedicalEntry::create([
                'appointment_id' => $appointment->id,
                'author_id' => $author->id,
                'visible_summary' => $data['visible_summary'],
                'staff_notes' => $data['staff_notes'] ?? null,
            ]);
            $this->audit->record(
                MedicalAuditAction::EntryCreated,
                $entry,
                $appointment->customer,
                ['visible_summary', 'staff_notes'],
            );
            $this->notifications->medicalEntryCreated($entry->fresh()->load('appointment.customer'));

            return $entry;
        });
    }

    public function update(MedicalEntry $entry, array $data): MedicalEntry
    {
        return DB::transaction(function () use ($entry, $data) {
            $entry->fill([
                'visible_summary' => $data['visible_summary'] ?? $entry->visible_summary,
                'staff_notes' => array_key_exists('staff_notes', $data) ? $data['staff_notes'] : $entry->staff_notes,
            ]);
            $dirty = array_keys($entry->getDirty());
            $entry->save();
            if ($dirty !== []) {
                $this->audit->record(
                    MedicalAuditAction::EntryUpdated,
                    $entry,
                    $entry->appointment->customer,
                    $dirty,
                );
            }

            return $entry;
        });
    }
}
