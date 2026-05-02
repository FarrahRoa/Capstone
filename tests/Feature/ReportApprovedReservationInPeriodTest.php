<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApprovedReservationInPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_reservation_with_start_after_period_end_is_included_when_approved_in_range(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-05-02 14:00:00', $tz));

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 'Test']);
        $studentRole = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);

        $student = User::create([
            'name' => 'Report Student',
            'email' => 'repstu@my.xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $studentRole->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_STUDENT,
            'college_office' => 'College of Engineering',
            'profile_completed_at' => now(),
        ]);

        $admin = User::create([
            'name' => 'Report Admin',
            'email' => 'repadmin@xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'college_office' => 'Library',
            'profile_completed_at' => now(),
        ]);

        $space = Space::create([
            'name' => 'Future Room',
            'slug' => 'future-room-' . uniqid(),
            'type' => 'avr',
            'capacity' => 8,
            'is_active' => true,
        ]);

        $bookingStart = Carbon::parse('2026-05-10 09:00:00', $tz);
        $bookingEnd = Carbon::parse('2026-05-10 10:00:00', $tz);

        $reservation = Reservation::create([
            'user_id' => $student->id,
            'space_id' => $space->id,
            'start_at' => $bookingStart,
            'end_at' => $bookingEnd,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Future booking',
            'approved_at' => Carbon::parse('2026-05-02 13:30:00', $tz),
            'approved_by' => $admin->id,
        ]);

        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'actor_user_id' => $admin->id,
            'actor_type' => ReservationLog::ACTOR_ADMIN,
            'action' => ReservationLog::ACTION_APPROVE,
            'notes' => null,
            'created_at' => Carbon::parse('2026-05-02 13:30:00', $tz),
        ]);

        $from = '2026-05-01';
        $to = '2026-05-02';

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/reports?period=custom&from={$from}&to={$to}");
        $response->assertOk();

        $this->assertSame(1, $response->json('data.summary.approved_reservations'));
        $this->assertSame(1, $response->json('data.summary.total_reservations'));

        $rooms = collect($response->json('data.room_utilization'));
        $this->assertTrue($rooms->contains(fn ($row) => ($row['space_name'] ?? '') === 'Future Room' && (int) ($row['count'] ?? 0) === 1));

        $peak = $response->json('data.peak_hours');
        $this->assertIsArray($peak);
        $this->assertSame(1, (int) ($peak['09'] ?? 0));

        $byCollege = $response->json('data.student_college');
        $this->assertIsArray($byCollege);
        $this->assertSame(1, $byCollege['College of Engineering'] ?? 0);
    }
}
