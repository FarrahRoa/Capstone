<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOperatingHoursPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $slug, string $name): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => 'Test role']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    public function test_admin_can_view_and_update_operating_hours(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);

        $get = $this->getJson('/api/admin/policies/operating-hours');
        $get->assertOk();
        $get->assertJsonPath('data.slug', PolicyDocument::SLUG_OPERATING_HOURS);
        $get->assertJsonStructure(['data' => ['hours' => ['day_start', 'day_end']]]);

        $put = $this->putJson('/api/admin/policies/operating-hours', [
            'day_start' => '08:00',
            'day_end' => '18:00',
        ]);
        $put->assertOk();
        $put->assertJsonPath('message', 'Operating hours saved.');
        $put->assertJsonPath('data.hours.day_start', '08:00');
        $put->assertJsonPath('data.hours.day_end', '18:00');

        $doc = PolicyDocument::operatingHours()->fresh();
        $this->assertSame(PolicyDocument::SLUG_OPERATING_HOURS, $doc->slug);
        $payload = json_decode((string) $doc->content, true);
        $this->assertSame('08:00', $payload['day_start'] ?? null);
        $this->assertSame('18:00', $payload['day_end'] ?? null);
    }

    public function test_invalid_time_range_is_rejected(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);

        $resp = $this->putJson('/api/admin/policies/operating-hours', [
            'day_start' => '18:00',
            'day_end' => '08:00',
        ]);
        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['day_end']);
    }

    public function test_non_admin_cannot_view_or_update_operating_hours(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);

        $this->getJson('/api/admin/policies/operating-hours')->assertStatus(403);
        $this->putJson('/api/admin/policies/operating-hours', [
            'day_start' => '08:00',
            'day_end' => '18:00',
        ])->assertStatus(403);
    }
}

