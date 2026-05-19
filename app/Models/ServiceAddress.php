<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['appointment_id', 'coverage_area_id', 'address_text', 'location_note'])]
class ServiceAddress extends Model
{
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function coverageArea(): BelongsTo
    {
        return $this->belongsTo(HomeServiceCoverageArea::class, 'coverage_area_id');
    }
}
