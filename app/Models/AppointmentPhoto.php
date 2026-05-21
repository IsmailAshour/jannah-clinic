<?php

namespace App\Models;

use App\Enums\AppointmentPhotoKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['appointment_id', 'kind', 'file_path', 'mime_type', 'file_size', 'caption', 'uploaded_by'])]
class AppointmentPhoto extends Model
{
    protected $casts = [
        'kind' => AppointmentPhotoKind::class,
        'file_size' => 'integer',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
