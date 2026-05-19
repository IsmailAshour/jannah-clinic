<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['doctor_profile_id', 'date', 'type', 'custom_start', 'custom_end', 'note'])]
class ScheduleException extends Model
{
    protected $casts = [
        'date' => 'date',
        'custom_start' => 'datetime:H:i',
        'custom_end' => 'datetime:H:i',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ScheduleExceptionSlot::class, 'schedule_exception_id');
    }
}
