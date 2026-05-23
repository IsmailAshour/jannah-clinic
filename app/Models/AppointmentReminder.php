<?php

namespace App\Models;

use App\Enums\ReminderKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['appointment_id', 'kind', 'sent_at', 'recipient_email'])]
class AppointmentReminder extends Model
{
    protected $casts = [
        'kind' => ReminderKind::class,
        'sent_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
