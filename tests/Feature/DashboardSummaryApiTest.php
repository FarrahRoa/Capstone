<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Documents JSON shape the HomeDashboard relies on for “At a glance” counts.
 */
class DashboardSummaryApiTest extends TestCase
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

    public function test_my_reservations_index_exposes_total_for_home_dashboard(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/reservations');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'total']);
        $this->assertIsInt($response->json('total'));
    }

    public function test_admin_reservations_filtered_exposes_total_for_home_dashboard(): void
    {
        $assistant = $this->makeUserWithRole('student_assistant', 'Student Assistant');
        Sanctum::actingAs($assistant);

        $response = $this->getJson('/api/admin/reservations?status=pending_approval');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'total']);
        $this->assertIsInt($response->json('total'));
    }
}
