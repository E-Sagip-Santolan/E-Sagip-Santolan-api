<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSuperadminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_superadmin_with_valid_input(): void
    {
        $this->artisan('esagip:create-superadmin', [
            '--name' => 'Barangay Captain',
            '--email' => 'captain@example.com',
            '--password' => 'Secret1234',
        ])->assertExitCode(0);

        $user = User::where('email', 'captain@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Barangay Captain', $user->name);
        $this->assertSame(User::ROLE_SUPERADMIN, $user->role);
        $this->assertSame(User::STATUS_APPROVED, $user->status);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Secret1234', $user->password));
    }

    public function test_fails_when_email_is_already_taken(): void
    {
        User::factory()->create([
            'email' => 'captain@example.com',
        ]);

        $this->artisan('esagip:create-superadmin', [
            '--name' => 'Another Admin',
            '--email' => 'captain@example.com',
            '--password' => 'Secret1234',
        ])->assertExitCode(1);

        $this->assertSame(1, User::where('email', 'captain@example.com')->count());
    }

    public function test_fails_when_password_does_not_meet_rules(): void
    {
        $this->artisan('esagip:create-superadmin', [
            '--name' => 'Barangay Captain',
            '--email' => 'captain@example.com',
            '--password' => 'weak',
        ])->assertExitCode(1);

        $this->assertNull(User::where('email', 'captain@example.com')->first());
    }
}
