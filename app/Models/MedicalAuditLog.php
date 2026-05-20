<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string $auditable_type
 * @property int $auditable_id
 * @property int $customer_id
 * @property array<int, string>|null $changed_fields
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
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
