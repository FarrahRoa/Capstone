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

class ConfabAssignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): User
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

    private function makeAdmin(): User
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

    /**
     * @return array{pool: Space, room: Space}
     */
    private function poolAndAssignableRoom(): array
    {
        $pool = Space::query()->firstWhere('is_confab_pool', true);
        $this->assertNotNull($pool, 'Migration should create confab assignment pool space.');

        $room = Space::create([
            'name' => 'Confab Test Room',
            'slug' => 'confab-test-room-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 8,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        return ['pool' => $pool, 'room' => $room];
    }

    public function test_student_can_create_pending_reservation_on_confab_pool_without_physical_room(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        ['pool' => $pool] = $this->poolAndAssignableRoom();

        $start = now()->addDays(2)->setTime(10, 0)->toDateTimeString();
        $end = now()->addDays(2)->setTime(11, 0)->toDateTimeString();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $pool->id,
            'start_at' => $start,
            'end_at' => $end,
            'purpose' => 'Confab pool request',
            'event_title' => 'Pool request',
            'participant_count' => 8,
        ]);

        $response->assertStatus(201);
        $id = $response->json('data.id');
        $this->assertNotNull($id);
        $row = Reservation::findOrFail($id);
        $this->assertSame($pool->id, (int) $row->space_id);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_student_cannot_reserve_specific_confab_room_directly(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        ['room' => $room] = $this->poolAndAssignableRoom();

        $start = now()->addDays(2)->setTime(12, 0)->toDateTimeString();
        $end = now()->addDays(2)->setTime(13, 0)->toDateTimeString();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $room->id,
            'start_at' => $start,
            'end_at' => $end,
            'purpose' => 'Should fail',
            'event_title' => 'Direct room',
            'participant_count' => 3,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['space_id']);
        Mail::assertNothingSent();
    }

    public function test_admin_cannot_approve_confab_pool_reservation_without_assigned_room(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        ['pool' => $pool] = $this->poolAndAssignableRoom();
        $user = $this->makeStudent();

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $pool->id,
            'start_at' => now()->addDays(3)->setTime(14, 0),
            'end_at' => now()->addDays(3)->setTime(15, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Queued confab',
        ]);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/approve", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assigned_space_id']);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_APPROVAL, $reservation->status);
        $this->assertSame($pool->id, (int) $reservation->space_id);
        Mail::assertNothingSent();
    }

    public function test_admin_can_approve_confab_pool_with_free_assignable_room(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        ['pool' => $pool, 'room' => $room] = $this->poolAndAssignableRoom();
        $user = $this->makeStudent();

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $pool->id,
            'start_at' => now()->addDays(4)->setTime(9, 0),
            'end_at' => now()->addDays(4)->setTime(10, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Queued confab',
        ]);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/approve", [
            'assigned_space_id' => $room->id,
        ]);

        $response->assertStatus(200);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_APPROVED, $reservation->status);
        $this->assertSame($room->id, (int) $reservation->space_id);
        $this->assertNotNull($reservation->reservation_number);

        Mail::assertSent(\App\Mail\ReservationApprovedMail::class);
    }

    public function test_admin_cannot_assign_confab_room_that_is_already_booked(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        ['pool' => $pool, 'room' => $room] = $this->poolAndAssignableRoom();
        $user = $this->makeStudent();
        $other = $this->makeStudent();

        $blockingStart = now()->addDays(5)->setTime(11, 0);
        $blockingEnd = now()->addDays(5)->setTime(12, 0);

        Reservation::create([
            'user_id' => $other->id,
            'space_id' => $room->id,
            'start_at' => $blockingStart,
            'end_at' => $blockingEnd,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Blocks slot',
            'reservation_number' => 'RES-TESTBLOCK',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $pending = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $pool->id,
            'start_at' => $blockingStart,
            'end_at' => $blockingEnd,
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Wants room',
        ]);

        $response = $this->postJson("/api/admin/reservations/{$pending->id}/approve", [
            'assigned_space_id' => $room->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assigned_space_id']);

        $pending->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_APPROVAL, $pending->status);
        $this->assertSame($pool->id, (int) $pending->space_id);
        Mail::assertNothingSent();
    }

    public function test_assignable_confab_spaces_endpoint_returns_only_free_rooms(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        ['pool' => $pool, 'room' => $room] = $this->poolAndAssignableRoom();
        $user = $this->makeStudent();

        $start = now()->addDays(6)->setTime(15, 0);
        $end = now()->addDays(6)->setTime(16, 0);

        $pending = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $pool->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Test',
        ]);

        $response = $this->getJson("/api/admin/reservations/{$pending->id}/assignable-confab-spaces");
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($room->id, $ids);

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $room->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Block',
            'reservation_number' => 'RES-BLOCK2',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $response2 = $this->getJson("/api/admin/reservations/{$pending->id}/assignable-confab-spaces");
        $response2->assertStatus(200);
        $ids2 = collect($response2->json('data'))->pluck('id')->all();
        $this->assertNotContains($room->id, $ids2);
    }

    public function test_non_confab_approve_still_works_without_assigned_space_id(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $avr = Space::create([
            'name' => 'AVR X',
            'slug' => 'avr-x-'.uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);
        $user = $this->makeStudent();

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $avr->id,
            'start_at' => now()->addDays(7)->setTime(9, 0),
            'end_at' => now()->addDays(7)->setTime(10, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'AVR',
        ]);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/approve", []);

        $response->assertStatus(200);
        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_APPROVED, $reservation->status);
        $this->assertSame($avr->id, (int) $reservation->space_id);
    }
}
