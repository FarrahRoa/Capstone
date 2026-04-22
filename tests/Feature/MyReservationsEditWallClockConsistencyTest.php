<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ensures the My Reservations edit UI follows the same half-hour wall-clock + duration model as new reservations,
 * not the legacy single 30-minute slot picker.
 */
class MyReservationsEditWallClockConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test role']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    public function test_my_reservations_page_uses_shared_booking_time_utils_not_fixed_slot_list(): void
    {
        $path = base_path('resources/js/pages/MyReservations.jsx');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('reservationBookingTimes', $content);
        $this->assertStringContainsString('buildStartEndPayloadFromWallClock', $content);
        $this->assertStringContainsString('HalfHourWallClockSelect', $content);
        $this->assertStringNotContainsString('type="time"', $content);
        $this->assertStringNotContainsString('buildManilaHalfHourSlots', $content);
        $this->assertStringNotContainsString('formatManilaHalfHourSlotLabel', $content);
    }

    public function test_user_can_patch_reservation_to_multi_hour_half_hour_aligned_window(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $space = Space::create([
            'name' => 'Reading Room A',
            'slug' => 'reading-room-a-' . uniqid(),
            'type' => 'avr',
            'capacity' => 8,
            'is_active' => true,
        ]);

        $res = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Study',
        ]);

        $resp = $this->patchJson("/api/reservations/{$res->id}", [
            'space_id' => $space->id,
            'start_at' => '2026-04-12T10:00:00+08:00',
            'end_at' => '2026-04-12T12:30:00+08:00',
        ]);

        $resp->assertOk();
        $row = Reservation::query()->find($res->id);
        $this->assertNotNull($row);
        $this->assertSame('2026-04-12 10:00:00', $row->start_at->timezone($tz)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-12 12:30:00', $row->end_at->timezone($tz)->format('Y-m-d H:i:s'));
    }
}
