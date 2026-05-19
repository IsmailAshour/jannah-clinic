<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['doctor_profile_id', 'weekday', 'morning_enabled', 'morning_start', 'morning_end', 'evening_enabled', 'evening_start', 'evening_end', 'slot_interval_minutes'])]
class DoctorSchedule extends Model
{
    protected $casts = [
        'weekday' => 'integer',
        'morning_enabled' => 'boolean',
        'evening_enabled' => 'boolean',
        'slot_interval_minutes' => 'integer',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
    }
}
