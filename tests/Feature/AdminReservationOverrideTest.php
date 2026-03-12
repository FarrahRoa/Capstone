<?php

namespace Tests\Feature;

use App\Mail\ReservationApprovedMail;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReservationOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): User
    {
        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Test role',
        ]);

        return User::factory()->create([
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);
    }

    private function makeSpace(): Space
    {
        return Space::create([
            'name' => 'AVR',
            'slug' => 'avr',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    private function makeReservation(string $status): Reservation
    {
        $role = Role::create([
            'name' => 'Student',
            'slug' => 'student',
            'description' => 'Test role',
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);

        $space = $this->makeSpace();

        return Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => $status,
            'purpose' => 'Test reservation',
        ]);
    }

    public function test_override_on_pending_approval_succeeds_and_sends_approval_mail(): void
    {
        Mail::fake();

        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_PENDING_APPROVAL);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/override", [
            'notes' => 'Approved via override',
        ]);

        $response->assertStatus(200);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_APPROVED, $reservation->status);
        $this->assertNotNull($reservation->reservation_number);
        $this->assertSame($admin->id, $reservation->approved_by);
        $this->assertNotNull($reservation->approved_at);

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'admin_id' => $admin->id,
            'action' => 'override',
            'notes' => 'Approved via override',
        ]);

        Mail::assertSent(ReservationApprovedMail::class, function (ReservationApprovedMail $mail) use ($reservation) {
            return $mail->hasTo($reservation->user->email);
        });
    }

    public function test_override_on_rejected_returns_422_and_sends_no_mail(): void
    {
        Mail::fake();

        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_REJECTED);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/override", [
            'notes' => 'Attempt override',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Reservation is not pending approval.']);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_REJECTED, $reservation->status);

        $this->assertDatabaseMissing('reservation_logs', [
            'reservation_id' => $reservation->id,
            'action' => 'override',
        ]);

        Mail::assertNothingSent();
    }

    public function test_override_on_cancelled_returns_422_and_sends_no_mail(): void
    {
        Mail::fake();

        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_CANCELLED);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/override", [
            'notes' => 'Attempt override',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Reservation is not pending approval.']);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_CANCELLED, $reservation->status);

        $this->assertDatabaseMissing('reservation_logs', [
            'reservation_id' => $reservation->id,
            'action' => 'override',
        ]);

        Mail::assertNothingSent();
    }

    public function test_override_on_email_verification_pending_returns_422_and_sends_no_mail(): void
    {
        Mail::fake();

        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_EMAIL_VERIFICATION_PENDING);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/override", [
            'notes' => 'Attempt override',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Reservation is not pending approval.']);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_EMAIL_VERIFICATION_PENDING, $reservation->status);

        $this->assertDatabaseMissing('reservation_logs', [
            'reservation_id' => $reservation->id,
            'action' => 'override',
        ]);

        Mail::assertNothingSent();
    }
}

