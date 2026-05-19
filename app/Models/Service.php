<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['category_id', 'name', 'description', 'base_price', 'duration_minutes', 'home_service_enabled', 'icon_key', 'is_active', 'display_order'])]
class Service extends Model
{
    protected $casts = [
        'base_price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'home_service_enabled' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(DoctorProfile::class, 'doctor_service')
            ->using(DoctorServicePivot::class)
            ->withPivot('price_override')->withTimestamps();
    }
}
