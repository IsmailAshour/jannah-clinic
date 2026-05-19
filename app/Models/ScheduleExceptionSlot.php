<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['schedule_exception_id', 'slot_start'])]
class ScheduleExceptionSlot extends Model
{
    public function exception(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'schedule_exception_id');
    }
}
