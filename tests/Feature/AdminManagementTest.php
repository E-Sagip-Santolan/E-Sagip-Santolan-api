<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperadmin(bool $isActive = true): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPERADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => $isActive,
        ]);
    }

    public function test_superadmin_can_list_admins_only_with_pagination(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = User::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);
        $admin->adminPermissions()->create(['permission' => AdminPermission::VOLUNTEER_APPROVALS]);

        // Volunteers must not appear in /api/admins.
        User::factory()->create([
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        Sanctum::actingAs($superadmin);

        $this->getJson('/api/admins')
            ->assertStatus(200)
            ->assertJsonPath('message', 'OK')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_superadmin_can_create_admin_with_permissions_and_logs_activity(): void
    {
        $superadmin = $this->createSuperadmin();
        Sanctum::actingAs($superadmin);

        $response = $this->postJson('/api/admins', [
            'name' => 'Pedro Reyes',
            'email' => 'pedro@example.com',
            'password' => 'TempPassword123',
            'role' => 'admin',
            'permissions' => [
                AdminPermission::VOLUNTEER_APPROVALS,
                AdminPermission::OPERATION_MANAGEMENT,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Admin created.')
            ->assertJsonPath('data.name', 'Pedro Reyes')
            ->assertJsonPath('data.email', 'pedro@example.com')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.permissions', [
                AdminPermission::OPERATION_MANAGEMENT,
                AdminPermission::VOLUNTEER_APPROVALS,
            ]);

        $created = User::where('email', 'pedro@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('TempPassword123', $created->password));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superadmin->id,
            'action' => 'admin_created',
            'module' => 'admins',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_superadmin_can_update_admin_and_logs_activity(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = User::factory()->create([
            'name' => 'Pedro Reyes',
            'email' => 'pedro@example.com',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);
        $admin->adminPermissions()->create(['permission' => AdminPermission::VOLUNTEER_APPROVALS]);

        Sanctum::actingAs($superadmin);

        $this->putJson("/api/admins/{$admin->id}", [
            'name' => 'Pedro R. Reyes',
            'permissions' => [
                AdminPermission::ATTENDANCE_CONFIRMATION,
                AdminPermission::REPORTS_EXPORTS,
            ],
            'is_active' => false,
        ])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Admin updated.')
            ->assertJsonPath('data.name', 'Pedro R. Reyes')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.permissions', [
                AdminPermission::ATTENDANCE_CONFIRMATION,
                AdminPermission::REPORTS_EXPORTS,
            ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superadmin->id,
            'action' => 'admin_updated',
            'module' => 'admins',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_admins_endpoints_return_401_when_unauthenticated(): void
    {
        $this->getJson('/api/admins')->assertStatus(401);
        $this->postJson('/api/admins', [])->assertStatus(401);
        $this->putJson('/api/admins/1', [])->assertStatus(401);
    }

    public function test_admins_endpoints_return_403_for_non_superadmins_and_disabled_superadmin(): void
    {
        $volunteer = User::factory()->create([
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);
        Sanctum::actingAs($volunteer);
        $this->getJson('/api/admins')->assertStatus(403);
        $this->postJson('/api/admins', [])->assertStatus(403);

        // Even an admin with all 6 permissions cannot manage admin accounts.
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);
        $admin->adminPermissions()->createMany(
            array_map(fn (string $p) => ['permission' => $p], AdminPermission::ALL)
        );

        Sanctum::actingAs($admin);
        $this->getJson('/api/admins')->assertStatus(403);
        $this->postJson('/api/admins', [])->assertStatus(403);
        $this->putJson("/api/admins/{$admin->id}", ['is_active' => false])->assertStatus(403);

        $disabledSuper = $this->createSuperadmin(isActive: false);
        Sanctum::actingAs($disabledSuper);
        $this->getJson('/api/admins')->assertStatus(403);
    }

    public function test_update_admin_returns_404_for_missing_or_volunteer_user(): void
    {
        $superadmin = $this->createSuperadmin();
        $volunteer = User::factory()->create([
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        Sanctum::actingAs($superadmin);

        $this->putJson('/api/admins/99999', ['name' => 'Missing'])->assertStatus(404);
        $this->putJson("/api/admins/{$volunteer->id}", ['name' => 'Volunteer'])->assertStatus(404);
    }

    public function test_admins_endpoints_return_422_for_invalid_data(): void
    {
        $superadmin = $this->createSuperadmin();
        User::factory()->create(['email' => 'taken@example.com', 'role' => User::ROLE_ADMIN]);

        Sanctum::actingAs($superadmin);

        $this->postJson('/api/admins', [
            'name' => 'Bad Admin',
            'email' => 'taken@example.com',
            'password' => 'weak',
            'role' => 'volunteer',
            'permissions' => ['invalid_permission'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password', 'role', 'permissions.0']);

        $this->putJson("/api/admins/{$superadmin->id}", [
            'role' => 'volunteer',
            'is_active' => 'not-a-boolean',
            'permissions' => ['invalid_permission'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role', 'is_active', 'permissions.0']);
    }
}
