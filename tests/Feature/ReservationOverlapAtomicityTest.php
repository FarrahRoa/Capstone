<?php

namespace Tests\Feature;

use App\Mail\ReservationVerificationMail;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationOverlapAtomicityTest extends TestCase
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

    private function makeSpace(string $slug = 'avr'): Space
    {
        return Space::create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    private function payload(int $spaceId, string $start, string $end): array
    {
        return [
            'space_id' => $spaceId,
            'start_at' => $start,
            'end_at' => $end,
            'purpose' => 'Test',
            'event_title' => 'Overlap test',
            'participant_count' => 2,
        ];
    }

    public function test_non_overlapping_reservation_succeeds(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $space = $this->makeSpace('avr');
        $day = now()->addDay()->startOfDay();

        $r1 = $this->postJson('/api/reservations', $this->payload(
            $space->id,
            $day->copy()->setTime(9, 0)->toDateTimeString(),
            $day->copy()->setTime(10, 0)->toDateTimeString(),
        ));
        $r1->assertStatus(201);

        $r2 = $this->postJson('/api/reservations', $this->payload(
            $space->id,
            $day->copy()->setTime(10, 0)->toDateTimeString(),
            $day->copy()->setTime(11, 0)->toDateTimeString(),
        ));
        $r2->assertStatus(201);

        $this->assertDatabaseCount('reservations', 2);
        Mail::assertSent(ReservationVerificationMail::class, 2);
    }

    public function test_overlapping_reservation_fails_with_slot_error_and_does_not_insert(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $space = $this->makeSpace('avr');
        $day = now()->addDay()->startOfDay();

        $this->postJson('/api/reservations', $this->payload(
            $space->id,
            $day->copy()->setTime(9, 0)->toDateTimeString(),
            $day->copy()->setTime(10, 0)->toDateTimeString(),
        ))->assertStatus(201);

        $resp = $this->postJson('/api/reservations', $this->payload(
            $space->id,
            $day->copy()->setTime(9, 0)->toDateTimeString(),
            $day->copy()->setTime(10, 0)->toDateTimeString(),
        ));

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['slot']);

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_same_time_different_spaces_does_not_conflict(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $spaceA = $this->makeSpace('avr');
        $spaceB = $this->makeSpace('lobby');
        $day = now()->addDay()->startOfDay();

        $start = $day->copy()->setTime(9, 0)->toDateTimeString();
        $end = $day->copy()->setTime(10, 0)->toDateTimeString();

        $this->postJson('/api/reservations', $this->payload($spaceA->id, $start, $end))->assertStatus(201);
        $this->postJson('/api/reservations', $this->payload($spaceB->id, $start, $end))->assertStatus(201);

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_non_blocking_statuses_do_not_block_future_bookings(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $space = $this->makeSpace('avr');
        $day = now()->addDay()->startOfDay();

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(9, 0),
            'end_at' => $day->copy()->setTime(10, 0),
            'status' => Reservation::STATUS_CANCELLED,
            'purpose' => 'Cancelled',
        ]);

        $this->postJson('/api/reservations', $this->payload(
            $space->id,
            $day->copy()->setTime(9, 0)->toDateTimeString(),
            $day->copy()->setTime(10, 0)->toDateTimeString(),
        ))->assertStatus(201);

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_availability_uses_same_blocking_statuses_as_conflict_logic(): void
    {
        $user = $this->makeStudent();
        $space = $this->makeSpace('avr');
        $day = now()->addDay()->startOfDay();

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(9, 0),
            'end_at' => $day->copy()->setTime(10, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Approved',
        ]);

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(11, 0),
            'status' => Reservation::STATUS_CANCELLED,
            'purpose' => 'Cancelled',
        ]);

        $resp = $this->getJson('/api/availability?space_id='.$space->id.'&date='.$day->toDateString());
        $resp->assertStatus(200);
        $slots = $resp->json('data.0.reserved_slots');
        $this->assertIsArray($slots);

        $statuses = array_map(fn ($s) => $s['status'] ?? null, $slots);
        $this->assertContains(Reservation::STATUS_APPROVED, $statuses);
        $this->assertNotContains(Reservation::STATUS_CANCELLED, $statuses);
    }
}

