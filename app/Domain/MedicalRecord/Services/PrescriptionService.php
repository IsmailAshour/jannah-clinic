<?php

namespace App\Domain\MedicalRecord\Services;

use App\Domain\Notification\Services\NotificationService;
use App\Enums\MedicalAuditAction;
use App\Models\MedicalEntry;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;

class PrescriptionService
{
    public function __construct(
        private AuditLogger $audit,
        private NotificationService $notifications,
    ) {}

    public function syncForEntry(MedicalEntry $entry, array $desired): void
    {
        $createdIds = [];
        DB::transaction(function () use ($entry, $desired, &$createdIds) {
            $existing = $entry->prescriptions()->get()->keyBy('id');
            $keepIds = [];
            foreach ($desired as $row) {
                $row = $this->normalize($row);
                if (isset($row['id']) && $existing->has($row['id'])) {
                    /** @var Prescription $p */
                    $p = $existing[$row['id']];
                    $update = $row;
                    unset($update['id']);
                    $p->fill($update);
                    $dirty = array_keys($p->getDirty());
                    if ($dirty !== []) {
                        $p->save();
                        $this->audit->record(
                            MedicalAuditAction::PrescriptionUpdated,
                            $p,
                            $entry->appointment->customer,
                            $dirty,
                        );
                    }
                    $keepIds[] = $p->id;
                } else {
                    unset($row['id']);
                    /** @var Prescription $created */
                    $created = $entry->prescriptions()->create($row);
                    $this->audit->record(
                        MedicalAuditAction::PrescriptionCreated,
                        $created,
                        $entry->appointment->customer,
                        ['medication_name', 'dosage', 'frequency', 'duration', 'notes'],
                    );
                    $createdIds[] = $created->id;
                    $keepIds[] = $created->id;
                }
            }
            $toDelete = $existing->keys()->diff($keepIds);
            foreach ($toDelete as $id) {
                /** @var Prescription $p */
                $p = $existing[$id];
                $this->audit->record(
                    MedicalAuditAction::PrescriptionDeleted,
                    $p,
                    $entry->appointment->customer,
                );
                $p->delete();
            }
        });
        // Notify AFTER the transaction commits. Per-prescription dispatch lets
        // a single failure log + continue without blocking the others.
        if ($createdIds !== []) {
            foreach (Prescription::query()->whereIn('id', $createdIds)->with('entry.appointment.customer')->get() as $created) {
                $this->notifications->prescriptionAdded($created);
            }
        }
    }

    private function normalize(array $row): array
    {
        return array_intersect_key(
            $row + ['notes' => null],
            array_flip(['id', 'medication_name', 'dosage', 'frequency', 'duration', 'notes']),
        );
    }
}
