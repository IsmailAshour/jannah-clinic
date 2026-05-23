<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    // Login now accepts 'identifier' (email OR phone) instead of 'email'.
    // After login, users are redirected based on role:
    //   staff  → admin.dashboard
    //   customer → public.home (the redesigned /, customer-personalized).
    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('public.home'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'identifier' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_doctor_login_redirects_to_their_day_view(): void
    {
        $user = User::factory()->create(['role' => UserRole::Doctor]);
        $doctor = DoctorProfile::factory()->create(['user_id' => $user->id]);

        $response = $this->post('/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.doctors.day', ['doctor' => $doctor->id]));
    }

    public function test_doctor_without_profile_falls_back_to_admin_dashboard(): void
    {
        // Edge case: a User row with role=Doctor but no DoctorProfile linked
        // yet — happens during onboarding. Don't 500; land on the dashboard
        // so they can still navigate.
        $user = User::factory()->create(['role' => UserRole::Doctor]);

        $response = $this->post('/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_manager_login_still_lands_on_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->post('/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
