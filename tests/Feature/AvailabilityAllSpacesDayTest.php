<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Supports the admin schedule overview: GET /availability?date=Y-m-d without space_id returns every active space.
 */
class AvailabilityAllSpacesDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_without_space_id_returns_all_active_spaces_for_the_day(): void
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
            'name' => 'Overview Room A',
            'slug' => 'ov-a-' . uniqid(),
            'type' => 'avr',
            'capacity' => 8,
            'is_active' => true,
        ]);
        $spaceB = Space::create([
            'name' => 'Overview Room B',
            'slug' => 'ov-b-' . uniqid(),
            'type' => 'avr',
            'capacity' => 8,
            'is_active' => true,
        ]);

        $start = Carbon::parse('2026-08-12 09:00:00', config('app.timezone'));
        $end = Carbon::parse('2026-08-12 10:00:00', config('app.timezone'));

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $spaceA->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'All-spaces availability test',
        ]);

        $response = $this->getJson('/api/availability?date=2026-08-12');
        $response->assertOk();

        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertGreaterThanOrEqual(2, count($rows));

        $ids = collect($rows)->pluck('space.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $this->assertContains($spaceA->id, $ids);
        $this->assertContains($spaceB->id, $ids);

        $rowA = collect($rows)->first(fn ($row) => (int) ($row['space']['id'] ?? 0) === $spaceA->id);
        $rowB = collect($rows)->first(fn ($row) => (int) ($row['space']['id'] ?? 0) === $spaceB->id);
        $this->assertNotNull($rowA);
        $this->assertNotNull($rowB);
        $this->assertCount(1, $rowA['reserved_slots']);
        $this->assertCount(0, $rowB['reserved_slots']);
    }
}
