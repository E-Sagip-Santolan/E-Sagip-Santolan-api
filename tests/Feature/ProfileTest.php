<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Skill;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_volunteer_can_view_own_profile(): void
    {
        $skill = Skill::create(['name' => 'First Aid', 'category' => 'Medical']);
        $volunteer = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $volunteer->volunteerProfile()->create([
            'contact_number' => '09171234567',
            'address_area' => 'Zone 1, Santolan',
            'birthdate' => '2000-05-14',
            'is_resident' => true,
            'availability' => VolunteerProfile::AVAILABILITY_AVAILABLE,
            'training' => 'Basic First Aid',
        ]);
        $volunteer->skills()->sync([$skill->id]);

        Sanctum::actingAs($volunteer);

        $this->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('message', 'OK')
            ->assertJsonPath('data.id', $volunteer->id)
            ->assertJsonPath('data.role', 'volunteer')
            ->assertJsonPath('data.profile.contact_number', '09171234567')
            ->assertJsonPath('data.profile.birthdate', '2000-05-14')
            ->assertJsonPath('data.profile.skills.0.id', $skill->id);
    }

    public function test_admin_receives_null_profile_and_permissions_on_me(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);
        $admin->adminPermissions()->create(['permission' => AdminPermission::VOLUNTEER_APPROVALS]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.profile', null)
            ->assertJsonPath('data.permissions', [AdminPermission::VOLUNTEER_APPROVALS]);
    }

    public function test_volunteer_can_partially_update_own_profile_and_cannot_change_protected_fields(): void
    {
        $skillOne = Skill::create(['name' => 'First Aid', 'category' => 'Medical']);
        $skillTwo = Skill::create(['name' => 'Cooking', 'category' => 'Relief']);

        $volunteer = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_PENDING,
            'is_active' => true,
        ]);

        $volunteer->volunteerProfile()->create([
            'contact_number' => '09171234567',
            'address_area' => 'Zone 1, Santolan',
            'birthdate' => '2000-05-14',
            'is_resident' => true,
            'availability' => VolunteerProfile::AVAILABILITY_AVAILABLE,
        ]);
        $volunteer->skills()->sync([$skillOne->id]);

        Sanctum::actingAs($volunteer);

        $this->putJson('/api/me', [
            'name' => 'Juan D. Cruz',
            'contact_number' => '09179876543',
            'availability' => VolunteerProfile::AVAILABILITY_UNAVAILABLE,
            'skills' => [$skillOne->id, $skillTwo->id],
            // Protected fields must be ignored.
            'email' => 'hacker@example.com',
            'role' => 'superadmin',
            'status' => 'approved',
            'is_active' => false,
        ])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('data.name', 'Juan D. Cruz')
            ->assertJsonPath('data.email', 'juan@example.com')
            ->assertJsonPath('data.role', 'volunteer')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.profile.contact_number', '09179876543')
            ->assertJsonPath('data.profile.availability', 'unavailable')
            ->assertJsonCount(2, 'data.profile.skills');
    }

    public function test_me_endpoints_return_401_when_unauthenticated(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->putJson('/api/me', ['name' => 'Nobody'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_update_profile_returns_422_for_invalid_data(): void
    {
        $volunteer = User::factory()->create([
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        Sanctum::actingAs($volunteer);

        $this->putJson('/api/me', [
            'availability' => 'always',
            'skills' => [9999],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors(['availability', 'skills.0']);
    }
}
