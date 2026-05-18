<?php

namespace Tests\Feature;

use App\Mail\ReservationVerificationMail;
use App\Models\DeanEmailMapping;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Support\StudentSpaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvrLobbyEventRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSacdevDeanMappingForTests();
    }

    private function student(): User
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'college_office' => 'College of Computer Studies',
        ]);
    }

    private function faculty(): User
    {
        $role = Role::firstOrCreate(['slug' => 'faculty'], ['name' => 'Faculty', 'description' => 'Test']);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'email' => 'faculty-'.uniqid().'@xu.edu.ph',
        ]);
    }

    private function avrSpace(): Space
    {
        return Space::create([
            'name' => 'AVR',
            'slug' => 'avr-'.uniqid(),
            'type' => 'avr',
            'capacity' => 70,
            'is_active' => true,
        ]);
    }

    private function lobbySpace(): Space
    {
        return Space::create([
            'name' => 'Lobby',
            'slug' => 'lobby-'.uniqid(),
            'type' => 'lobby',
            'capacity' => 40,
            'is_active' => true,
        ]);
    }

    public function test_student_cannot_reserve_avr_even_with_organization_audience(): void
    {
        $user = $this->student();
        Sanctum::actingAs($user);
        $space = $this->avrSpace();

        $resp = $this->postJson('/api/reservations', array_merge([
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'event_title' => 'Test',
            'participant_count' => 5,
        ], $this->organizationEventAudiencePayload()));

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['space_id']);
        $this->assertSame(StudentSpaceAccess::CONFAB_ONLY_MESSAGE, $resp->json('errors.space_id.0'));
    }

    public function test_faculty_avr_requires_event_request_type(): void
    {
        Sanctum::actingAs($this->faculty());
        $space = $this->avrSpace();

        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'event_title' => 'Test',
            'participant_count' => 25,
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['event_request_type']);
    }

    public function test_faculty_avr_succeeds_with_organization_audience(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->faculty());
        $space = $this->avrSpace();

        $resp = $this->postJson('/api/reservations', array_merge([
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'event_title' => 'Test',
            'participant_count' => 25,
        ], $this->organizationEventAudiencePayload()));

        $resp->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_avr_create_fails_422_when_organization_chosen_but_sacdev_mapping_missing(): void
    {
        DeanEmailMapping::query()->delete();

        Sanctum::actingAs($this->faculty());
        $space = $this->avrSpace();

        $resp = $this->postJson('/api/reservations', array_merge([
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'event_title' => 'Test',
            'participant_count' => 5,
        ], $this->organizationEventAudiencePayload()));

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['event_request_type']);
    }

    public function test_avr_employee_event_requires_active_college_mapping(): void
    {
        $user = $this->faculty();
        Sanctum::actingAs($user);
        $space = $this->avrSpace();

        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'event_title' => 'Test',
            'participant_count' => 5,
            'event_request_type' => Reservation::EVENT_REQUEST_EMPLOYEE,
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['event_request_type']);
    }

    public function test_lobby_faculty_organization_event_succeeds(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->faculty());
        $space = $this->lobbySpace();

        $resp = $this->postJson('/api/reservations', array_merge([
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'event_title' => 'Lobby event',
            'participant_count' => 10,
        ], $this->organizationEventAudiencePayload()));

        $resp->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }
}
