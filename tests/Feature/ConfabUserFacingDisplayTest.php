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

class ConfabUserFacingDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_spaces_list_shows_confab_pool_as_confab_not_legacy_phrase(): void
    {
        $pool = Space::query()->firstWhere('is_confab_pool', true);
        $this->assertNotNull($pool);

        $resp = $this->getJson('/api/spaces');
        $resp->assertOk();
        $rows = $resp->json('data');
        $this->assertIsArray($rows);
        $hit = collect($rows)->firstWhere('slug', 'confab-pool');
        $this->assertNotNull($hit);
        $this->assertSame('Confab', $hit['name']);
        $this->assertStringNotContainsString('room assigned', (string) $hit['name']);
    }

    public function test_public_spaces_list_maps_assignable_confab_rooms_to_confab_while_db_name_stays_distinct(): void
    {
        $room = Space::create([
            'name' => 'Space 9',
            'slug' => 'space-nine-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 4,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        $resp = $this->getJson('/api/spaces');
        $resp->assertOk();
        $row = collect($resp->json('data'))->firstWhere('id', $room->id);
        $this->assertNotNull($row);
        $this->assertSame('Confab', $row['name']);

        $room->refresh();
        $this->assertSame('Space 9', $room->name, 'Database name remains for admin/internal use.');
    }

    public function test_non_confab_space_name_unchanged_in_public_spaces_list(): void
    {
        $avr = Space::create([
            'name' => 'Overview AVR',
            'slug' => 'overview-avr-'.uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $resp = $this->getJson('/api/spaces');
        $resp->assertOk();
        $row = collect($resp->json('data'))->firstWhere('id', $avr->id);
        $this->assertSame('Overview AVR', $row['name']);
    }

    public function test_public_schedule_overview_uses_user_facing_confab_label(): void
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 't']);
        $user = User::factory()->create(['role_id' => $role->id, 'is_activated' => true]);

        $room = Space::create([
            'name' => 'Space 7',
            'slug' => 'space-seven-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 4,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        $start = Carbon::parse('2026-08-10 10:00:00', config('app.timezone'));
        $end = Carbon::parse('2026-08-10 11:00:00', config('app.timezone'));
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $room->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Test',
        ]);

        $resp = $this->getJson('/api/public/schedule-overview?date=2026-08-10&space_id='.$room->id);
        $resp->assertOk();
        $payload = $resp->json('data');
        $row = collect($payload['spaces'])->first(fn ($r) => (int) ($r['space']['id'] ?? 0) === $room->id);
        $this->assertNotNull($row);
        $this->assertSame('Confab', $row['space']['name']);
    }

    public function test_user_reservation_show_masks_assignable_confab_name(): void
    {
        $studentRole = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 't']);
        $user = User::factory()->create(['role_id' => $studentRole->id, 'is_activated' => true]);

        $room = Space::create([
            'name' => 'Confab 4',
            'slug' => 'confab-four-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 4,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $room->id,
            'start_at' => now()->addDays(3)->setTime(10, 0),
            'end_at' => now()->addDays(3)->setTime(11, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Meeting',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/reservations/'.$reservation->id)
            ->assertOk()
            ->assertJsonPath('data.space.name', 'Confab');

        $room->refresh();
        $this->assertSame('Confab 4', $room->name);
    }

    public function test_admin_spaces_endpoint_keeps_physical_confab_room_name_for_assignment_ui(): void
    {
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Test admin for spaces.manage']
        );
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_activated' => true]);

        $room = Space::create([
            'name' => 'Confab 2',
            'slug' => 'confab-two-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 4,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        Sanctum::actingAs($admin);

        $resp = $this->getJson('/api/admin/spaces');
        $resp->assertOk();
        $row = collect($resp->json('data'))->firstWhere('id', $room->id);
        $this->assertNotNull($row);
        $this->assertSame('Confab 2', $row['name']);
    }
}
