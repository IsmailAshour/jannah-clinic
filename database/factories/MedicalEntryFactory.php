<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalEntry;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalEntry>
 */
class MedicalEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => fn () => $this->buildAppointment()->id,
            'author_id' => fn () => User::factory()->create(['role' => UserRole::Doctor])->id,
            'visible_summary' => 'sample diagnosis',
            'staff_notes' => null,
        ];
    }

    private function buildAppointment(): Appointment
    {
        $cat = ServiceCategory::create([
            'name' => 'cat-'.uniqid(),
            'slug' => 'cat-'.uniqid(),
            'color_variant' => 'brand',
        ]);
        $svc = Service::create([
            'category_id' => $cat->id,
            'name' => 'svc',
            'base_price' => '100.00',
            'duration_minutes' => 30,
            'home_service_enabled' => false,
        ]);
        $doc = DoctorProfile::factory()->create();
        $doc->services()->attach($svc->id);
        $cust = User::factory()->create(['role' => UserRole::Customer]);

        return Appointment::create([
            'customer_id' => $cust->id,
            'doctor_profile_id' => $doc->id,
            'service_id' => $svc->id,
            'start_at' => now()->subDay(),
            'end_at' => now()->subDay()->addMinutes(30),
            'status' => AppointmentStatus::Completed,
            'price_at_booking' => '100.00',
            'delivery_mode' => DeliveryMode::Center,
            'home_surcharge_amount' => '0.00',
            'created_by_role' => UserRole::Customer,
        ]);
    }
}
