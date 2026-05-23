<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $appointment_id
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $file_size
 * @property string|null $title
 * @property int $uploaded_by
 */
#[Fillable(['appointment_id', 'file_path', 'original_filename', 'mime_type', 'file_size', 'title', 'uploaded_by'])]
class MedicalAttachment extends Model
{
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
