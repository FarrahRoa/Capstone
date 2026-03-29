<?php

namespace Tests\Feature;

use App\Mail\ReservationApprovedMail;
use App\Mail\ReservationRejectedMail;
use App\Mail\ReservationVerificationMail;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationAuditTrailTest extends TestCase
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

    private function makeSpace(): Space
    {
        $slug = 'avr-' . Str::lower(Str::random(8));
        return Space::create([
            'name' => 'AVR',
            'slug' => $slug,
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    private function makePendingReservation(User $requester): Reservation
    {
        $reservation = Reservation::create([
            'user_id' => $requester->id,
            'space_id' => $this->makeSpace()->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Audit trail test',
        ]);
        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'admin_id' => $requester->id,
            'action' => 'create',
        ]);

        return $reservation;
    }

    public function test_history_entry_is_created_on_reservation_create(): void
    {
        Mail::fake();

        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);
        $space = $this->makeSpace();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Initial reservation',
        ]);

        $response->assertStatus(201);
        $reservationId = $response->json('reservation.id');

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservationId,
            'admin_id' => $student->id,
            'action' => 'create',
        ]);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_history_entry_is_created_on_approve(): void
    {
        Mail::fake();

        $operator = $this->makeUserWithRole('student_assistant', 'Student Assistant');
        $requester = $this->makeUserWithRole('student', 'Student');
        $reservation = $this->makePendingReservation($requester);
        Sanctum::actingAs($operator);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/approve", [
            'notes' => 'Approved for use',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'admin_id' => $operator->id,
            'action' => 'approve',
            'notes' => 'Approved for use',
        ]);
        Mail::assertSent(ReservationApprovedMail::class);
    }

    public function test_history_entry_is_created_on_reject_with_reason(): void
    {
        Mail::fake();

        $operator = $this->makeUserWithRole('librarian', 'Librarian');
        $requester = $this->makeUserWithRole('student', 'Student');
        $reservation = $this->makePendingReservation($requester);
        Sanctum::actingAs($operator);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/reject", [
            'reason' => 'Room unavailable for maintenance.',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'admin_id' => $operator->id,
            'action' => 'reject',
            'notes' => 'Room unavailable for maintenance.',
        ]);
        Mail::assertSent(ReservationRejectedMail::class);
    }

    public function test_history_entry_is_created_on_cancel_and_override(): void
    {
        Mail::fake();

        $admin = $this->makeUserWithRole('admin', 'Admin');
        $requester = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($admin);

        $overrideReservation = $this->makePendingReservation($requester);
        $cancelReservation = $this->makePendingReservation($requester);
        $cancelReservation->update(['status' => Reservation::STATUS_APPROVED]);

        $overrideResponse = $this->postJson("/api/admin/reservations/{$overrideReservation->id}/override", [
            'notes' => 'Manual override approved',
        ]);
        $overrideResponse->assertStatus(200);

        $cancelResponse = $this->postJson("/api/admin/reservations/{$cancelReservation->id}/cancel", [
            'notes' => 'Cancelled by admin for conflict',
        ]);
        $cancelResponse->assertStatus(200);

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $overrideReservation->id,
            'admin_id' => $admin->id,
            'action' => 'override',
            'notes' => 'Manual override approved',
        ]);
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $cancelReservation->id,
            'admin_id' => $admin->id,
            'action' => 'cancel',
            'notes' => 'Cancelled by admin for conflict',
        ]);
    }

    public function test_unauthorized_user_cannot_access_other_users_reservation_history(): void
    {
        $owner = $this->makeUserWithRole('student', 'Student');
        $other = $this->makeUserWithRole('student', 'Student');

        $reservation = $this->makePendingReservation($owner);

        Sanctum::actingAs($owner);
        $ownerResponse = $this->getJson("/api/reservations/{$reservation->id}");
        $ownerResponse->assertStatus(200);
        $ownerResponse->assertJsonPath('logs.0.action', 'create');

        Sanctum::actingAs($other);
        $otherResponse = $this->getJson("/api/reservations/{$reservation->id}");
        $otherResponse->assertStatus(403);
    }

    public function test_authorized_queue_operator_can_access_reservation_history(): void
    {
        $owner = $this->makeUserWithRole('student', 'Student');
        $operator = $this->makeUserWithRole('student_assistant', 'Student Assistant');
        $reservation = $this->makePendingReservation($owner);

        Sanctum::actingAs($operator);
        $response = $this->getJson("/api/admin/reservations/{$reservation->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('logs.0.action', 'create');
    }
}
