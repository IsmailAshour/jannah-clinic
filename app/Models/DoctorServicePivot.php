<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string|null $price_override decimal:2 cast — numeric string or null
 */
class DoctorServicePivot extends Pivot
{
    protected $casts = [
        'price_override' => 'decimal:2',
    ];
}
