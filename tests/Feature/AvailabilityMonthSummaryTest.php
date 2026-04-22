<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityMonthSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_summary_lists_date_when_all_bookable_slots_are_blocked(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test role']
        );
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);

        $space = Space::create([
            'name' => 'Summary Room',
            'slug' => 'summary-room-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $tz = config('app.timezone');
        $day = Carbon::parse('2026-06-10', $tz)->startOfDay();

        for ($h = 9; $h < 18; $h++) {
            foreach ([0, 30] as $minute) {
                $start = $day->copy()->setTime($h, $minute, 0);
                $end = $minute === 0
                    ? $day->copy()->setTime($h, 30, 0)
                    : $day->copy()->setTime($h + 1, 0, 0);
                Reservation::create([
                    'user_id' => $user->id,
                    'space_id' => $space->id,
                    'start_at' => $start,
                    'end_at' => $end,
                    'status' => Reservation::STATUS_APPROVED,
                    'purpose' => 'Block slot',
                ]);
            }
        }

        $response = $this->getJson('/api/availability/month-summary?' . http_build_query([
            'space_id' => $space->id,
            'from' => '2026-06-10',
            'to' => '2026-06-10',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.fully_booked_dates', ['2026-06-10']);
    }

    public function test_month_summary_omits_date_when_one_slot_remains_free(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test role']
        );
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);

        $space = Space::create([
            'name' => 'Partial Room',
            'slug' => 'partial-room-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $tz = config('app.timezone');
        $day = Carbon::parse('2026-07-01', $tz)->startOfDay();

        for ($h = 9; $h < 18; $h++) {
            foreach ([0, 30] as $minute) {
                if ($h === 9 && $minute === 0) {
                    continue;
                }
                $start = $day->copy()->setTime($h, $minute, 0);
                $end = $minute === 0
                    ? $day->copy()->setTime($h, 30, 0)
                    : $day->copy()->setTime($h + 1, 0, 0);
                Reservation::create([
                    'user_id' => $user->id,
                    'space_id' => $space->id,
                    'start_at' => $start,
                    'end_at' => $end,
                    'status' => Reservation::STATUS_APPROVED,
                    'purpose' => 'Block',
                ]);
            }
        }

        $response = $this->getJson('/api/availability/month-summary?' . http_build_query([
            'space_id' => $space->id,
            'from' => '2026-07-01',
            'to' => '2026-07-01',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.fully_booked_dates', []);
    }

    public function test_month_summary_rejects_range_over_45_days(): void
    {
        $space = Space::create([
            'name' => 'Range Room',
            'slug' => 'range-room-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/availability/month-summary?' . http_build_query([
            'space_id' => $space->id,
            'from' => '2026-01-01',
            'to' => '2026-03-01',
        ]));

        $response->assertStatus(422);
    }

    public function test_month_summary_returns_404_for_inactive_space(): void
    {
        $space = Space::create([
            'name' => 'Inactive Room',
            'slug' => 'inactive-room-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/availability/month-summary?' . http_build_query([
            'space_id' => $space->id,
            'from' => '2026-02-01',
            'to' => '2026-02-05',
        ]));

        $response->assertNotFound();
    }
}
