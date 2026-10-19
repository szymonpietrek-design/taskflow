<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_screen_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_guest_cannot_create_an_account(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'guest@example.com',
            'password' => 'some-secret-password',
            'password_confirmation' => 'some-secret-password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'guest@example.com']);
    }
}
