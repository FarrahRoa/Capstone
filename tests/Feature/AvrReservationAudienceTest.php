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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvrReservationAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-05-16 10:00:00', 'Asia/Manila'));
        $this->seedSacdevDeanMappingForTests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function facultyUser(array $extra = []): User
    {
        $role = Role::firstOrCreate(['slug' => 'faculty'], ['name' => 'Faculty', 'description' => 'Test']);

        return User::factory()->create(array_merge([
            'role_id' => $role->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'email' => 'faculty-'.uniqid().'@xu.edu.ph',
            'college_office' => 'College of Engineering',
        ], $extra));
    }

    private function studentUser(): User
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_STUDENT,
            'email' => 'student-'.uniqid().'@my.xu.edu.ph',
        ]);
    }

    private function avrSpace(int $capacity = 70): Space
    {
        return Space::create([
            'name' => 'AVR',
            'slug' => 'avr-'.uniqid(),
            'type' => Space::TYPE_AVR,
            'capacity' => $capacity,
            'is_active' => true,
        ]);
    }

    private function lobbySpace(int $capacity = 40): Space
    {
        return Space::create([
            'name' => 'Lobby',
            'slug' => 'lobby-'.uniqid(),
            'type' => Space::TYPE_LOBBY,
            'capacity' => $capacity,
            'is_active' => true,
        ]);
    }

    public function test_employee_avr_organization_event_within_capacity_succeeds(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->facultyUser());
        $avr = $this->avrSpace(70);

        $response = $this->postJson('/api/reservations', array_merge([
            'space_id' => $avr->id,
            'start_at' => '2026-05-20T08:00:00+08:00',
            'end_at' => '2026-05-21T18:00:00+08:00',
            'event_title' => 'Institutional event',
            'participant_count' => 25,
        ], $this->organizationEventAudiencePayload()));

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_employee_avr_employee_event_within_capacity_succeeds_with_college_mapping(): void
    {
        Mail::fake();
        $user = $this->facultyUser();
        DeanEmailMapping::create([
            'affiliation_type' => DeanEmailMapping::TYPE_COLLEGE,
            'affiliation_name' => 'College of Engineering',
            'approver_name' => 'Engineering Dean',
            'approver_email' => 'eng.dean@test.xu.edu.ph',
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);
        $avr = $this->avrSpace(70);

        $response = $this->postJson('/api/reservations', [
            'space_id' => $avr->id,
            'start_at' => '2026-05-20T08:00:00+08:00',
            'end_at' => '2026-05-20T12:00:00+08:00',
            'event_title' => 'Faculty workshop',
            'participant_count' => 25,
            'event_request_type' => Reservation::EVENT_REQUEST_EMPLOYEE,
        ]);

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_employee_avr_requires_event_audience(): void
    {
        Sanctum::actingAs($this->facultyUser());
        $avr = $this->avrSpace(70);

        $response = $this->postJson('/api/reservations', [
            'space_id' => $avr->id,
            'start_at' => '2026-05-20T08:00:00+08:00',
            'end_at' => '2026-05-20T12:00:00+08:00',
            'event_title' => 'Missing audience',
            'participant_count' => 25,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['event_request_type']);
    }

    public function test_employee_avr_over_capacity_fails_seating_not_audience(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->facultyUser());
        $avr = $this->avrSpace(70);

        $response = $this->postJson('/api/reservations', array_merge([
            'space_id' => $avr->id,
            'start_at' => '2026-05-20T08:00:00+08:00',
            'end_at' => '2026-05-20T12:00:00+08:00',
            'event_title' => 'Large group',
            'participant_count' => 71,
        ], $this->organizationEventAudiencePayload()));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['participant_count']);
        $response->assertJsonMissingValidationErrors(['event_request_type']);
        $this->assertStringContainsString(
            'Exceeded the Seating capacity',
            (string) $response->json('errors.participant_count.0')
        );
        Mail::assertNothingSent();
    }

    public function test_employee_avr_at_exact_capacity_passes(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->facultyUser());
        $avr = $this->avrSpace(70);

        $response = $this->postJson('/api/reservations', array_merge([
            'space_id' => $avr->id,
            'start_at' => '2026-05-20T08:00:00+08:00',
            'end_at' => '2026-05-20T12:00:00+08:00',
            'event_title' => 'Full room',
            'participant_count' => 70,
        ], $this->organizationEventAudiencePayload()));

        $response->assertStatus(201);
    }

    public function test_lobby_organization_event_routes_with_sacdev_mapping(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->facultyUser());
        $lobby = $this->lobbySpace(40);

        $response = $this->postJson('/api/reservations', array_merge([
            'space_id' => $lobby->id,
            'start_at' => '2026-05-20T09:00:00+08:00',
            'end_at' => '2026-05-20T11:00:00+08:00',
            'event_title' => 'Lobby org event',
            'participant_count' => 10,
        ], $this->organizationEventAudiencePayload()));

        $response->assertStatus(201);
        $this->assertSame(
            Reservation::EVENT_REQUEST_ORGANIZATION,
            $response->json('data.event_request_type')
        );
    }

    public function test_student_cannot_reserve_avr(): void
    {
        Sanctum::actingAs($this->studentUser());
        $avr = $this->avrSpace(70);

        $response = $this->postJson('/api/reservations', array_merge([
            'space_id' => $avr->id,
            'start_at' => '2026-05-20T08:00:00+08:00',
            'end_at' => '2026-05-20T10:00:00+08:00',
            'event_title' => 'Student attempt',
            'participant_count' => 10,
        ], $this->organizationEventAudiencePayload()));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['space_id']);
        $this->assertSame(StudentSpaceAccess::CONFAB_ONLY_MESSAGE, $response->json('errors.space_id.0'));
    }

    public function test_organization_event_without_sacdev_mapping_returns_422(): void
    {
        DeanEmailMapping::query()->delete();

        Sanctum::actingAs($this->facultyUser());
        $avr = $this->avrSpace(70);

        $response = $this->postJson('/api/reservations', array_merge([
            'space_id' => $avr->id,
            'start_at' => '2026-05-20T08:00:00+08:00',
            'end_at' => '2026-05-20T10:00:00+08:00',
            'event_title' => 'No SACDEV',
            'participant_count' => 5,
        ], $this->organizationEventAudiencePayload()));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['event_request_type']);
        $this->assertStringContainsString(
            'SACDEV',
            (string) $response->json('errors.event_request_type.0')
        );
    }
}
