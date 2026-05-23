<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'name', 'description', 'content', 'image_path', 'base_price', 'duration_minutes', 'home_service_enabled', 'online_service_enabled', 'is_featured', 'is_active', 'display_order', 'loyalty_enabled', 'loyalty_redemption_points'])]
class Service extends Model
{
    protected $casts = [
        'base_price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'home_service_enabled' => 'boolean',
        'online_service_enabled' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'loyalty_enabled' => 'boolean',
        'loyalty_redemption_points' => 'integer',
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

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function slotCount(): int
    {
        return intdiv((int) $this->duration_minutes, (int) config('clinic.slot_minutes', 30));
    }
}
