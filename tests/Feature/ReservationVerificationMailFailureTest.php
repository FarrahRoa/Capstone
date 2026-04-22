<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationVerificationMailFailureTest extends TestCase
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

    private function makeSpace(): Space
    {
        return Space::create([
            'name' => 'Room A',
            'slug' => 'room-a',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    public function test_reservation_create_returns_503_and_does_not_persist_when_verification_mail_send_throws(): void
    {
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace();

        // Force Mail::to(...)->send(...) to throw.
        Mail::shouldReceive('to')->once()->with($user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP down'));

        $day = now()->addDay()->startOfDay();

        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(9, 0)->toDateTimeString(),
            'end_at' => $day->copy()->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Test',
        ]);

        $resp->assertStatus(503);
        $resp->assertJsonFragment([
            'message' => 'Reservation could not be completed because we could not send the verification email. Check mail configuration or try again shortly.',
        ]);

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('reservation_logs', 0);
    }
}

