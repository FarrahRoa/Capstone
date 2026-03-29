<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportExportPermissionTest extends TestCase
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

    private function seedReservationAndLog(): void
    {
        $requester = $this->makeUserWithRole('student', 'Student');
        $admin = $this->makeUserWithRole('admin', 'Admin');
        $space = Space::create([
            'name' => 'AVR',
            'slug' => 'avr',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'user_id' => $requester->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Report export test',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'admin_id' => $requester->id,
            'action' => ReservationLog::ACTION_CREATE,
        ]);
        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'admin_id' => $admin->id,
            'action' => ReservationLog::ACTION_APPROVE,
            'notes' => 'Approved for export test',
        ]);
    }

    public function test_admin_can_export_reports_json_with_consistent_labels(): void
    {
        $this->seedReservationAndLog();
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/reports/export?format=json');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary',
            'status_totals',
            'action_totals',
            'recent_activity',
            'reservation_rows',
        ]);
        $response->assertJsonFragment([
            'status' => Reservation::STATUS_APPROVED,
            'label' => Reservation::statusLabel(Reservation::STATUS_APPROVED),
        ]);
        $response->assertJsonFragment([
            'action' => ReservationLog::ACTION_APPROVE,
            'label' => ReservationLog::actionLabel(ReservationLog::ACTION_APPROVE),
        ]);
    }

    public function test_librarian_cannot_export_reports(): void
    {
        $this->seedReservationAndLog();
        $librarian = $this->makeUserWithRole('librarian', 'Librarian');
        Sanctum::actingAs($librarian);

        $response = $this->getJson('/api/admin/reports/export?format=json');
        $response->assertStatus(403);
    }
}
