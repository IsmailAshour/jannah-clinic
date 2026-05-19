<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed local dev accounts. Idempotent — safe to re-run.
     * Dev credentials only; never seeded in production (ADR-002).
     */
    public function run(): void
    {
        // Admin / manager — full dashboard access (admin.* routes).
        User::updateOrCreate(
            ['email' => 'manager@jannahclinic.test'],
            [
                'name' => 'مدير العيادة',
                'phone' => '0590000001',
                'password' => Hash::make('password'),
                'role' => UserRole::Manager,
                'email_verified_at' => now(),
            ],
        );

        // Customer — client portal (portal.* routes).
        $customer = User::updateOrCreate(
            ['email' => 'customer@jannahclinic.test'],
            [
                'name' => 'عميل تجريبي',
                'phone' => '0590000002',
                'password' => Hash::make('password'),
                'role' => UserRole::Customer,
                'email_verified_at' => now(),
            ],
        );
        CustomerProfile::firstOrCreate(['user_id' => $customer->id]);
    }
}
