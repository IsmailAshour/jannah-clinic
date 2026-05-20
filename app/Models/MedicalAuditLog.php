<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'action', 'auditable_type', 'auditable_id', 'customer_id', 'changed_fields', 'ip_address', 'user_agent'])]
class MedicalAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $casts = [
        'changed_fields' => 'array',
    ];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('medical_audit_logs is append-only');
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw new \LogicException('medical_audit_logs is append-only');
    }
}
