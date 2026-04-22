<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAvailabilityMonthSignalsTest extends TestCase
{
    use RefreshDatabase;

    private function seedUser(): User
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

    public function test_public_month_overview_only_counts_approved_reservations(): void
    {
        $user = $this->seedUser();

        $spaceApproved = Space::create([
            'name' => 'Public Month Approved',
            'slug' => 'pub-mo-a-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
        $spacePending = Space::create([
            'name' => 'Public Month Pending',
            'slug' => 'pub-mo-p-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $tz = config('app.timezone');
        $day = Carbon::parse('2026-04-13', $tz)->startOfDay();

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $spaceApproved->id,
            'start_at' => $day->copy()->setTime(9, 0, 0),
            'end_at' => $day->copy()->setTime(10, 0, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Approved',
        ]);
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $spacePending->id,
            'start_at' => $day->copy()->setTime(10, 0, 0),
            'end_at' => $day->copy()->setTime(11, 0, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Pending',
        ]);

        $resp = $this->getJson('/api/public/availability/month-overview?' . http_build_query([
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ]));

        $resp->assertOk();
        $ids = $resp->json('data.dates.2026-04-13');
        $this->assertIsArray($ids);
        $this->assertSame([$spaceApproved->id], $ids, 'Public overview must not include pending-only spaces');
    }

    public function test_public_month_summary_only_counts_approved_reservations_when_marking_fully_booked(): void
    {
        $user = $this->seedUser();

        $space = Space::create([
            'name' => 'Public Month Summary Room',
            'slug' => 'pub-ms-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $tz = config('app.timezone');
        $day = Carbon::parse('2026-04-18', $tz)->startOfDay();

        // Pending reservations across the whole day should not cause "Full" in public mode.
        for ($h = 9; $h < 18; $h++) {
            Reservation::create([
                'user_id' => $user->id,
                'space_id' => $space->id,
                'start_at' => $day->copy()->setTime($h, 0, 0),
                'end_at' => $day->copy()->setTime($h + 1, 0, 0),
                'status' => Reservation::STATUS_PENDING_APPROVAL,
                'purpose' => 'Pending block ' . $h,
            ]);
        }

        $resp = $this->getJson('/api/public/availability/month-summary?' . http_build_query([
            'space_id' => $space->id,
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ]));

        $resp->assertOk();
        $list = $resp->json('data.fully_booked_dates');
        $this->assertIsArray($list);
        $this->assertNotContains('2026-04-18', $list, 'Public summary must not treat pending as fully booked');
    }
}

