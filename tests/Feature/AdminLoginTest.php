<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_log_in_with_permissions_and_activity_is_logged(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'Password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $admin->adminPermissions()->createMany([
            ['permission' => AdminPermission::VOLUNTEER_APPROVALS],
            ['permission' => AdminPermission::OPERATION_MANAGEMENT],
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
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
            ->assertJsonPath('data.user.id', $admin->id)
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.user.permissions', [
                AdminPermission::OPERATION_MANAGEMENT,
                AdminPermission::VOLUNTEER_APPROVALS,
            ])
            ->assertJsonPath('message', 'Logged in.');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'admin_login',
            'module' => 'auth',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_superadmin_can_log_in_and_receives_all_permissions(): void
    {
        $superadmin = User::factory()->create([
            'email' => 'super@example.com',
            'password' => 'Password123',
            'role' => User::ROLE_SUPERADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'super@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.role', 'superadmin')
            ->assertJsonPath('data.user.permissions', AdminPermission::ALL);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superadmin->id,
            'action' => 'admin_login',
            'module' => 'auth',
        ]);
    }

    public function test_admin_login_returns_401_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'Password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'WrongPassword1',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_admin_login_returns_403_for_volunteer(): void
    {
        User::factory()->create([
            'email' => 'juan@example.com',
            'password' => 'Password123',
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'juan@example.com',
            'password' => 'Password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This account has no access to the admin portal.');
    }

    public function test_admin_login_returns_403_for_disabled_admin(): void
    {
        User::factory()->create([
            'email' => 'disabled-admin@example.com',
            'password' => 'Password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => false,
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'disabled-admin@example.com',
            'password' => 'Password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This account has no access to the admin portal.');
    }

    public function test_admin_login_returns_422_for_missing_fields(): void
    {
        $this->postJson('/api/admin/login', [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_admin_login_is_rate_limited_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/admin/login', [
                'email' => 'rate-admin@example.com',
                'password' => 'WrongPass1',
            ])->assertStatus(401);
        }

        $this->postJson('/api/admin/login', [
            'email' => 'rate-admin@example.com',
            'password' => 'WrongPass1',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many attempts. Try again later.');
    }
}
