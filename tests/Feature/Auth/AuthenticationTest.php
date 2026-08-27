<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSee('Username')
            ->assertDontSee('Forgot your password?');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => '321',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $this->post('/login', [
            'username' => 'admin',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_password_reset_routes_are_unavailable(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/reset-password/example-token')->assertNotFound();
    }
}
