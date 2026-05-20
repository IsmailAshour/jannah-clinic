<?php

namespace App\Domain\MedicalRecord\Services;

use App\Enums\MedicalAuditAction;
use App\Models\MedicalAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private Request $request) {}

    public function record(
        MedicalAuditAction $action,
        Model $auditable,
        User $patient,
        ?array $changedFields = null
    ): void {
        MedicalAuditLog::create([
            'user_id' => $this->request->user()?->id,
            'action' => $action->value,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'customer_id' => $patient->id,
            'changed_fields' => $changedFields,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->truncate((string) $this->request->userAgent(), 255),
        ]);
    }

    private function truncate(string $value, int $max): ?string
    {
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
