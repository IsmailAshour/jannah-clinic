<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $appointment_id
 * @property int $author_id
 * @property string $visible_summary
 * @property string|null $staff_notes
 * @property Appointment $appointment
 * @property User $author
 * @property Collection<int, Prescription> $prescriptions
 */
#[Fillable(['appointment_id', 'author_id', 'visible_summary', 'staff_notes'])]
class MedicalEntry extends Model
{
    use HasFactory;

    protected $casts = [
        'visible_summary' => 'encrypted',
        'staff_notes' => 'encrypted',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class)->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MedicalAttachment::class)->orderByDesc('created_at');
    }
}
