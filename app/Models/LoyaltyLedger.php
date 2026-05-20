<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $points_delta
 * @property int $balance_after
 * @property string $reason
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $notes
 * @property int|null $actor_id
 * @property CarbonImmutable $created_at
 * @property User $customer
 * @property User|null $actor
 */
#[Fillable(['customer_id', 'points_delta', 'balance_after', 'reason', 'reference_type', 'reference_id', 'notes', 'actor_id'])]
class LoyaltyLedger extends Model
{
    protected $table = 'loyalty_ledger';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'immutable_datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('LoyaltyLedger is append-only — entries cannot be updated.');
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw new \LogicException('LoyaltyLedger is append-only — entries cannot be deleted.');
    }
}
