<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\RegistrationDisplayName;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoogleProfileEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_includes_needs_profile_enrichment_when_name_is_fallback(): void
    {
        $this->seed(RoleSeeder::class);
        $student = Role::where('slug', 'student')->first();
        $user = User::create([
            'name' => RegistrationDisplayName::FALLBACK,
            'email' => '20220024802@my.xu.edu.ph',
            'password' => Hash::make('secret'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');
        $response->assertOk();
        $response->assertJsonPath('needs_profile_enrichment', true);
    }

    public function test_me_sets_needs_profile_enrichment_false_when_name_is_not_fallback(): void
    {
        $this->seed(RoleSeeder::class);
        $student = Role::where('slug', 'student')->first();
        $user = User::create([
            'name' => 'Juan Cruz',
            'email' => 'juan@my.xu.edu.ph',
            'password' => Hash::make('secret'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');
        $response->assertOk();
        $response->assertJsonPath('needs_profile_enrichment', false);
    }

    public function test_google_profile_updates_name_when_email_matches(): void
    {
        $this->seed(RoleSeeder::class);
        $student = Role::where('slug', 'student')->first();
        $user = User::create([
            'name' => RegistrationDisplayName::FALLBACK,
            'email' => 'student@my.xu.edu.ph',
            'password' => Hash::make('secret'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        $this->mock(\App\Contracts\GoogleCredentialVerifier::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->with('fake-jwt')->andReturn([
                'iss' => 'https://accounts.google.com',
                'aud' => 'test',
                'email' => 'student@my.xu.edu.ph',
                'email_verified' => true,
                'name' => 'Maria Santos',
            ]);
        });

        config(['services.google.client_id' => 'test']);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/me/google-profile', ['credential' => 'fake-jwt']);

        $response->assertOk();
        $response->assertJsonPath('user.name', 'Maria Santos');
        $response->assertJsonPath('user.needs_profile_enrichment', false);

        $this->assertSame('Maria Santos', $user->fresh()->name);
    }

    public function test_google_profile_rejects_mismatched_google_email(): void
    {
        $this->seed(RoleSeeder::class);
        $student = Role::where('slug', 'student')->first();
        $user = User::create([
            'name' => RegistrationDisplayName::FALLBACK,
            'email' => 'student@my.xu.edu.ph',
            'password' => Hash::make('secret'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        $this->mock(\App\Contracts\GoogleCredentialVerifier::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn([
                'iss' => 'https://accounts.google.com',
                'aud' => 'test',
                'email' => 'someone.else@gmail.com',
                'email_verified' => true,
                'name' => 'Other Person',
            ]);
        });

        config(['services.google.client_id' => 'test']);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/me/google-profile', ['credential' => 'fake-jwt']);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Google account email must match your XU account email.']);
    }

    public function test_google_profile_rejects_when_name_already_set(): void
    {
        $this->seed(RoleSeeder::class);
        $student = Role::where('slug', 'student')->first();
        $user = User::create([
            'name' => 'Already Set',
            'email' => 'student@my.xu.edu.ph',
            'password' => Hash::make('secret'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/me/google-profile', ['credential' => 'fake-jwt']);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Your profile already has a display name.']);
    }
}
