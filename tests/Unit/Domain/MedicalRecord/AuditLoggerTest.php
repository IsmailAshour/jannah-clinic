<?php

use App\Domain\MedicalRecord\Services\AuditLogger;
use App\Enums\MedicalAuditAction;
use App\Enums\UserRole;
use App\Models\MedicalAuditLog;
use App\Models\MedicalEntry;
use App\Models\User;
use Illuminate\Http\Request;

it('records an entry-created audit row with full field set', function () {
    $doctor = User::factory()->create(['role' => UserRole::Doctor]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $entry = MedicalEntry::factory()->create(['author_id' => $doctor->id]);

    $req = Request::create('/x', 'POST', server: ['REMOTE_ADDR' => '1.2.3.4', 'HTTP_USER_AGENT' => 'pest']);
    $req->setUserResolver(fn () => $doctor);

    $logger = new AuditLogger($req);
    $logger->record(MedicalAuditAction::EntryCreated, $entry, $customer, ['visible_summary', 'staff_notes']);

    $row = MedicalAuditLog::firstWhere('action', 'entry.created');
    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($doctor->id)
        ->and($row->customer_id)->toBe($customer->id)
        ->and($row->ip_address)->toBe('1.2.3.4')
        ->and($row->user_agent)->toBe('pest')
        ->and($row->changed_fields)->toBe(['visible_summary', 'staff_notes']);
});

it('truncates long user_agent to 255 chars', function () {
    $doctor = User::factory()->create(['role' => UserRole::Doctor]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $entry = MedicalEntry::factory()->create(['author_id' => $doctor->id]);

    $long = str_repeat('A', 400);
    $req = Request::create('/x', 'POST', server: ['HTTP_USER_AGENT' => $long]);
    $req->setUserResolver(fn () => $doctor);

    (new AuditLogger($req))->record(MedicalAuditAction::EntryViewed, $entry, $customer);

    expect(MedicalAuditLog::first()->user_agent)->toHaveLength(255);
});
