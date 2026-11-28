<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VolunteerApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function createVolunteer(string $status = User::STATUS_PENDING): User
    {
        $volunteer = User::factory()->create([
            'role' => User::ROLE_VOLUNTEER,
            'status' => $status,
            'is_active' => true,
        ]);

        $volunteer->volunteerProfile()->create([
            'contact_number' => '09171234567',
            'address_area' => 'Zone 1, Santolan',
            'birthdate' => '2000-05-14',
            'is_resident' => true,
            'availability' => VolunteerProfile::AVAILABILITY_AVAILABLE,
        ]);

        return $volunteer;
    }

    private function createApproverAdmin(): User
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);
        $admin->adminPermissions()->create(['permission' => AdminPermission::VOLUNTEER_APPROVALS]);

        return $admin;
    }

    public function test_authorized_admin_can_approve_pending_volunteer_and_logs_activity(): void
    {
        $admin = $this->createApproverAdmin();
        $volunteer = $this->createVolunteer(User::STATUS_PENDING);

        Sanctum::actingAs($admin);

        $this->postJson("/api/volunteers/{$volunteer->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Volunteer approved.')
            ->assertJsonPath('data.id', $volunteer->id)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $admin->id);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'volunteer_approved',
            'module' => 'volunteers',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_authorized_admin_can_reject_pending_volunteer_with_and_without_reason(): void
    {
        $admin = $this->createApproverAdmin();
        $first = $this->createVolunteer(User::STATUS_PENDING);
        $second = $this->createVolunteer(User::STATUS_PENDING);

        Sanctum::actingAs($admin);

        $this->postJson("/api/volunteers/{$first->id}/reject", [
            'reason' => 'Address is outside Barangay Santolan.',
        ])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Volunteer rejected.')
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Address is outside Barangay Santolan.');

        // Reason is optional.
        $this->postJson("/api/volunteers/{$second->id}/reject")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', null);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'volunteer_rejected',
            'module' => 'volunteers',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_approve_and_reject_return_401_when_unauthenticated(): void
    {
        $volunteer = $this->createVolunteer();

        $this->postJson("/api/volunteers/{$volunteer->id}/approve")->assertStatus(401);
        $this->postJson("/api/volunteers/{$volunteer->id}/reject")->assertStatus(401);
    }

    public function test_approve_and_reject_return_403_for_unauthorized_users(): void
    {
        $target = $this->createVolunteer();

        $volunteer = $this->createVolunteer(User::STATUS_APPROVED);
        Sanctum::actingAs($volunteer);
        $this->postJson("/api/volunteers/{$target->id}/approve")->assertStatus(403);
        $this->postJson("/api/volunteers/{$target->id}/reject")->assertStatus(403);

        $otherAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);
        $otherAdmin->adminPermissions()->create(['permission' => AdminPermission::REPORTS_EXPORTS]);
        Sanctum::actingAs($otherAdmin);

        $this->postJson("/api/volunteers/{$target->id}/approve")->assertStatus(403);
        $this->postJson("/api/volunteers/{$target->id}/reject")->assertStatus(403);
    }

    public function test_approve_and_reject_return_404_for_missing_or_non_volunteer_user(): void
    {
        $admin = $this->createApproverAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/volunteers/99999/approve')->assertStatus(404);
        $this->postJson("/api/volunteers/{$admin->id}/reject")->assertStatus(404);
    }

    public function test_approve_and_reject_return_422_for_non_pending_or_invalid_reason(): void
    {
        $admin = $this->createApproverAdmin();
        $approved = $this->createVolunteer(User::STATUS_APPROVED);
        $pending = $this->createVolunteer(User::STATUS_PENDING);

        Sanctum::actingAs($admin);

        $this->postJson("/api/volunteers/{$approved->id}/approve")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->postJson("/api/volunteers/{$approved->id}/reject")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->postJson("/api/volunteers/{$pending->id}/reject", [
            'reason' => str_repeat('a', 256),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }
}
