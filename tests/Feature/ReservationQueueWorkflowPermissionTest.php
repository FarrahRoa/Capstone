<?php

namespace Tests\Feature;

use App\Mail\ReservationApprovedMail;
use App\Mail\ReservationRejectedMail;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationQueueWorkflowPermissionTest extends TestCase
{
    use RefreshDatabase;

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

    private function makePendingReservation(): Reservation
    {
        $requester = $this->makeUserWithRole('student', 'Student');

        $space = Space::create([
            'name' => 'Room A',
            'slug' => 'room-a',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        return Reservation::create([
            'user_id' => $requester->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Queue workflow test',
        ]);
    }

    private function makeEmailVerificationPendingReservation(): Reservation
    {
        $requester = $this->makeUserWithRole('student', 'Student');

        $space = Space::create([
            'name' => 'AVR',
            'slug' => 'avr-ev',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        return Reservation::create([
            'user_id' => $requester->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_EMAIL_VERIFICATION_PENDING,
            'purpose' => 'Awaiting email',
            'verification_token' => Str::random(64),
            'verification_expires_at' => now()->addHour(),
        ]);
    }

    public function test_librarian_can_reject_email_verification_pending_reservation(): void
    {
        Mail::fake();

        $librarian = $this->makeUserWithRole('librarian', 'Librarian');
        $reservation = $this->makeEmailVerificationPendingReservation();
        Sanctum::actingAs($librarian);

        $rejectResponse = $this->postJson("/api/admin/reservations/{$reservation->id}/reject", [
            'reason' => 'Invalid or duplicate booking request.',
        ]);

        $rejectResponse->assertStatus(200);
        $rejectResponse->assertJsonFragment([
            'message' => 'Reservation rejected.',
        ]);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_REJECTED,
        ]);
        Mail::assertSent(ReservationRejectedMail::class);
    }

    public function test_student_assistant_can_view_queue_and_approve(): void
    {
        Mail::fake();

        $assistant = $this->makeUserWithRole('student_assistant', 'Student Assistant');
        $reservation = $this->makePendingReservation();
        Sanctum::actingAs($assistant);

        $listResponse = $this->getJson('/api/admin/reservations?status=pending_approval');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonPath('data.0.id', $reservation->id);

        $approveResponse = $this->postJson("/api/admin/reservations/{$reservation->id}/approve", [
            'notes' => 'Approved by student assistant',
        ]);

        $approveResponse->assertStatus(200);
        $approveResponse->assertJsonFragment([
            'message' => 'Reservation approved.',
        ]);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_APPROVED,
            'approved_by' => $assistant->id,
        ]);
        Mail::assertSent(ReservationApprovedMail::class);
    }

    public function test_librarian_can_view_queue_and_reject(): void
    {
        Mail::fake();

        $librarian = $this->makeUserWithRole('librarian', 'Librarian');
        $reservation = $this->makePendingReservation();
        Sanctum::actingAs($librarian);

        $listResponse = $this->getJson('/api/admin/reservations?status=pending_approval');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonPath('data.0.id', $reservation->id);

        $rejectResponse = $this->postJson("/api/admin/reservations/{$reservation->id}/reject", [
            'reason' => 'Conflicts with room maintenance schedule.',
        ]);

        $rejectResponse->assertStatus(200);
        $rejectResponse->assertJsonFragment([
            'message' => 'Reservation rejected.',
        ]);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_REJECTED,
            'rejected_reason' => 'Conflicts with room maintenance schedule.',
        ]);
        Mail::assertSent(ReservationRejectedMail::class);
    }

    public function test_student_cannot_view_queue_or_approve_or_reject(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        $reservation = $this->makePendingReservation();
        Sanctum::actingAs($student);

        $this->getJson('/api/admin/reservations')->assertStatus(403);
        $this->postJson("/api/admin/reservations/{$reservation->id}/approve")->assertStatus(403);
        $this->postJson("/api/admin/reservations/{$reservation->id}/reject", [
            'reason' => 'No permission',
        ])->assertStatus(403);
    }
}
