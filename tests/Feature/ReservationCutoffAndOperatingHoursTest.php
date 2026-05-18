<?php

namespace Tests\Feature;

use App\Mail\ReservationVerificationMail;
use App\Models\PolicyDocument;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationCutoffAndOperatingHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Manila']);
        $this->seedSacdevDeanMappingForTests();
    }

    private function facultyUser(): User
    {
        $role = Role::firstOrCreate(['slug' => 'faculty'], ['name' => 'Faculty', 'description' => 'Test']);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'email' => 'faculty-'.uniqid().'@xu.edu.ph',
        ]);
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

    private function makeSpace(string $type): Space
    {
        return Space::create([
            'name' => strtoupper($type),
            'slug' => $type.'-'.uniqid(),
            'type' => $type,
            'capacity' => 20,
            'is_active' => true,
        ]);
    }

    private function confabPool(): Space
    {
        $pool = Space::query()->firstWhere('is_confab_pool', true);
        $this->assertNotNull($pool);

        return $pool;
    }

    public function test_unit_is_past_reservation_cutoff_uses_server_clock_only(): void
    {
        $this->assertFalse(PolicyDocument::isPastReservationCutoff(
            Carbon::parse('2026-05-16 14:00:00', 'Asia/Manila')
        ));
        $this->assertTrue(PolicyDocument::isPastReservationCutoff(
            Carbon::parse('2026-05-16 16:31:00', 'Asia/Manila')
        ));
        $this->assertFalse(PolicyDocument::isPastReservationCutoff(
            Carbon::parse('2026-05-17 09:00:00', 'Asia/Manila')
        ));
    }

    public function test_employee_may_reserve_avr_same_day_evening_slot_at_two_pm(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-05-16 14:00:00', 'Asia/Manila'));
        Sanctum::actingAs($this->facultyUser());
        $avr = $this->makeSpace(Space::TYPE_AVR);

        $response = $this->postJson('/api/reservations', array_merge([
            'space_id' => $avr->id,
            'start_at' => '2026-05-16T20:00:00+08:00',
            'end_at' => '2026-05-16T21:00:00+08:00',
            'event_title' => 'Evening AVR',
            'participant_count' => 5,
        ], $this->organizationEventAudiencePayload()));

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_allows_tomorrow_submission_at_four_twenty_nine_pm(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-05-16 16:29:00', 'Asia/Manila'));
        Sanctum::actingAs($this->studentUser());
        $space = $this->makeSpace(Space::TYPE_BOARDROOM);

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2026-05-17T09:00:00+08:00',
            'end_at' => '2026-05-17T09:30:00+08:00',
            'purpose' => 'Meeting',
        ]);

        $response->assertStatus(201);
    }

    public function test_blocks_submission_at_four_thirty_one_pm(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-16 16:31:00', 'Asia/Manila'));
        Sanctum::actingAs($this->studentUser());
        $space = $this->makeSpace(Space::TYPE_BOARDROOM);

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2026-05-20T09:00:00+08:00',
            'end_at' => '2026-05-20T09:30:00+08:00',
            'purpose' => 'Meeting',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.reservation.0', PolicyDocument::reservationCutoffValidationMessage());
    }

    public function test_employee_avr_multi_day_range_not_blocked_by_operating_hours(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-05-16 14:00:00', 'Asia/Manila'));
        Sanctum::actingAs($this->facultyUser());
        $avr = $this->makeSpace(Space::TYPE_AVR);

        $response = $this->postJson('/api/reservations', array_merge([
            'space_id' => $avr->id,
            'start_at' => '2026-05-20T08:00:00+08:00',
            'end_at' => '2026-05-21T18:00:00+08:00',
            'event_title' => 'Multi-day AVR',
            'participant_count' => 10,
        ], $this->organizationEventAudiencePayload()));

        $response->assertStatus(201);
        $response->assertJsonMissingValidationErrors(['start_at']);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_confab_outside_operating_hours_is_blocked(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-05-16 10:00:00', 'Asia/Manila'));
        Sanctum::actingAs($this->facultyUser());
        $pool = $this->confabPool();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $pool->id,
            'start_at' => '2026-05-18T19:00:00+08:00',
            'end_at' => '2026-05-18T20:00:00+08:00',
            'event_title' => 'Late confab',
            'participant_count' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_at']);
        $this->assertStringContainsString(
            'operating hours',
            (string) $response->json('errors.start_at.0')
        );
        Mail::assertNothingSent();
    }

    public function test_confab_tomorrow_before_submission_cutoff_is_allowed(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-05-16 14:00:00', 'Asia/Manila'));
        Sanctum::actingAs($this->facultyUser());
        $pool = $this->confabPool();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $pool->id,
            'start_at' => '2026-05-17T17:00:00+08:00',
            'end_at' => '2026-05-17T17:30:00+08:00',
            'event_title' => 'Late afternoon confab',
            'participant_count' => 5,
        ]);

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_confab_start_after_four_thirty_pm_tomorrow_is_allowed_when_submitting_before_cutoff(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-05-16 14:00:00', 'Asia/Manila'));
        Sanctum::actingAs($this->facultyUser());
        $pool = $this->confabPool();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $pool->id,
            'start_at' => '2026-05-17T17:00:00+08:00',
            'end_at' => '2026-05-17T18:00:00+08:00',
            'event_title' => 'Ends at closing',
            'participant_count' => 5,
        ]);

        $response->assertStatus(201);
    }
}
