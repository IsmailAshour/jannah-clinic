<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * NOTE (P0/Task8 hazard): User implements MustVerifyEmail, but customers may
 * register with phone only (no email) — they will always fail hasVerifiedEmail().
 * Customer portal routes MUST NOT use the `verified` middleware alias, or
 * phone-only customers get trapped on /email/verify. See ADR-002 and the P0 plan
 * Task 8. Introduce a phone-aware verification guard before requiring verification.
 *
 * @property UserRole $role
 */
#[Fillable(['name', 'email', 'password', 'phone', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function isStaff(): bool
    {
        return $this->role instanceof UserRole && $this->role->isStaff();
    }
}
