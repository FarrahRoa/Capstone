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

class PublicScheduleOverviewTest extends TestCase
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

    public function test_public_schedule_overview_requires_no_authentication(): void
    {
        $user = $this->seedUser();
        $space = Space::create([
            'name' => 'Public Overview Room',
            'slug' => 'public-ov-' . uniqid(),
            'type' => 'avr',
            'capacity' => 6,
            'is_active' => true,
        ]);

        $start = Carbon::parse('2026-09-01 10:00:00', config('app.timezone'));
        $end = Carbon::parse('2026-09-01 11:00:00', config('app.timezone'));

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Secret purpose text',
        ]);

        $response = $this->getJson('/api/public/schedule-overview?date=2026-09-01&space_id=' . $space->id);
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'date',
                'timezone',
                'operating_hours' => ['day_start', 'day_end'],
                'booking_cutoff',
                'time_slots',
                'spaces' => [
                    [
                        'space' => ['id', 'name', 'type', 'slug', 'is_confab_pool'],
                        'occupied_slots',
                    ],
                ],
            ],
        ]);

        $payload = $response->json('data');
        $this->assertSame('2026-09-01', $payload['date']);
        $row = collect($payload['spaces'])->first(fn ($r) => (int) ($r['space']['id'] ?? 0) === $space->id);
        $this->assertNotNull($row);
        $this->assertCount(1, $row['occupied_slots']);
        $slot = $row['occupied_slots'][0];
        $this->assertArrayHasKey('start_at', $slot);
        $this->assertArrayHasKey('end_at', $slot);
        $this->assertArrayNotHasKey('id', $slot);
        $this->assertArrayNotHasKey('status', $slot);
        $this->assertArrayNotHasKey('user_id', $slot);
        $this->assertArrayNotHasKey('purpose', $slot);

        $spacePayload = $row['space'];
        $this->assertArrayNotHasKey('capacity', $spacePayload);
        $this->assertArrayNotHasKey('user_id', $spacePayload);
        $this->assertArrayHasKey('schedule_label', $spacePayload);
    }

    public function test_public_schedule_overview_without_space_id_returns_all_active_spaces(): void
    {
        $user = $this->seedUser();
        $a = Space::create([
            'name' => 'Multi Overview A',
            'slug' => 'multi-ov-a-' . uniqid(),
            'type' => 'avr',
            'capacity' => 6,
            'is_active' => true,
        ]);
        $b = Space::create([
            'name' => 'Multi Overview B',
            'slug' => 'multi-ov-b-' . uniqid(),
            'type' => 'lobby',
            'capacity' => 8,
            'is_active' => true,
        ]);

        $start = Carbon::parse('2026-09-10 11:00:00', config('app.timezone'));
        $end = Carbon::parse('2026-09-10 12:00:00', config('app.timezone'));
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $a->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Multi-space public overview',
        ]);

        $response = $this->getJson('/api/public/schedule-overview?date=2026-09-10');
        $response->assertOk();
        $ids = collect($response->json('data.spaces'))->pluck('space.id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
        $rowA = collect($response->json('data.spaces'))->first(fn ($r) => (int) ($r['space']['id'] ?? 0) === $a->id);
        $this->assertNotNull($rowA);
        $this->assertGreaterThanOrEqual(1, count($rowA['occupied_slots']));
        $rowB = collect($response->json('data.spaces'))->first(fn ($r) => (int) ($r['space']['id'] ?? 0) === $b->id);
        $this->assertNotNull($rowB);
        $this->assertCount(0, $rowB['occupied_slots']);
    }

    public function test_pending_reservation_blocks_internal_availability_but_not_public_overview(): void
    {
        $user = $this->seedUser();
        $space = Space::create([
            'name' => 'Pending Contrast Room',
            'slug' => 'pending-ov-' . uniqid(),
            'type' => 'avr',
            'capacity' => 6,
            'is_active' => true,
        ]);

        $start = Carbon::parse('2026-09-02 14:00:00', config('app.timezone'));
        $end = Carbon::parse('2026-09-02 15:00:00', config('app.timezone'));

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Pending internal',
        ]);

        $public = $this->getJson('/api/public/schedule-overview?date=2026-09-02&space_id=' . $space->id);
        $public->assertOk();
        $pubRow = collect($public->json('data.spaces'))->first(fn ($r) => (int) ($r['space']['id'] ?? 0) === $space->id);
        $this->assertNotNull($pubRow);
        $this->assertCount(0, $pubRow['occupied_slots'], 'Public overview must not treat pending as occupied');

        Sanctum::actingAs($user);
        $internal = $this->getJson('/api/availability?date=2026-09-02&space_id=' . $space->id);
        $internal->assertOk();
        $rows = $internal->json('data');
        $intRow = collect($rows)->first(fn ($r) => (int) ($r['space']['id'] ?? 0) === $space->id);
        $this->assertNotNull($intRow);
        $this->assertGreaterThanOrEqual(1, count($intRow['reserved_slots']), 'Authenticated-style availability still blocks on pending');
    }

    public function test_reservation_create_still_requires_login(): void
    {
        $user = $this->seedUser();
        $space = Space::create([
            'name' => 'Mutation Guard Room',
            'slug' => 'mut-guard-' . uniqid(),
            'type' => 'avr',
            'capacity' => 4,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2026-10-01 09:00:00',
            'end_at' => '2026-10-01 10:00:00',
            'purpose' => 'Should not work',
        ]);
        $response->assertUnauthorized();
    }
}
