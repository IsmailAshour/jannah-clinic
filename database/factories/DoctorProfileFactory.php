<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create(['role' => UserRole::Doctor])->id,
            'specialty' => 'عام',
            'is_bookable' => true,
            'display_order' => 0,
        ];
    }
}
