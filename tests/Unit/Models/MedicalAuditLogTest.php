<?php

use App\Enums\MedicalAuditAction;
use App\Models\MedicalAuditLog;
use App\Models\User;

it('throws on save after create (append-only)', function () {
    $u = User::factory()->create();
    $log = MedicalAuditLog::create([
        'user_id' => $u->id,
        'action' => MedicalAuditAction::EntryCreated->value,
        'auditable_type' => 'App\Models\MedicalEntry',
        'auditable_id' => 1,
        'customer_id' => $u->id,
    ]);

    $log->action = MedicalAuditAction::EntryUpdated->value;

    expect(fn () => $log->save())->toThrow(\LogicException::class, 'append-only');
});

it('throws on delete (append-only)', function () {
    $u = User::factory()->create();
    $log = MedicalAuditLog::create([
        'user_id' => $u->id,
        'action' => MedicalAuditAction::EntryCreated->value,
        'auditable_type' => 'App\Models\MedicalEntry',
        'auditable_id' => 1,
        'customer_id' => $u->id,
    ]);

    expect(fn () => $log->delete())->toThrow(\LogicException::class, 'append-only');
});
