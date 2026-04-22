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

class UserReservationEditTest extends TestCase
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

    private function makeAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Test role']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function makeSpace(string $name): Space
    {
        return Space::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    public function test_user_can_edit_own_pending_reservation_and_status_resets_to_pending_approval(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $spaceA = $this->makeSpace('Confab 1');
        $spaceB = $this->makeSpace('Boardroom');

        $res = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $spaceA->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Test',
        ]);

        $resp = $this->patchJson("/api/reservations/{$res->id}", [
            'space_id' => $spaceB->id,
            'start_at' => '2026-04-13T10:00:00+08:00',
            'end_at' => '2026-04-13T11:00:00+08:00',
        ]);

        $resp->assertOk();
        $this->assertDatabaseHas('reservations', [
            'id' => $res->id,
            'space_id' => $spaceB->id,
            'status' => Reservation::STATUS_PENDING_APPROVAL,
        ]);
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $res->id,
            'actor_user_id' => $user->id,
            'actor_type' => ReservationLog::ACTOR_USER,
            'action' => ReservationLog::ACTION_UPDATE,
        ]);
    }

    public function test_user_can_edit_own_approved_reservation_and_status_resets_to_pending_approval(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $user = $this->makeStudent();
        $admin = $this->makeAdmin();
        Sanctum::actingAs($user);
        $space = $this->makeSpace('AVR');

        $res = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_APPROVED,
            'reservation_number' => 'RES-TEST1234',
            'approved_by' => $admin->id,
            'approved_at' => Carbon::now($tz),
            'purpose' => 'Approved',
        ]);

        $resp = $this->patchJson("/api/reservations/{$res->id}", [
            'space_id' => $space->id,
            'start_at' => '2026-04-13T09:00:00+08:00',
            'end_at' => '2026-04-13T10:00:00+08:00',
        ]);

        $resp->assertOk();
        $row = Reservation::find($res->id);
        $this->assertNotNull($row);
        $this->assertSame(Reservation::STATUS_PENDING_APPROVAL, $row->status);
        $this->assertNull($row->approved_by);
        $this->assertNull($row->approved_at);
        $this->assertNull($row->reservation_number);
    }

    public function test_user_cannot_edit_cancelled_or_rejected_or_past_reservation(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace('Confab 2');

        $cancelled = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_CANCELLED,
        ]);
        $rejected = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 11:00:00', $tz),
            'status' => Reservation::STATUS_REJECTED,
        ]);
        $past = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-01 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-01 10:00:00', $tz),
            'status' => Reservation::STATUS_APPROVED,
        ]);

        foreach ([$cancelled, $rejected] as $r) {
            $resp = $this->patchJson("/api/reservations/{$r->id}", [
                'space_id' => $space->id,
                'start_at' => '2026-04-13T09:00:00+08:00',
                'end_at' => '2026-04-13T10:00:00+08:00',
            ]);
            $resp->assertStatus(422);
        }

        $respPast = $this->patchJson("/api/reservations/{$past->id}", [
            'space_id' => $space->id,
            'start_at' => '2026-04-13T09:00:00+08:00',
            'end_at' => '2026-04-13T10:00:00+08:00',
        ]);
        $respPast->assertStatus(422);
    }

    public function test_user_cannot_edit_another_users_reservation(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $owner = $this->makeStudent();
        $attacker = $this->makeStudent();
        $space = $this->makeSpace('Lobby');

        $res = Reservation::create([
            'user_id' => $owner->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
        ]);

        Sanctum::actingAs($attacker);
        $resp = $this->patchJson("/api/reservations/{$res->id}", [
            'space_id' => $space->id,
            'start_at' => '2026-04-13T09:00:00+08:00',
            'end_at' => '2026-04-13T10:00:00+08:00',
        ]);
        $resp->assertStatus(403);
    }

    public function test_backend_rejects_taken_slot_during_edit_and_admin_queue_sees_edited_reservation(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $user = $this->makeStudent();
        $admin = $this->makeAdmin();
        $space = $this->makeSpace('Medical Confab 1');

        $res = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => Carbon::now($tz),
        ]);

        // Taken slot for the target edit time.
        $other = $this->makeStudent();
        Reservation::create([
            'user_id' => $other->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-13 11:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-13 12:00:00', $tz),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
        ]);

        Sanctum::actingAs($user);
        $resp = $this->patchJson("/api/reservations/{$res->id}", [
            'space_id' => $space->id,
            'start_at' => '2026-04-13T11:00:00+08:00',
            'end_at' => '2026-04-13T12:00:00+08:00',
        ]);
        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['slot']);

        // Successful edit to a free slot should reappear in admin queue (pending_approval).
        $ok = $this->patchJson("/api/reservations/{$res->id}", [
            'space_id' => $space->id,
            'start_at' => '2026-04-13T10:00:00+08:00',
            'end_at' => '2026-04-13T11:00:00+08:00',
        ]);
        $ok->assertOk();

        Sanctum::actingAs($admin);
        $queue = $this->getJson('/api/admin/reservations?status=pending_approval&per_page=50');
        $queue->assertOk();
        $ids = collect($queue->json('data'))->pluck('id')->all();
        $this->assertContains($res->id, $ids);
    }

    public function test_patch_rejects_invalid_start_minute_and_does_not_change_stored_times(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace('Study Room');

        $res = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Study',
        ]);

        $resp = $this->patchJson("/api/reservations/{$res->id}", [
            'space_id' => $space->id,
            'start_at' => '2026-04-13T09:15:00+08:00',
            'end_at' => '2026-04-13T10:00:00+08:00',
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['start_at']);

        $fresh = Reservation::find($res->id);
        $this->assertNotNull($fresh);
        $this->assertSame('2026-04-12 09:00:00', $fresh->start_at->timezone($tz)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-12 10:00:00', $fresh->end_at->timezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_patch_rejects_invalid_end_minute_45(): void
    {
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace('Reading Room');

        $res = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-04-12 09:00:00', $tz),
            'end_at' => Carbon::parse('2026-04-12 10:00:00', $tz),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
        ]);

        $resp = $this->patchJson("/api/reservations/{$res->id}", [
            'space_id' => $space->id,
            'start_at' => '2026-04-13T09:00:00+08:00',
            'end_at' => '2026-04-13T10:45:00+08:00',
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['end_at']);
    }
}

