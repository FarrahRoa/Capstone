<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserReservationCancelTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
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

    private function otherStudent(): User
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

    private function space(): Space
    {
        return Space::create([
            'name' => 'Study Room A',
            'slug' => 'study-room-a-' . uniqid(),
            'type' => 'avr',
            'capacity' => 6,
            'is_active' => true,
        ]);
    }

    public function test_owner_can_cancel_future_pending_reservation(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $user = $this->student();
        Sanctum::actingAs($user);
        $space = $this->space();

        $res = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Test',
        ]);

        $resp = $this->postJson("/api/reservations/{$res->id}/cancel");

        $resp->assertOk();
        $resp->assertJsonPath('message', 'Reservation cancelled.');
        $resp->assertJsonPath('data.status', Reservation::STATUS_CANCELLED);

        $this->assertDatabaseHas('reservations', [
            'id' => $res->id,
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $res->id,
            'actor_user_id' => $user->id,
            'actor_type' => ReservationLog::ACTOR_USER,
            'action' => ReservationLog::ACTION_CANCEL,
        ]);
    }

    public function test_non_owner_cannot_cancel(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $owner = $this->student();
        $other = $this->otherStudent();
        Sanctum::actingAs($other);
        $space = $this->space();

        $res = Reservation::create([
            'user_id' => $owner->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Test',
        ]);

        $resp = $this->postJson("/api/reservations/{$res->id}/cancel");

        $resp->assertForbidden();
        $this->assertDatabaseHas('reservations', [
            'id' => $res->id,
            'status' => Reservation::STATUS_PENDING_APPROVAL,
        ]);
    }

    public function test_cannot_cancel_past_reservation(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-15 12:00:00', $tz));

        $user = $this->student();
        Sanctum::actingAs($user);
        $space = $this->space();

        $res = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Test',
        ]);

        $resp = $this->postJson("/api/reservations/{$res->id}/cancel");

        $resp->assertStatus(422);
        $resp->assertJsonPath('message', 'Past reservations cannot be cancelled.');
    }

    public function test_cannot_cancel_rejected_reservation(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $user = $this->student();
        Sanctum::actingAs($user);
        $space = $this->space();

        $res = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_REJECTED,
            'purpose' => 'Test',
        ]);

        $resp = $this->postJson("/api/reservations/{$res->id}/cancel");

        $resp->assertStatus(422);
        $resp->assertJsonPath('message', 'This reservation cannot be cancelled.');
    }
}
