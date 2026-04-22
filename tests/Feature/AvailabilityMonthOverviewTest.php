<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityMonthOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_overview_groups_days_by_space_with_blocking_reservations(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test role']
        );
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);

        $spaceA = Space::create([
            'name' => 'Confab 1',
            'slug' => 'confab-1-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
        $spaceB = Space::create([
            'name' => 'Boardroom',
            'slug' => 'boardroom-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $tz = config('app.timezone');
        $day = Carbon::parse('2026-04-13', $tz)->startOfDay();

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $spaceA->id,
            'start_at' => $day->copy()->setTime(9, 0, 0),
            'end_at' => $day->copy()->setTime(10, 0, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'A',
        ]);
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $spaceB->id,
            'start_at' => $day->copy()->setTime(10, 0, 0),
            'end_at' => $day->copy()->setTime(11, 0, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'B',
        ]);

        $resp = $this->getJson('/api/availability/month-overview?' . http_build_query([
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ]));

        $resp->assertOk();
        $resp->assertJsonPath('data.from', '2026-04-01');
        $resp->assertJsonPath('data.to', '2026-04-30');
        $ids = $resp->json('data.dates.2026-04-13');
        $this->assertIsArray($ids);
        sort($ids);
        $this->assertSame([$spaceA->id, $spaceB->id], $ids);
    }

    public function test_month_overview_rejects_range_over_45_days(): void
    {
        $resp = $this->getJson('/api/availability/month-overview?' . http_build_query([
            'from' => '2026-01-01',
            'to' => '2026-03-01',
        ]));
        $resp->assertStatus(422);
    }
}

