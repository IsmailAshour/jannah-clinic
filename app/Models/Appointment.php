<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $doctor_profile_id
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 * @property AppointmentStatus $status
 * @property PaymentMethod $payment_method
 * @property int|null $loyalty_points_spent
 * @property User $customer
 * @property DoctorProfile $doctor
 * @property Service $service
 * @property Payment|null $payment
 * @property MedicalEntry|null $medicalEntry
 */
#[Fillable(['customer_id', 'doctor_profile_id', 'service_id', 'start_at', 'end_at', 'status', 'price_at_booking', 'delivery_mode', 'whatsapp_phone', 'home_surcharge_amount', 'created_by_role', 'cancellation_reason', 'rescheduled_from_id', 'payment_method', 'loyalty_points_spent'])]
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
        'payment_method' => PaymentMethod::class,
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

    public function medicalEntry(): HasOne
    {
        return $this->hasOne(MedicalEntry::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AppointmentPhoto::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }

    public function medicalAttachments(): HasMany
    {
        return $this->hasMany(MedicalAttachment::class)->orderByDesc('created_at');
    }
}
