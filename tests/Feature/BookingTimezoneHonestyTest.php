<?php

namespace Tests\Feature;

use App\Mail\ReservationVerificationMail;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTimezoneHonestyTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_timezone_is_asia_manila_by_default(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
    }

    public function test_availability_day_boundary_matches_app_timezone(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test role']
        );
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);

        $space = Space::create([
            'name' => 'TZ Test Room',
            'slug' => 'tz-test-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $start = Carbon::parse('2026-08-12 09:00:00', config('app.timezone'));
        $end = Carbon::parse('2026-08-12 10:00:00', config('app.timezone'));

        Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Timezone honesty test',
        ]);

        $response = $this->getJson('/api/availability?date=2026-08-12&space_id='.$space->id);
        $response->assertStatus(200);

        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $match = collect($rows)->first(fn ($row) => (int) ($row['space']['id'] ?? 0) === $space->id);
        $this->assertNotNull($match);
        $this->assertNotEmpty($match['reserved_slots']);
        $this->assertSame(1, count($match['reserved_slots']));
    }

    public function test_reservation_create_accepts_explicit_manila_offset_strings(): void
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
            'name' => 'Offset Payload Room',
            'slug' => 'offset-payload-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2028-06-01T09:00:00+08:00',
            'end_at' => '2028-06-01T10:00:00+08:00',
            'purpose' => 'Manila offset payload',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'message' => 'Reservation created. Please confirm your email using the link sent to your XU email.',
        ]);

        $this->assertDatabaseHas('reservations', [
            'space_id' => $space->id,
            'user_id' => $user->id,
        ]);

        $row = Reservation::where('space_id', $space->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(
            '2028-06-01 09:00:00',
            Carbon::parse($row->start_at, config('app.timezone'))->format('Y-m-d H:i:s')
        );

        Mail::assertSent(ReservationVerificationMail::class);
    }
}
