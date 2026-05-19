<?php

namespace App\Domain\Auth\Services;

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function resolveByIdentifier(string $identifier): ?User
    {
        return User::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();
    }

    public function registerCustomer(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => UserRole::Customer,
            ]);
            CustomerProfile::create(['user_id' => $user->id]);

            return $user->fresh('customerProfile');
        });
    }

    public function createStaff(array $data, UserRole $role): User
    {
        return DB::transaction(function () use ($data, $role) {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $role,
            ]);
        });
    }
}
