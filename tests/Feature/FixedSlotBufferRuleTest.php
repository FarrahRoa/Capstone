<?php

namespace Tests\Feature;

use App\Mail\ReservationVerificationMail;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FixedSlotBufferRuleTest extends TestCase
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

    public function test_reservation_accepts_half_hour_start_and_end_minutes(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace();

        $day = now()->addDays(2)->startOfDay();
        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(9, 0)->toDateTimeString(),
            'end_at' => $day->copy()->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Test',
        ]);

        $resp->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_reservation_accepts_thirty_minute_start(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace();

        $day = now()->addDays(2)->startOfDay();
        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(9, 30)->toDateTimeString(),
            'end_at' => $day->copy()->setTime(10, 30)->toDateTimeString(),
            'purpose' => 'Test',
        ]);

        $resp->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_reservation_rejects_start_minute_15(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace();

        $day = now()->addDays(2)->startOfDay();
        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(9, 15)->toDateTimeString(),
            'end_at' => $day->copy()->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Test',
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['start_at']);
        Mail::assertNothingSent();
    }

    public function test_reservation_rejects_end_minute_45(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace();

        $day = now()->addDays(2)->startOfDay();
        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(9, 0)->toDateTimeString(),
            'end_at' => $day->copy()->setTime(10, 45)->toDateTimeString(),
            'purpose' => 'Test',
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['end_at']);
        Mail::assertNothingSent();
    }

    public function test_reservation_rejects_mixed_invalid_minutes(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = $this->makeSpace();

        $day = now()->addDays(2)->startOfDay();
        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(9, 30)->toDateTimeString(),
            'end_at' => $day->copy()->setTime(10, 15)->toDateTimeString(),
            'purpose' => 'Test',
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['end_at']);
        Mail::assertNothingSent();
    }

    public function test_standard_boardroom_reservation_rejects_quarter_hour_start(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);
        $space = Space::create([
            'name' => 'Boardroom',
            'slug' => 'boardroom-half-hour-'.uniqid(),
            'type' => Space::TYPE_BOARDROOM,
            'capacity' => 12,
            'is_active' => true,
        ]);

        $day = now()->addDays(2)->startOfDay();
        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => $day->copy()->setTime(14, 45)->toDateTimeString(),
            'end_at' => $day->copy()->setTime(15, 30)->toDateTimeString(),
            'purpose' => 'Meeting',
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['start_at']);
        Mail::assertNothingSent();
    }
}
