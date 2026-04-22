<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoPastDateReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_rejects_reservation_for_past_civil_date_in_app_timezone(): void
    {
        Mail::fake();

        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test role']
        );
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
        Sanctum::actingAs($user);

        $space = Space::create([
            'name' => 'Past Date Room',
            'slug' => 'past-date-room-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', $tz));

        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2026-04-09T09:00:00+08:00',
            'end_at' => '2026-04-09T10:00:00+08:00',
            'purpose' => 'Should be rejected',
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['start_at']);
    }
}

