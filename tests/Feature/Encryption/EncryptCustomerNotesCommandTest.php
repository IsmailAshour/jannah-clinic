<?php

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

it('encrypts plaintext notes and is idempotent', function () {
    $user = User::factory()->create();
    DB::table('customer_profiles')->insert([
        'user_id' => $user->id,
        'notes' => 'plaintext leftover',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('medical:encrypt-customer-notes')->assertExitCode(0);

    $raw = DB::table('customer_profiles')->where('user_id', $user->id)->value('notes');
    expect($raw)->not->toBe('plaintext leftover');
    expect(Crypt::decryptString($raw))->toBe('plaintext leftover');

    $this->artisan('medical:encrypt-customer-notes')->assertExitCode(0);

    $rawAfter = DB::table('customer_profiles')->where('user_id', $user->id)->value('notes');
    expect(Crypt::decryptString($rawAfter))->toBe('plaintext leftover');
});
