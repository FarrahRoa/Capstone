<?php

namespace Tests\Feature;

use App\Mail\ReservationVerificationMail;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Support\ReservationDurationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationDurationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function adminUser(): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 'Test']);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function makeSpace(string $slug, string $type, array $extra = []): Space
    {
        return Space::create(array_merge([
            'name' => strtoupper($slug),
            'slug' => $slug.'-'.uniqid(),
            'type' => $type,
            'capacity' => 20,
            'is_active' => true,
            'is_confab_pool' => false,
        ], $extra));
    }

    private function confabPool(): Space
    {
        $pool = Space::query()->firstWhere('is_confab_pool', true);
        $this->assertNotNull($pool);

        return $pool;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function reservationPayload(int $spaceId, string $startAt, string $endAt, array $extra = []): array
    {
        return array_merge([
            'space_id' => $spaceId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'event_title' => 'Duration policy test',
            'participant_count' => 5,
        ], $extra);
    }

    public function test_unit_policy_exempts_avr_and_lobby_from_library_cap(): void
    {
        $avr = $this->makeSpace('avr-u', Space::TYPE_AVR);
        $lobby = $this->makeSpace('lobby-u', Space::TYPE_LOBBY);
        $lecture = $this->makeSpace('lecture-u', Space::TYPE_LECTURE);

        $this->assertFalse(ReservationDurationPolicy::spaceAppliesLibraryDurationCap($avr));
        $this->assertFalse(ReservationDurationPolicy::spaceAppliesLibraryDurationCap($lobby));
        $this->assertTrue(ReservationDurationPolicy::spaceAppliesLibraryDurationCap($lecture));
    }

    public function test_unit_employee_avr_and_lobby_have_no_duration_cap_for_multi_day_minutes(): void
    {
        $user = $this->facultyUser();
        $avr = $this->makeSpace('avr-cap', Space::TYPE_AVR);
        $lobby = $this->makeSpace('lobby-cap', Space::TYPE_LOBBY);

        $thirtyFourHours = 34 * 60;

        $this->assertNull(ReservationDurationPolicy::maxMinutesFor($user, $avr));
        $this->assertNull(
            ReservationDurationPolicy::messageIfDurationExceedsCap($user, $avr, $thirtyFourHours)
        );
        $this->assertNull(
            ReservationDurationPolicy::messageIfDurationExceedsCap($user, $lobby, $thirtyFourHours)
        );
    }

    public function test_employee_confab_four_hours_is_blocked(): void
    {
        Mail::fake();
        $user = $this->facultyUser();
        Sanctum::actingAs($user);
        $pool = $this->confabPool();

        $start = now()->addDays(3)->setTime(9, 0);
        $end = $start->copy()->addHours(4);

        $response = $this->postJson('/api/reservations', $this->reservationPayload(
            $pool->id,
            $start->toDateTimeString(),
            $end->toDateTimeString(),
        ));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end_at']);
        Mail::assertNothingSent();
    }

    public function test_employee_confab_two_hours_is_allowed(): void
    {
        Mail::fake();
        $user = $this->facultyUser();
        Sanctum::actingAs($user);
        $pool = $this->confabPool();

        $start = now()->addDays(3)->setTime(9, 0);
        $end = $start->copy()->addHours(2);

        $response = $this->postJson('/api/reservations', $this->reservationPayload(
            $pool->id,
            $start->toDateTimeString(),
            $end->toDateTimeString(),
        ));

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_employee_avr_long_range_exceeding_three_hours_is_allowed(): void
    {
        Mail::fake();
        $user = $this->facultyUser();
        Sanctum::actingAs($user);
        $avr = $this->makeSpace('avr', Space::TYPE_AVR);

        $start = now()->addDays(5)->setTime(7, 0);
        $end = $start->copy()->setTime(18, 0);

        $response = $this->postJson('/api/reservations', array_merge(
            $this->reservationPayload(
                $avr->id,
                $start->toDateTimeString(),
                $end->toDateTimeString(),
            ),
            $this->organizationEventAudiencePayload(),
        ));

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_employee_lobby_long_range_exceeding_three_hours_is_allowed(): void
    {
        Mail::fake();
        $user = $this->facultyUser();
        Sanctum::actingAs($user);
        $lobby = $this->makeSpace('lobby', Space::TYPE_LOBBY);

        $start = now()->addDays(6)->setTime(8, 0);
        $end = $start->copy()->setTime(17, 30);

        $response = $this->postJson('/api/reservations', array_merge(
            $this->reservationPayload(
                $lobby->id,
                $start->toDateTimeString(),
                $end->toDateTimeString(),
            ),
            $this->organizationEventAudiencePayload(),
        ));

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_employee_lecture_five_hours_is_blocked(): void
    {
        Mail::fake();
        $user = $this->facultyUser();
        Sanctum::actingAs($user);
        $lecture = $this->makeSpace('lecture', Space::TYPE_LECTURE);

        $start = now()->addDays(3)->setTime(9, 0);
        $end = $start->copy()->addHours(5);

        $response = $this->postJson('/api/reservations', $this->reservationPayload(
            $lecture->id,
            $start->toDateTimeString(),
            $end->toDateTimeString(),
        ));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end_at']);
        Mail::assertNothingSent();
    }

    public function test_admin_confab_nine_hours_is_allowed(): void
    {
        Mail::fake();
        $user = $this->adminUser();
        Sanctum::actingAs($user);
        $pool = $this->confabPool();

        $start = now()->addDays(3)->setTime(9, 0);
        $end = $start->copy()->addHours(9);

        $response = $this->postJson('/api/reservations', $this->reservationPayload(
            $pool->id,
            $start->toDateTimeString(),
            $end->toDateTimeString(),
        ));

        $response->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }
}
