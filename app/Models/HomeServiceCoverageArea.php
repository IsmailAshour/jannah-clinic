<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'is_active', 'display_order'])]
class HomeServiceCoverageArea extends Model
{
    protected $casts = ['is_active' => 'boolean', 'display_order' => 'integer'];
}
