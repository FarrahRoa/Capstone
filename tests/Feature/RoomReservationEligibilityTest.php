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

class RoomReservationEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithReservationCreate(array $extra = []): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test role']
        );

        return User::factory()->create(array_merge([
            'role_id' => $role->id,
            'is_activated' => true,
        ], $extra));
    }

    private function reservationPayload(int $spaceId): array
    {
        return [
            'space_id' => $spaceId,
            'start_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Test',
        ];
    }

    public function test_eligible_med_user_can_reserve_med_confab(): void
    {
        Mail::fake();

        $user = $this->makeUserWithReservationCreate(['med_confab_eligible' => true]);
        Sanctum::actingAs($user);

        $space = Space::create([
            'name' => 'Medical Confab 1',
            'slug' => 'medical-confab-1',
            'type' => Space::TYPE_MEDICAL_CONFAB,
            'capacity' => 20,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDay()->setTime(9, 30)->toDateTimeString(),
            'event_title' => 'Med Confab',
            'participant_count' => 5,
        ]);

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_non_med_user_cannot_reserve_med_confab(): void
    {
        Mail::fake();

        $user = $this->makeUserWithReservationCreate(['med_confab_eligible' => false]);
        Sanctum::actingAs($user);

        $space = Space::create([
            'name' => 'Medical Confab 1',
            'slug' => 'medical-confab-1',
            'type' => Space::TYPE_MEDICAL_CONFAB,
            'capacity' => 20,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', $this->reservationPayload($space->id));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['space_id']);
        $response->assertJsonFragment([
            'space_id' => ['Only eligible med users can reserve Med Confab.'],
        ]);
        Mail::assertNothingSent();
    }

    public function test_eligible_oop_user_can_reserve_boardroom(): void
    {
        Mail::fake();

        $user = $this->makeUserWithReservationCreate([
            'boardroom_eligible' => true,
            'college_office' => 'Office of the President',
        ]);
        Sanctum::actingAs($user);

        $space = Space::create([
            'name' => 'Boardroom',
            'slug' => 'boardroom',
            'type' => Space::TYPE_BOARDROOM,
            'capacity' => 12,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', $this->reservationPayload($space->id));

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_eligible_ovp_higher_education_user_can_reserve_boardroom(): void
    {
        Mail::fake();

        $user = $this->makeUserWithReservationCreate([
            'boardroom_eligible' => false,
            'college_office' => 'Office of the Vice-President Higher Education',
        ]);
        Sanctum::actingAs($user);

        $space = Space::create([
            'name' => 'Boardroom',
            'slug' => 'boardroom',
            'type' => Space::TYPE_BOARDROOM,
            'capacity' => 12,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', $this->reservationPayload($space->id));

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_unrelated_office_cannot_reserve_boardroom_even_if_flag_is_true(): void
    {
        Mail::fake();

        $user = $this->makeUserWithReservationCreate([
            'boardroom_eligible' => true,
            'college_office' => 'Finance',
        ]);
        Sanctum::actingAs($user);

        $space = Space::create([
            'name' => 'Boardroom',
            'slug' => 'boardroom',
            'type' => Space::TYPE_BOARDROOM,
            'capacity' => 12,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', $this->reservationPayload($space->id));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['space_id']);
        $response->assertJsonFragment([
            'space_id' => ['Only authorized Office of the President and Office of the Vice-President Higher Education users can reserve Boardroom.'],
        ]);
        Mail::assertNothingSent();
    }

    public function test_unrestricted_room_works_for_permitted_user(): void
    {
        Mail::fake();

        $user = $this->makeUserWithReservationCreate([
            'med_confab_eligible' => false,
            'boardroom_eligible' => false,
        ]);
        Sanctum::actingAs($user);

        $space = Space::create([
            'name' => 'AVR',
            'slug' => 'room-a',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', $this->reservationPayload($space->id));

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }
}
