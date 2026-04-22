<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacultyReportAccessTest extends TestCase
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

    private function seedReservationData(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        $space = Space::create([
            'name' => 'Room A',
            'slug' => 'room-a',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        Reservation::create([
            'user_id' => $student->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Report seed reservation',
        ]);
    }

    public function test_librarian_can_access_reports_endpoint(): void
    {
        $this->seedReservationData();
        $librarian = $this->makeUserWithRole('librarian', 'Librarian');
        Sanctum::actingAs($librarian);

        $response = $this->getJson('/api/admin/reports');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'period',
                'summary',
                'status_totals',
                'action_totals',
                'recent_activity',
                'reservation_rows',
                'reservations_by_college_office',
                'student_college',
                'faculty_staff_office',
                'student_year_level',
                'room_utilization',
                'peak_hours',
                'average_reservation_duration_minutes',
                'average_approval_time_minutes',
            ],
        ]);
    }

    public function test_faculty_cannot_access_reports_endpoint(): void
    {
        $this->seedReservationData();
        $faculty = $this->makeUserWithRole('faculty', 'Faculty');
        Sanctum::actingAs($faculty);

        $response = $this->getJson('/api/admin/reports');

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Insufficient permissions.']);
    }

    public function test_student_cannot_access_reports_endpoint(): void
    {
        $this->seedReservationData();
        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/admin/reports');

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Insufficient permissions.']);
    }
}
