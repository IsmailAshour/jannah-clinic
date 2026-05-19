<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DoctorServicePivot extends Pivot
{
    protected $casts = [
        'price_override' => 'decimal:2',
    ];
}
