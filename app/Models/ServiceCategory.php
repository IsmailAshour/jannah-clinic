<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'color_variant', 'display_order', 'is_active'])]
class ServiceCategory extends Model
{
    protected $casts = ['is_active' => 'boolean', 'display_order' => 'integer'];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
