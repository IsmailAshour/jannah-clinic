<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['appointment_id', 'coverage_area_id', 'address_text', 'location_note', 'lat', 'lng'])]
class ServiceAddress extends Model
{
    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function coverageArea(): BelongsTo
    {
        return $this->belongsTo(HomeServiceCoverageArea::class, 'coverage_area_id');
    }
}
