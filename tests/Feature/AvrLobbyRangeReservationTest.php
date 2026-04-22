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

class AvrLobbyRangeReservationTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): User
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);
        return User::factory()->create(['role_id' => $role->id, 'is_activated' => true]);
    }

    private function makeSpace(string $slug, string $type, string $name): Space
    {
        return Space::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    public function test_avr_accepts_datetime_range_with_30_minute_alignment_and_saves_event_fields(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $avr = $this->makeSpace('avr', 'avr', 'AVR');

        $resp = $this->postJson('/api/reservations', [
            'space_id' => $avr->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 30)->toDateTimeString(),
            'event_title' => 'AVR Event',
            'event_description' => 'Notes here',
            'participant_count' => 50,
        ]);

        $resp->assertStatus(201);
        $this->assertDatabaseHas('reservations', [
            'space_id' => $avr->id,
            'event_title' => 'AVR Event',
            'event_description' => 'Notes here',
            'participant_count' => 50,
            'status' => Reservation::STATUS_EMAIL_VERIFICATION_PENDING,
        ]);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_lobby_rejects_minutes_not_00_or_30(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $lobby = $this->makeSpace('lobby', 'lobby', 'Lobby');

        $resp = $this->postJson('/api/reservations', [
            'space_id' => $lobby->id,
            'start_at' => now()->addDays(2)->setTime(9, 15)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'event_title' => 'Lobby Event',
            'participant_count' => 10,
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['start_at']);
    }

    public function test_end_before_start_is_rejected_for_avr(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $avr = $this->makeSpace('avr', 'avr', 'AVR');

        $resp = $this->postJson('/api/reservations', [
            'space_id' => $avr->id,
            'start_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(9, 30)->toDateTimeString(),
            'event_title' => 'Bad AVR Event',
            'participant_count' => 1,
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['end_at']);
    }
}

