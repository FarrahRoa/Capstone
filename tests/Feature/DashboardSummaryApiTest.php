<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
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

    public function test_my_active_reservations_count_endpoint_exposes_count_for_home_dashboard(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);

        $space = Space::create([
            'name' => 'Room A',
            'slug' => 'room-a',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        // Counts: future pending/approved only.
        Reservation::create([
            'user_id' => $student->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Future pending',
        ]);
        Reservation::create([
            'user_id' => $student->id,
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(10, 0),
            'end_at' => now()->addDays(2)->setTime(11, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Future approved',
        ]);

        // Does not count: cancelled/rejected.
        Reservation::create([
            'user_id' => $student->id,
            'space_id' => $space->id,
            'start_at' => now()->addDays(3)->setTime(11, 0),
            'end_at' => now()->addDays(3)->setTime(12, 0),
            'status' => Reservation::STATUS_CANCELLED,
            'purpose' => 'Cancelled',
        ]);
        Reservation::create([
            'user_id' => $student->id,
            'space_id' => $space->id,
            'start_at' => now()->addDays(4)->setTime(12, 0),
            'end_at' => now()->addDays(4)->setTime(13, 0),
            'status' => Reservation::STATUS_REJECTED,
            'purpose' => 'Rejected',
        ]);

        // Does not count: past finished even if status is approved.
        Reservation::create([
            'user_id' => $student->id,
            'space_id' => $space->id,
            'start_at' => now()->subDay()->setTime(9, 0),
            'end_at' => now()->subDay()->setTime(10, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Past approved',
        ]);

        $response = $this->getJson('/api/reservations/active-count');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['count']]);
        $this->assertSame(2, $response->json('data.count'));
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

    public function test_admin_reservations_unfiltered_exposes_total_and_rows_for_home_dashboard(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/reservations?per_page=5');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'total']);
        $this->assertIsInt($response->json('total'));
        $this->assertIsArray($response->json('data'));
    }
}
