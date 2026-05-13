<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumIdleOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactivity_timeout_is_skipped_until_profile_is_complete(): void
    {
        config(['sanctum.idle_timeout_minutes' => 5]);

        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 't']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'name' => 'Incomplete',
            'college_office' => null,
            'user_type' => null,
            'email' => 'idle-onboard@my.xu.edu.ph',
        ]);

        $newAccess = $user->createToken('auth');
        $plain = $newAccess->plainTextToken;
        $newAccess->accessToken->forceFill(['last_used_at' => now()->subMinutes(20)])->save();

        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/api/me')->assertOk();
    }

    public function test_inactivity_timeout_invalidates_token_after_profile_is_complete(): void
    {
        config(['sanctum.idle_timeout_minutes' => 5]);

        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 't']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'name' => 'Complete User',
            'college_office' => 'College of Computer Studies',
            'user_type' => User::USER_TYPE_STUDENT,
            'email' => 'idle-done@my.xu.edu.ph',
        ]);

        $newAccess = $user->createToken('auth');
        $plain = $newAccess->plainTextToken;
        $newAccess->accessToken->forceFill(['last_used_at' => now()->subMinutes(20)])->save();

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/me')
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Session expired due to inactivity. Please sign in again.',
            ]);
    }
}
