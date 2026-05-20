<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $appointment_id
 * @property string $amount
 * @property PaymentStatus $status
 * @property \Carbon\CarbonImmutable|null $verified_at
 * @property int|null $verified_by
 * @property \Carbon\CarbonImmutable|null $refunded_at
 * @property int|null $refunded_by
 * @property string|null $refund_reference
 * @property string|null $rejection_reason
 * @property string|null $notes
 * @property Appointment $appointment
 */
#[Fillable([
    'appointment_id', 'amount', 'status',
    'verified_at', 'verified_by',
    'refunded_at', 'refunded_by', 'refund_reference',
    'rejection_reason', 'notes',
])]
class Payment extends Model
{
    protected $casts = [
        'amount' => 'decimal:2',
        'status' => PaymentStatus::class,
        'verified_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class)->orderByDesc('id');
    }
}
