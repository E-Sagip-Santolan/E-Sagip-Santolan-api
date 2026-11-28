<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_log_out_and_only_current_token_is_revoked(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VOLUNTEER,
            'status' => User::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $tokenOne = $user->createToken('mobile');
        $tokenTwo = $user->createToken('other-device');

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenOne->plainTextToken)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertExactJson([
                'data' => null,
                'message' => 'Logged out.',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenOne->accessToken->id,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenTwo->accessToken->id,
        ]);
    }

    public function test_logout_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/logout')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson('/api/logout')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
