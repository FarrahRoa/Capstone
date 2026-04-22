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

class AdminScheduleOperationalConfabLabelsTest extends TestCase
{
    use RefreshDatabase;

    private function librarian(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'librarian'],
            ['name' => 'Librarian', 'description' => 'Test']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function student(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Test']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    public function test_spaces_operational_returns_numbered_confab_name_and_pool_label_for_view_all_role(): void
    {
        $pool = Space::query()->firstWhere('is_confab_pool', true);
        $this->assertNotNull($pool);

        $room = Space::create([
            'name' => 'Confab 777',
            'slug' => 'confab-777-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 8,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        Sanctum::actingAs($this->librarian());

        $rows = $this->getJson('/api/spaces?operational=1')->json('data');
        $this->assertIsArray($rows);
        $poolRow = collect($rows)->firstWhere('id', $pool->id);
        $roomRow = collect($rows)->firstWhere('id', $room->id);
        $this->assertSame('Confab (pool — pending assignment)', $poolRow['name']);
        $this->assertSame('Confab 777', $roomRow['name']);
    }

    public function test_spaces_operational_flag_ignored_for_student_even_when_query_present(): void
    {
        $room = Space::create([
            'name' => 'Confab 888',
            'slug' => 'confab-888-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 4,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        Sanctum::actingAs($this->student());
        $rows = $this->getJson('/api/spaces?operational=1')->json('data');
        $roomRow = collect($rows)->firstWhere('id', $room->id);
        $this->assertSame('Confab', $roomRow['name']);
    }

    public function test_availability_operational_labels_match_physical_confab_for_admin_day_view(): void
    {
        $tz = config('app.timezone');
        $room = Space::create([
            'name' => 'Confab 999',
            'slug' => 'confab-999-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 5,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        Sanctum::actingAs($this->librarian());

        $date = Carbon::now($tz)->addDays(2)->startOfDay()->format('Y-m-d');
        $rows = $this->getJson('/api/availability?date='.$date.'&operational=1')->json('data');
        $hit = collect($rows)->firstWhere('space.id', $room->id);
        $this->assertNotNull($hit);
        $this->assertSame('Confab 999', $hit['space']['name']);
    }

    public function test_second_reservation_overlapping_same_assigned_confab_room_is_rejected(): void
    {
        $tz = config('app.timezone');
        $actor = $this->admin();
        $holder = $this->student();
        $room = Space::create([
            'name' => 'Confab Overlap A',
            'slug' => 'confab-ov-a-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 4,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        $start = Carbon::now($tz)->addDays(5)->setTime(10, 0, 0);
        $end = Carbon::now($tz)->addDays(5)->setTime(11, 0, 0);

        Reservation::create([
            'user_id' => $holder->id,
            'space_id' => $room->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'reservation_number' => 'RES-TESTOV1',
            'purpose' => 'Hold slot',
        ]);

        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/reservations', [
            'space_id' => $room->id,
            'start_at' => $start->copy()->addMinutes(30)->toIso8601String(),
            'end_at' => $end->copy()->addMinutes(30)->toIso8601String(),
            'purpose' => 'Should fail',
            'event_title' => 'Overlap test',
            'participant_count' => 2,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slot']);
    }

    public function test_two_overlapping_reservations_on_different_confab_rooms_are_allowed(): void
    {
        $tz = config('app.timezone');
        $actor = $this->admin();
        $a = Space::create([
            'name' => 'Confab Overlap B1',
            'slug' => 'confab-ov-b1-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 4,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);
        $b = Space::create([
            'name' => 'Confab Overlap B2',
            'slug' => 'confab-ov-b2-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 4,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        $start = Carbon::now($tz)->addDays(6)->setTime(14, 0, 0);
        $end = Carbon::now($tz)->addDays(6)->setTime(15, 0, 0);

        Reservation::create([
            'user_id' => $actor->id,
            'space_id' => $a->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'reservation_number' => 'RES-TESTOV2',
            'purpose' => 'Room A',
        ]);

        Sanctum::actingAs($actor);

        $this->postJson('/api/reservations', [
            'space_id' => $b->id,
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
            'purpose' => 'Room B same window',
            'event_title' => 'Parallel',
            'participant_count' => 2,
        ])->assertStatus(201);
    }

    public function test_space_model_operational_display_name_for_pool_and_numbered_room(): void
    {
        $pool = Space::query()->firstWhere('is_confab_pool', true);
        $this->assertNotNull($pool);
        $this->assertSame('Confab (pool — pending assignment)', $pool->scheduleOperationalDisplayName());

        $room = Space::create([
            'name' => 'Confab 444',
            'slug' => 'confab-444-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 4,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);
        $this->assertSame('Confab 444', $room->scheduleOperationalDisplayName());
        $this->assertSame('Confab', $room->userFacingName());
    }
}
