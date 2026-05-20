<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['customer_id', 'doctor_profile_id', 'service_id', 'start_at', 'end_at', 'status', 'price_at_booking', 'delivery_mode', 'home_surcharge_amount', 'created_by_role', 'cancellation_reason', 'rescheduled_from_id'])]
class Appointment extends Model
{
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'status' => AppointmentStatus::class,
        'delivery_mode' => DeliveryMode::class,
        'created_by_role' => UserRole::class,
        'price_at_booking' => 'decimal:2',
        'home_surcharge_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceAddress(): HasOne
    {
        return $this->hasOne(ServiceAddress::class);
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
