<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Skill;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VolunteerListTest extends TestCase
{
    use RefreshDatabase;

    private function createVolunteer(array $userOverrides = [], array $profileOverrides = [], array $skillIds = []): User
    {
        $volunteer = User::factory()->create(array_merge([
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_PENDING,
            'is_active' => true,
        ], $userOverrides));

        $volunteer->volunteerProfile()->create(array_merge([
            'contact_number' => '09171234567',
            'address_area' => 'Zone 1, Santolan',
            'birthdate' => '2000-05-14',
            'is_resident' => true,
            'availability' => VolunteerProfile::AVAILABILITY_AVAILABLE,
            'training' => 'Basic First Aid',
        ], $profileOverrides));

        if ($skillIds !== []) {
            $volunteer->skills()->sync($skillIds);
        }

        return $volunteer;
    }

    public function test_authorized_admin_and_superadmin_can_list_volunteers_with_contact_details(): void
    {
        $skill = Skill::create(['name' => 'First Aid', 'category' => 'Medical']);
        $volunteer = $this->createVolunteer(
            ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com'],
            ['contact_number' => '09171234567', 'address_area' => 'Zone 1, Santolan'],
            [$skill->id],
        );

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);
        $admin->adminPermissions()->create(['permission' => AdminPermission::VOLUNTEER_APPROVALS]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/volunteers')
            ->assertStatus(200)
            ->assertJsonPath('message', 'OK')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $volunteer->id)
            ->assertJsonPath('data.0.email', 'juan@example.com')
            ->assertJsonPath('data.0.contact_number', '09171234567')
            ->assertJsonPath('data.0.address_area', 'Zone 1, Santolan')
            ->assertJsonPath('data.0.skills.0.id', $skill->id)
            ->assertJsonPath('meta', [
                'page' => 1,
                'per_page' => 15,
                'total' => 1,
                'last_page' => 1,
            ]);

        $superadmin = User::factory()->create([
            'role' => User::ROLE_SUPERADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        Sanctum::actingAs($superadmin);

        $this->getJson('/api/volunteers')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_volunteers_list_supports_filters_and_pagination(): void
    {
        $firstAid = Skill::create(['name' => 'First Aid', 'category' => 'Medical']);
        $cooking = Skill::create(['name' => 'Cooking', 'category' => 'Relief']);

        $match = $this->createVolunteer(
            ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'status' => User::STATUS_APPROVED],
            ['contact_number' => '09171112222', 'address_area' => 'Zone 1, Sgt. de Leon', 'availability' => VolunteerProfile::AVAILABILITY_AVAILABLE],
            [$firstAid->id],
        );

        $this->createVolunteer(
            ['name' => 'Maria Clara', 'email' => 'maria@example.com', 'status' => User::STATUS_PENDING],
            ['contact_number' => '09189998888', 'address_area' => 'Zone 3, Tawiran', 'availability' => VolunteerProfile::AVAILABILITY_UNAVAILABLE],
            [$cooking->id],
        );

        $superadmin = User::factory()->create([
            'role' => User::ROLE_SUPERADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        Sanctum::actingAs($superadmin);

        $this->getJson("/api/volunteers?status=approved&skill={$firstAid->id}&availability=available&address_area=zone%201&search=0917111")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);

        $this->getJson('/api/volunteers?per_page=1&page=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_authorized_admin_can_get_volunteer_summary(): void
    {
        $this->createVolunteer(['status' => User::STATUS_PENDING]);
        $this->createVolunteer(['status' => User::STATUS_PENDING]);
        $this->createVolunteer(['status' => User::STATUS_APPROVED]);
        $this->createVolunteer(['status' => User::STATUS_REJECTED]);

        $superadmin = User::factory()->create([
            'role' => User::ROLE_SUPERADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        Sanctum::actingAs($superadmin);

        $this->getJson('/api/volunteers/summary')
            ->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    'total' => 4,
                    'pending' => 2,
                    'approved' => 1,
                    'rejected' => 1,
                ],
                'message' => 'OK',
            ]);
    }

    public function test_volunteers_endpoints_return_401_when_unauthenticated(): void
    {
        $this->getJson('/api/volunteers')->assertStatus(401);
        $this->getJson('/api/volunteers/summary')->assertStatus(401);
    }

    public function test_volunteers_endpoints_return_403_for_unauthorized_roles(): void
    {
        $volunteer = $this->createVolunteer(['status' => User::STATUS_APPROVED]);
        Sanctum::actingAs($volunteer);

        $this->getJson('/api/volunteers')
            ->assertStatus(403)
            ->assertJsonPath('message', 'You do not have access to this action.');
        $this->getJson('/api/volunteers/summary')->assertStatus(403);

        // Admin without volunteer_approvals permission
        $contentAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);
        $contentAdmin->adminPermissions()->create(['permission' => AdminPermission::COMMUNITY_PUBLISHING]);

        Sanctum::actingAs($contentAdmin);
        $this->getJson('/api/volunteers')->assertStatus(403);
        $this->getJson('/api/volunteers/summary')->assertStatus(403);

        // Disabled admin with volunteer_approvals permission
        $disabledAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => false,
        ]);
        $disabledAdmin->adminPermissions()->create(['permission' => AdminPermission::VOLUNTEER_APPROVALS]);

        Sanctum::actingAs($disabledAdmin);
        $this->getJson('/api/volunteers')->assertStatus(403);
    }

    public function test_volunteers_list_returns_422_for_invalid_filters(): void
    {
        $superadmin = User::factory()->create([
            'role' => User::ROLE_SUPERADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        Sanctum::actingAs($superadmin);

        $this->getJson('/api/volunteers?status=unknown&skill=9999&availability=always&per_page=100')
            ->assertStatus(422)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors(['status', 'skill', 'availability', 'per_page']);
    }
}
