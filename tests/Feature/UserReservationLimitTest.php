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

class UserReservationLimitTest extends TestCase
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

    private function payload(int $spaceId, \Illuminate\Support\Carbon $day, int $hour): array
    {
        return [
            'space_id' => $spaceId,
            'start_at' => $day->copy()->setTime($hour, 0)->toDateTimeString(),
            'end_at' => $day->copy()->setTime($hour + 1, 0)->toDateTimeString(),
            'purpose' => 'Test',
            'event_title' => 'Limit test',
            'participant_count' => 2,
        ];
    }

    public function test_user_with_fewer_than_3_active_reservations_can_create(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace('avr');
        $day = now()->addDays(2)->startOfDay();

        // 2 active reservations (pending_approval + approved) should still allow create.
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(9, 0),
            'end_at' => $day->copy()->setTime(10, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Existing',
        ]);
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(11, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Existing',
        ]);

        $resp = $this->postJson('/api/reservations', $this->payload($space->id, $day, 11));
        $resp->assertStatus(201);

        $this->assertDatabaseCount('reservations', 3);
        Mail::assertSent(ReservationVerificationMail::class, 1);
    }

    public function test_user_with_exactly_3_active_reservations_cannot_create_another(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace('avr');
        $day = now()->addDays(2)->startOfDay();

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(9, 0),
            'end_at' => $day->copy()->setTime(10, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Existing',
        ]);
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(11, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Existing',
        ]);
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(11, 0),
            'end_at' => $day->copy()->setTime(12, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Existing',
        ]);

        $resp = $this->postJson('/api/reservations', $this->payload($space->id, $day, 12));
        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['reservation']);
        $resp->assertJsonFragment([
            'You already have 3 active reservations. Cancel or complete an existing reservation before making another.',
        ]);

        $this->assertDatabaseCount('reservations', 3);
        Mail::assertNothingSent();
    }

    public function test_rejected_and_cancelled_do_not_count_toward_limit(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace('avr');
        $day = now()->addDays(2)->startOfDay();

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(9, 0),
            'end_at' => $day->copy()->setTime(10, 0),
            'status' => Reservation::STATUS_REJECTED,
            'purpose' => 'Rejected',
        ]);
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(11, 0),
            'status' => Reservation::STATUS_CANCELLED,
            'purpose' => 'Cancelled',
        ]);

        // 2 active plus these 2 terminal should still allow create.
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(11, 0),
            'end_at' => $day->copy()->setTime(12, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Active',
        ]);
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(12, 0),
            'end_at' => $day->copy()->setTime(13, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Active',
        ]);

        $resp = $this->postJson('/api/reservations', $this->payload($space->id, $day, 13));
        $resp->assertStatus(201);

        $this->assertDatabaseCount('reservations', 5);
        Mail::assertSent(ReservationVerificationMail::class, 1);
    }

    public function test_past_reservations_do_not_count_toward_limit_even_if_status_is_approved_or_pending(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace('avr');

        // Past approved should not count.
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => now()->subDays(2)->setTime(9, 0),
            'end_at' => now()->subDays(2)->setTime(10, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Past',
        ]);

        // 2 active in the future.
        $day = now()->addDays(2)->startOfDay();
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(11, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Active',
        ]);
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(11, 0),
            'end_at' => $day->copy()->setTime(12, 0),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Active',
        ]);

        $resp = $this->postJson('/api/reservations', $this->payload($space->id, $day, 12));
        $resp->assertStatus(201);

        $this->assertDatabaseCount('reservations', 4);
        Mail::assertSent(ReservationVerificationMail::class, 1);
    }
}

