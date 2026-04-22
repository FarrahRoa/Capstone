<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SessionLogoutAndExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_like_me_request_does_not_log_out_valid_token(): void
    {
        $this->seed(RoleSeeder::class);
        $student = Role::where('slug', 'student')->firstOrFail();

        $user = User::create([
            'name' => 'Student',
            'email' => 'refresh@my.xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'refresh@my.xu.edu.ph');
    }

    public function test_logout_revokes_current_token_for_normal_user(): void
    {
        $this->seed(RoleSeeder::class);
        $student = Role::where('slug', 'student')->firstOrFail();

        $user = User::create([
            'name' => 'Student',
            'email' => 'logout@my.xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_logout_revokes_current_token_for_admin_portal_user(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $user = User::create([
            'name' => 'Admin',
            'email' => 'adminlogout@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_inactivity_timeout_expires_normal_user_token(): void
    {
        Config::set('sanctum.idle_timeout_minutes', 5);
        Config::set('sanctum.idle_timeout_admin_minutes', 5);

        $this->seed(RoleSeeder::class);
        $student = Role::where('slug', 'student')->firstOrFail();

        $user = User::create([
            'name' => 'Student',
            'email' => 'idle@my.xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        $token = $user->createToken('auth')->plainTextToken;
        $patId = (int) explode('|', $token, 2)[0];

        /** @var PersonalAccessToken $pat */
        $pat = PersonalAccessToken::find($patId);
        $this->assertNotNull($pat);
        $pat->forceFill(['last_used_at' => now()->subMinutes(10)])->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertStatus(401)
            ->assertJsonFragment(['message' => 'Session expired due to inactivity. Please sign in again.']);
    }

    public function test_inactivity_timeout_is_stricter_for_admin_portal_roles(): void
    {
        Config::set('sanctum.idle_timeout_minutes', 60);
        Config::set('sanctum.idle_timeout_admin_minutes', 5);

        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $user = User::create([
            'name' => 'Admin',
            'email' => 'idleadmin@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        $token = $user->createToken('auth')->plainTextToken;
        $patId = (int) explode('|', $token, 2)[0];

        /** @var PersonalAccessToken $pat */
        $pat = PersonalAccessToken::find($patId);
        $this->assertNotNull($pat);
        $pat->forceFill(['last_used_at' => now()->subMinutes(10)])->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}

