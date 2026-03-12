<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReservationActionValidationTest extends TestCase
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

    public function test_approve_without_notes_passes(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_PENDING_APPROVAL);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/approve", []);

        $response->assertStatus(200);
    }

    public function test_approve_with_notes_over_500_fails_422(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_PENDING_APPROVAL);

        $longNotes = str_repeat('a', 501);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/approve", [
            'notes' => $longNotes,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('notes');
    }

    public function test_reject_without_reason_fails_422(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_PENDING_APPROVAL);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/reject", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('reason');
    }

    public function test_reject_with_short_reason_fails_422(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_PENDING_APPROVAL);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/reject", [
            'reason' => 'no',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('reason');
    }

    public function test_cancel_without_notes_passes(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_APPROVED);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/cancel", []);

        $response->assertStatus(200);
    }

    public function test_cancel_with_notes_over_500_fails_422(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_APPROVED);

        $longNotes = str_repeat('a', 501);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/cancel", [
            'notes' => $longNotes,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('notes');
    }
}

