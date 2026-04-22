<?php

namespace Tests\Feature;

use App\Mail\ReservationVerificationMail;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationValueIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_allowed_statuses_match_status_labels_and_workflow(): void
    {
        $allowed = Reservation::allowedStatuses();
        $this->assertSame(Reservation::workflowStatuses(), $allowed);
        $this->assertCount(5, $allowed);
        foreach ($allowed as $status) {
            $this->assertArrayHasKey($status, Reservation::STATUS_LABELS, 'Missing label for status: ' . $status);
        }
        $this->assertSame(array_keys(Reservation::STATUS_LABELS), $allowed);
    }

    public function test_reservation_log_allowed_actions_match_action_labels(): void
    {
        $actions = ReservationLog::allowedActions();
        $this->assertCount(5, $actions);
        foreach ($actions as $action) {
            $this->assertArrayHasKey($action, ReservationLog::ACTION_LABELS, 'Missing label for action: ' . $action);
        }
        $this->assertSame(array_keys(ReservationLog::ACTION_LABELS), $actions);
    }

    public function test_reservation_log_actor_types_are_defined(): void
    {
        $types = ReservationLog::allowedActorTypes();
        $this->assertEqualsCanonicalizing(
            [ReservationLog::ACTOR_USER, ReservationLog::ACTOR_ADMIN, ReservationLog::ACTOR_SYSTEM],
            $types
        );
    }

    public function test_reservation_persists_only_valid_status_via_eloquent(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test']
        );
        $user = User::factory()->create(['role_id' => $role->id, 'is_activated' => true]);
        $space = Space::create([
            'name' => 'Room A',
            'slug' => 'room-a-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => 'made_up_status',
            'purpose' => 'x',
        ]);
    }

    public function test_reservation_log_rejects_invalid_action(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test']
        );
        $user = User::factory()->create(['role_id' => $role->id, 'is_activated' => true]);
        $space = Space::create([
            'name' => 'Room B',
            'slug' => 'room-b-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
        $reservation = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'x',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'actor_user_id' => $user->id,
            'actor_type' => ReservationLog::ACTOR_USER,
            'action' => 'mystery_action',
            'notes' => null,
        ]);
    }

    public function test_admin_reservations_index_rejects_invalid_status_filter(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student_assistant'],
            ['name' => 'Student Assistant', 'description' => 'Test']
        );
        $user = User::factory()->create(['role_id' => $role->id, 'is_activated' => true]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/reservations?status=not_a_real_status');
        $response->assertStatus(422);
    }

    public function test_workflow_end_to_end_uses_only_allowed_statuses_and_log_actions(): void
    {
        Mail::fake();

        $studentRole = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test']
        );
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Test']
        );
        $student = User::factory()->create(['role_id' => $studentRole->id, 'is_activated' => true]);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_activated' => true]);
        $space = Space::create([
            'name' => 'Workflow Room',
            'slug' => 'wf-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        Sanctum::actingAs($student);
        $create = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => now()->addDays(3)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Integrity sweep',
        ]);
        $create->assertStatus(201);
        $reservationId = $create->json('data.id');
        $this->assertNotNull($reservationId);

        $r = Reservation::with('logs')->findOrFail($reservationId);
        $this->assertContains($r->status, Reservation::allowedStatuses());
        $this->assertSame(Reservation::STATUS_EMAIL_VERIFICATION_PENDING, $r->status);
        $this->assertCount(1, $r->logs);
        $this->assertSame(ReservationLog::ACTION_CREATE, $r->logs->first()->action);

        $token = $r->verification_token;
        $this->assertNotNull($token);

        $this->postJson('/api/reservations/confirm-email', ['token' => $token])->assertStatus(200);
        $r->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_APPROVAL, $r->status);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/reservations/{$r->id}/approve", ['notes' => 'ok'])->assertStatus(200);
        $r->refresh();
        $this->assertSame(Reservation::STATUS_APPROVED, $r->status);

        $actions = $r->logs()->orderBy('id')->pluck('action')->all();
        $this->assertSame(
            [ReservationLog::ACTION_CREATE, ReservationLog::ACTION_APPROVE],
            $actions
        );
        foreach ($actions as $a) {
            $this->assertContains($a, ReservationLog::allowedActions());
        }

        Mail::assertSent(ReservationVerificationMail::class);
    }
}
