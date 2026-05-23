<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line item on an appointment — one row per service rendered during the
 * visit. Captures price + duration AT booking time (denormalized) so
 * later service edits don't retroactively rewrite an old appointment.
 *
 * @property int $id
 * @property int $appointment_id
 * @property int $service_id
 * @property string $price_at_booking
 * @property int $duration_minutes
 * @property int $sort_order
 */
#[Fillable(['appointment_id', 'service_id', 'price_at_booking', 'duration_minutes', 'sort_order'])]
class AppointmentService extends Model
{
    protected $casts = [
        'price_at_booking' => 'decimal:2',
        'duration_minutes' => 'integer',
        'sort_order' => 'integer',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
