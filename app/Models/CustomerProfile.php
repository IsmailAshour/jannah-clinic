<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'date_of_birth', 'gender', 'notes', 'avatar_path', 'profile_completed_at'])]
class CustomerProfile extends Model
{
    protected $casts = ['date_of_birth' => 'date', 'profile_completed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
