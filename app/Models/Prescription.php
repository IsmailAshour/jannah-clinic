<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['medical_entry_id', 'medication_name', 'dosage', 'frequency', 'duration', 'notes'])]
class Prescription extends Model
{
    protected $casts = [
        'medication_name' => 'encrypted',
        'dosage' => 'encrypted',
        'frequency' => 'encrypted',
        'duration' => 'encrypted',
        'notes' => 'encrypted',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(MedicalEntry::class, 'medical_entry_id');
    }
}
