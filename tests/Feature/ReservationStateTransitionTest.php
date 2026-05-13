<?php

namespace Tests\Feature;

use App\Mail\ReservationVerificationMail;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationStateTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSacdevDeanMappingForTests();
    }

    private function makeUserWithRole(string $slug, string $name): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => 'Test role']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function makeSpace(): Space
    {
        return Space::create([
            'name' => 'AVR',
            'slug' => 'avr-' . Str::lower(Str::random(6)),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        return $this->makeUserWithRole('admin', 'Admin');
    }

    private function makeReservation(string $status, array $extra = []): Reservation
    {
        $user = $this->makeUserWithRole('student', 'Student');
        $space = $this->makeSpace();

        return Reservation::create(array_merge([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => $status,
            'purpose' => 'State transition test',
            'event_request_type' => Reservation::EVENT_REQUEST_ORGANIZATION,
        ], $extra));
    }

    public function test_create_sets_initial_status_email_verification_pending(): void
    {
        Mail::fake();

        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);
        $space = $this->makeSpace();

        $response = $this->postJson('/api/reservations', array_merge([
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'New booking',
        ], $this->organizationEventAudiencePayload()));

        $response->assertStatus(201);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('reservations', [
            'id' => $id,
            'status' => Reservation::initialCreateStatus(),
        ]);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_confirm_email_avr_transitions_to_pending_dean_when_mapped(): void
    {
        Mail::fake();

        $reservation = $this->makeReservation(Reservation::STATUS_EMAIL_VERIFICATION_PENDING, [
            'verification_token' => Str::random(64),
            'verification_expires_at' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/reservations/confirm-email', [
            'token' => $reservation->verification_token,
        ]);

        $response->assertStatus(200);
        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_DEAN_APPROVAL, $reservation->status);
        $this->assertNotNull($reservation->verified_at);
    }

    public function test_confirm_email_non_avr_transitions_to_pending_approval(): void
    {
        Mail::fake();

        $user = $this->makeUserWithRole('student', 'Student');
        $space = Space::create([
            'name' => 'Lecture',
            'slug' => 'lec-'.Str::lower(Str::random(6)),
            'type' => 'lecture',
            'capacity' => 10,
            'is_active' => true,
        ]);
        $reservation = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_EMAIL_VERIFICATION_PENDING,
            'purpose' => 'State transition test',
            'verification_token' => Str::random(64),
            'verification_expires_at' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/reservations/confirm-email', [
            'token' => $reservation->verification_token,
        ]);

        $response->assertStatus(200);
        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_APPROVAL, $reservation->status);
    }

    public function test_confirm_email_expired_transitions_to_rejected(): void
    {
        Mail::fake();

        $reservation = $this->makeReservation(Reservation::STATUS_EMAIL_VERIFICATION_PENDING, [
            'verification_token' => Str::random(64),
            'verification_expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/reservations/confirm-email', [
            'token' => $reservation->verification_token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Confirmation link has expired.']);
        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_REJECTED, $reservation->status);
    }

    public function test_approve_only_from_pending_approval(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $deanPending = $this->makeReservation(Reservation::STATUS_PENDING_DEAN_APPROVAL);
        $deanBlock = $this->postJson("/api/admin/reservations/{$deanPending->id}/approve", []);
        $deanBlock->assertStatus(422);
        $deanBlock->assertJsonFragment(['message' => 'This reservation is awaiting dean/office approval. Library staff cannot approve it until that step is complete.']);

        $pending = $this->makeReservation(Reservation::STATUS_PENDING_APPROVAL);
        $ok = $this->postJson("/api/admin/reservations/{$pending->id}/approve", []);
        $ok->assertStatus(200);
        $pending->refresh();
        $this->assertSame(Reservation::STATUS_APPROVED, $pending->status);

        $approved = $this->makeReservation(Reservation::STATUS_APPROVED);
        $fail = $this->postJson("/api/admin/reservations/{$approved->id}/approve", []);
        $fail->assertStatus(422);
        $fail->assertJsonFragment(['message' => 'Reservation is not pending approval.']);
    }

    public function test_reject_only_from_pending_or_email_verification_pending(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $deanPending = $this->makeReservation(Reservation::STATUS_PENDING_DEAN_APPROVAL);
        $deanBlock = $this->postJson("/api/admin/reservations/{$deanPending->id}/reject", [
            'reason' => 'Should not apply while dean pending.',
        ]);
        $deanBlock->assertStatus(422);
        $deanBlock->assertJsonFragment(['message' => 'This reservation is awaiting dean/office approval. Library staff cannot reject it until that step is complete.']);
        $deanPending->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_DEAN_APPROVAL, $deanPending->status);

        foreach ([Reservation::STATUS_PENDING_APPROVAL, Reservation::STATUS_EMAIL_VERIFICATION_PENDING] as $status) {
            $r = $this->makeReservation($status);
            $response = $this->postJson("/api/admin/reservations/{$r->id}/reject", [
                'reason' => 'Not available for this test.',
            ]);
            $response->assertStatus(200);
            $r->refresh();
            $this->assertSame(Reservation::STATUS_REJECTED, $r->status);
        }

        $approved = $this->makeReservation(Reservation::STATUS_APPROVED);
        $fail = $this->postJson("/api/admin/reservations/{$approved->id}/reject", [
            'reason' => 'Cannot reject an approved reservation.',
        ]);
        $fail->assertStatus(422);
        $fail->assertJsonFragment(['message' => 'Reservation cannot be rejected.']);
        $approved->refresh();
        $this->assertSame(Reservation::STATUS_APPROVED, $approved->status);
    }

    public function test_reject_fails_when_already_rejected(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $rejected = $this->makeReservation(Reservation::STATUS_REJECTED);
        $fail = $this->postJson("/api/admin/reservations/{$rejected->id}/reject", [
            'reason' => 'Trying to reject again.',
        ]);
        $fail->assertStatus(422);
        $fail->assertJsonFragment(['message' => 'Reservation cannot be rejected.']);
    }

    public function test_cancel_allowed_until_already_cancelled_including_from_rejected(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $rejected = $this->makeReservation(Reservation::STATUS_REJECTED);
        $response = $this->postJson("/api/admin/reservations/{$rejected->id}/cancel", []);
        $response->assertStatus(200);
        $rejected->refresh();
        $this->assertSame(Reservation::STATUS_CANCELLED, $rejected->status);

        $fail = $this->postJson("/api/admin/reservations/{$rejected->id}/cancel", []);
        $fail->assertStatus(422);
        $fail->assertJsonFragment(['message' => 'Already cancelled.']);
    }

    public function test_override_matches_approve_requires_pending_approval(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $pending = $this->makeReservation(Reservation::STATUS_PENDING_APPROVAL);
        $target = $this->makeSpace();
        $ok = $this->postJson("/api/admin/reservations/{$pending->id}/override", [
            'reason' => 'Override ok',
            'space_id' => $target->id,
            'start_at' => $pending->start_at->toIso8601String(),
            'end_at' => $pending->end_at->toIso8601String(),
        ]);
        $ok->assertStatus(200);
        $pending->refresh();
        $this->assertSame(Reservation::STATUS_OVERRIDDEN, $pending->status);

        $rejected = $this->makeReservation(Reservation::STATUS_REJECTED);
        $fail = $this->postJson("/api/admin/reservations/{$rejected->id}/override", [
            'reason' => 'Nope',
            'space_id' => $this->makeSpace()->id,
            'start_at' => $rejected->start_at->toIso8601String(),
            'end_at' => $rejected->end_at->toIso8601String(),
        ]);
        $fail->assertStatus(422);
    }

    public function test_blocking_statuses_are_subset_of_workflow_and_hold_slot(): void
    {
        $blocking = Reservation::blockingStatuses();
        $workflow = Reservation::workflowStatuses();

        $this->assertSame(
            [
                Reservation::STATUS_APPROVED,
                Reservation::STATUS_OVERRIDDEN,
                Reservation::STATUS_PENDING_APPROVAL,
                Reservation::STATUS_PENDING_DEAN_APPROVAL,
                Reservation::STATUS_EMAIL_VERIFICATION_PENDING,
            ],
            $blocking
        );

        foreach ($blocking as $status) {
            $this->assertContains($status, $workflow);
            $this->assertNotSame(Reservation::STATUS_REJECTED, $status);
            $this->assertNotSame(Reservation::STATUS_CANCELLED, $status);
        }
    }

    public function test_allowed_transitions_map_is_complete_for_workflow_statuses(): void
    {
        $map = Reservation::allowedTransitions();
        foreach (Reservation::workflowStatuses() as $status) {
            $this->assertArrayHasKey($status, $map, "Missing transition row for status: {$status}");
        }
    }
}
