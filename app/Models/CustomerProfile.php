<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    protected $fillable = ['user_id', 'date_of_birth', 'gender', 'notes', 'avatar_path', 'profile_completed_at'];

    protected $casts = ['date_of_birth' => 'date', 'profile_completed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
