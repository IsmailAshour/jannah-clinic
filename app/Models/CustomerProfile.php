<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable|null $date_of_birth
 * @property string|null $gender
 * @property string|null $notes
 * @property string|null $chronic_conditions
 * @property string|null $allergies
 * @property User $user
 */
#[Fillable(['user_id', 'date_of_birth', 'gender', 'notes', 'avatar_path', 'profile_completed_at', 'chronic_conditions', 'allergies'])]
class CustomerProfile extends Model
{
    protected $casts = [
        'date_of_birth' => 'date',
        'profile_completed_at' => 'datetime',
        'notes' => 'encrypted',
        'chronic_conditions' => 'encrypted',
        'allergies' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
