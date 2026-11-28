<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_volunteer_can_log_in_and_activity_is_logged(): void
    {
        $volunteer = User::factory()->create([
            'email' => 'juan@example.com',
            'password' => 'Password123',
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'juan@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'role', 'status', 'is_active', 'rejection_reason', 'permissions'],
                ],
                'message',
            ])
            ->assertJsonPath('data.user.id', $volunteer->id)
            ->assertJsonPath('data.user.role', 'volunteer')
            ->assertJsonPath('data.user.status', 'approved')
            ->assertJsonPath('message', 'Logged in.');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $volunteer->id,
            'action' => 'login',
            'module' => 'auth',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_pending_and_rejected_volunteers_can_log_in(): void
    {
        User::factory()->create([
            'email' => 'pending@example.com',
            'password' => 'Password123',
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_PENDING,
            'is_active' => true,
        ]);

        User::factory()->create([
            'email' => 'rejected@example.com',
            'password' => 'Password123',
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_REJECTED,
            'rejection_reason' => 'Outside Barangay Santolan.',
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'pending@example.com',
            'password' => 'Password123',
        ])->assertStatus(200)->assertJsonPath('data.user.status', 'pending');

        $this->postJson('/api/login', [
            'email' => 'rejected@example.com',
            'password' => 'Password123',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.user.status', 'rejected')
            ->assertJsonPath('data.user.rejection_reason', 'Outside Barangay Santolan.');
    }

    public function test_login_returns_401_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'juan@example.com',
            'password' => 'Password123',
            'role' => User::ROLE_VOLUNTEER,
        ]);

        $this->postJson('/api/login', [
            'email' => 'juan@example.com',
            'password' => 'WrongPassword1',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_login_returns_403_for_admin_or_superadmin(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'Password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'Password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Please use the admin portal to log in.');
    }

    public function test_login_returns_403_for_disabled_volunteer(): void
    {
        User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => 'Password123',
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_APPROVED,
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => 'disabled@example.com',
            'password' => 'Password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This account is disabled.');
    }

    public function test_login_returns_422_for_missing_fields(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'rate@example.com',
                'password' => 'WrongPass1',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email' => 'rate@example.com',
            'password' => 'WrongPass1',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many attempts. Try again later.');
    }
}
