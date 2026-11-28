<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'contact_number' => '09171234567',
            'address_area' => 'Zone 1, Sgt. de Leon St., Santolan',
            'birthdate' => '2000-05-14',
            'is_resident' => true,
            'availability' => 'available',
            'training' => 'Red Cross Basic First Aid (2025)',
            'skills' => [],
            'consent' => true,
            'policy_version' => '1.0',
        ], $overrides);
    }

    public function test_volunteer_can_register_with_valid_data(): void
    {
        $skill = Skill::create([
            'name' => 'First Aid',
            'category' => 'Medical',
        ]);

        $response = $this->postJson('/api/register', $this->validPayload([
            'skills' => [$skill->id],
        ]));

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'role', 'status', 'is_active', 'rejection_reason', 'permissions'],
                ],
                'message',
            ])
            ->assertJsonPath('data.user.role', 'volunteer')
            ->assertJsonPath('data.user.status', 'pending')
            ->assertJsonPath('data.user.is_active', true)
            ->assertJsonPath('data.user.permissions', [])
            ->assertJsonPath('message', 'Registration received. Waiting for admin approval.');

        $user = User::where('email', 'juan@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('Password123', $user->password));

        $this->assertDatabaseHas('volunteer_profiles', [
            'user_id' => $user->id,
            'contact_number' => '09171234567',
            'is_resident' => true,
            'availability' => 'available',
        ]);

        $this->assertDatabaseHas('volunteer_skills', [
            'user_id' => $user->id,
            'skill_id' => $skill->id,
        ]);

        $this->assertDatabaseHas('consent_records', [
            'user_id' => $user->id,
            'policy_version' => '1.0',
        ]);
    }

    public function test_register_returns_422_for_invalid_data(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/register', $this->validPayload([
            'email' => 'taken@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'birthdate' => now()->subYears(17)->toDateString(),
            'is_resident' => false,
            'consent' => false,
            'skills' => [9999],
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors([
                'email',
                'password',
                'birthdate',
                'is_resident',
                'consent',
                'skills.0',
            ]);
    }

    public function test_register_is_rate_limited_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', [])->assertStatus(422);
        }

        $this->postJson('/api/register', [])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many attempts. Try again later.');
    }
}
