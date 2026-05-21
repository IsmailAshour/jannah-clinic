<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    // Registration creates a Customer and redirects to public.home (the redesigned /).
    // Email is optional (phone-only registration is supported), but at least
    // one of email/phone must be present. Password confirmation is required.
    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('public.home'));
    }

    public function test_registration_rejects_mismatched_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test',
            'email' => 'mismatch@example.com',
            'phone' => null,
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-2',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }
}
