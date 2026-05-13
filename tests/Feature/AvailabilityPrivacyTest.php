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

class AvailabilityPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_requires_authentication(): void
    {
        $this->getJson('/api/availability?date=2026-08-15')->assertUnauthorized();
    }

    public function test_availability_masks_other_users_reservation_details(): void
    {
        $studentRole = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 't']);

        $owner = User::factory()->create(['role_id' => $studentRole->id, 'is_activated' => true]);
        $viewer = User::factory()->create(['role_id' => $studentRole->id, 'is_activated' => true]);

        $space = Space::create([
            'name' => 'Privacy Room',
            'slug' => 'privacy-'.uniqid(),
            'type' => 'avr',
            'capacity' => 8,
            'is_active' => true,
        ]);

        $start = Carbon::parse('2026-08-15 10:00:00', config('app.timezone'));
        $end = Carbon::parse('2026-08-15 11:00:00', config('app.timezone'));

        Reservation::create([
            'user_id' => $owner->id,
            'space_id' => $space->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'event_title' => 'Secret meeting',
            'event_description' => 'Do not leak',
            'purpose' => 'fallback purpose',
        ]);

        Sanctum::actingAs($viewer);
        $resp = $this->getJson('/api/availability?date=2026-08-15&space_id='.$space->id);
        $resp->assertOk();
        $row = collect($resp->json('data'))->firstWhere('space.id', $space->id);
        $this->assertNotNull($row);
        $this->assertCount(1, $row['reserved_slots']);
        $slot = $row['reserved_slots'][0];
        $this->assertNull($slot['user']);
        $this->assertNull($slot['title']);
        $this->assertNull($slot['description']);

        Sanctum::actingAs($owner);
        $resp2 = $this->getJson('/api/availability?date=2026-08-15&space_id='.$space->id);
        $resp2->assertOk();
        $row2 = collect($resp2->json('data'))->firstWhere('space.id', $space->id);
        $slot2 = $row2['reserved_slots'][0];
        $this->assertSame($owner->id, $slot2['user']['id']);
        $this->assertSame('Secret meeting', $slot2['title']);
        $this->assertSame('Do not leak', $slot2['description']);
    }

    public function test_operational_availability_reveals_details_for_staff_schedule(): void
    {
        $libRole = Role::firstOrCreate(['slug' => 'librarian'], ['name' => 'Librarian', 'description' => 't']);
        $studentRole = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 't']);

        $owner = User::factory()->create(['role_id' => $studentRole->id, 'is_activated' => true]);
        $librarian = User::factory()->create(['role_id' => $libRole->id, 'is_activated' => true]);

        $space = Space::create([
            'name' => 'Ops Room',
            'slug' => 'ops-'.uniqid(),
            'type' => 'avr',
            'capacity' => 6,
            'is_active' => true,
        ]);

        $start = Carbon::parse('2026-09-10 14:00:00', config('app.timezone'));
        $end = Carbon::parse('2026-09-10 15:00:00', config('app.timezone'));

        Reservation::create([
            'user_id' => $owner->id,
            'space_id' => $space->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'event_title' => 'Staff-visible title',
            'purpose' => 'p',
        ]);

        Sanctum::actingAs($librarian);
        $resp = $this->getJson('/api/availability?date=2026-09-10&space_id='.$space->id.'&operational=1');
        $resp->assertOk();
        $row = collect($resp->json('data'))->firstWhere('space.id', $space->id);
        $slot = $row['reserved_slots'][0];
        $this->assertSame($owner->id, $slot['user']['id']);
        $this->assertSame('Staff-visible title', $slot['title']);
    }

    public function test_user_api_reservation_show_allows_view_all_but_blocks_other_students(): void
    {
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 't']);
        $studentRole = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 't']);

        $owner = User::factory()->create(['role_id' => $studentRole->id, 'is_activated' => true]);
        $otherStudent = User::factory()->create(['role_id' => $studentRole->id, 'is_activated' => true]);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_activated' => true]);

        $space = Space::create([
            'name' => 'Show Guard Room',
            'slug' => 'show-guard-'.uniqid(),
            'type' => 'avr',
            'capacity' => 4,
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'user_id' => $owner->id,
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(9, 0),
            'end_at' => now()->addDays(2)->setTime(10, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Queue operators may read via user route when permitted',
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/reservations/'.$reservation->id)->assertOk();

        Sanctum::actingAs($otherStudent);
        $this->getJson('/api/reservations/'.$reservation->id)->assertForbidden();
    }
}
