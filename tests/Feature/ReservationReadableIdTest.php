<?php

namespace Tests\Feature;

use App\Mail\ReservationVerificationMail;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Services\ReservationReadableIdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationReadableIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSacdevDeanMappingForTests();
    }

    private function roleWithCreate(string $slug, string $name): Role
    {
        return Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => 'Test']);
    }

    private function studentUser(): User
    {
        $role = $this->roleWithCreate('student', 'Student');

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_STUDENT,
            'email' => 'student-'.uniqid().'@my.xu.edu.ph',
        ]);
    }

    private function facultyUser(): User
    {
        $role = $this->roleWithCreate('faculty', 'Faculty');

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'email' => 'faculty-'.uniqid().'@xu.edu.ph',
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
        ], $extra));
    }

    private function confabPool(): Space
    {
        $pool = Space::query()->firstWhere('is_confab_pool', true);
        $this->assertNotNull($pool);

        return $pool;
    }

    private function reservationPayload(int $spaceId, array $extra = []): array
    {
        return array_merge([
            'space_id' => $spaceId,
            'start_at' => now()->addDays(3)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'event_title' => 'Event',
            'participant_count' => 5,
        ], $extra);
    }

    public function test_student_confab_pool_gets_sequential_cs_ids(): void
    {
        Mail::fake();
        $user = $this->studentUser();
        Sanctum::actingAs($user);
        $pool = $this->confabPool();

        $first = $this->postJson('/api/reservations', $this->reservationPayload($pool->id));
        $first->assertStatus(201);
        $this->assertSame('CS1', $first->json('data.reservation_number'));

        $second = $this->postJson('/api/reservations', array_merge(
            $this->reservationPayload($pool->id),
            [
                'start_at' => now()->addDays(4)->setTime(9, 0)->toDateTimeString(),
                'end_at' => now()->addDays(4)->setTime(10, 0)->toDateTimeString(),
            ]
        ));
        $second->assertStatus(201);
        $this->assertSame('CS2', $second->json('data.reservation_number'));
    }

    public function test_faculty_confab_pool_gets_ce_ids(): void
    {
        Mail::fake();
        $user = $this->facultyUser();
        Sanctum::actingAs($user);
        $pool = $this->confabPool();

        $response = $this->postJson('/api/reservations', $this->reservationPayload($pool->id));
        $response->assertStatus(201);
        $this->assertSame('CE1', $response->json('data.reservation_number'));
        $this->assertSame(ReservationReadableIdService::CATEGORY_CE, $response->json('data.reservation_category'));
    }

    public function test_faculty_avr_gets_suffix_avr_ids(): void
    {
        Mail::fake();
        $user = $this->facultyUser();
        Sanctum::actingAs($user);
        $avr = $this->makeSpace('avr', Space::TYPE_AVR);

        $response = $this->postJson('/api/reservations', array_merge(
            $this->reservationPayload($avr->id),
            $this->organizationEventAudiencePayload(),
        ));
        $response->assertStatus(201);
        $this->assertSame('1AVR', $response->json('data.reservation_number'));
        $this->assertSame(1, (int) $response->json('data.reservation_sequence'));
    }

    public function test_faculty_lobby_and_lecture_get_l_and_ls_ids(): void
    {
        Mail::fake();
        $user = $this->facultyUser();
        Sanctum::actingAs($user);

        $lobby = $this->makeSpace('lobby', Space::TYPE_LOBBY);
        $lobbyResp = $this->postJson('/api/reservations', array_merge(
            $this->reservationPayload($lobby->id),
            $this->organizationEventAudiencePayload(),
        ));
        $lobbyResp->assertStatus(201);
        $this->assertSame('1L', $lobbyResp->json('data.reservation_number'));

        $lecture = $this->makeSpace('lecture', Space::TYPE_LECTURE);
        $lectureResp = $this->postJson('/api/reservations', $this->reservationPayload($lecture->id));
        $lectureResp->assertStatus(201);
        $this->assertSame('1LS', $lectureResp->json('data.reservation_number'));
    }

    public function test_student_role_cannot_reserve_medical_confab_for_medcs_ids(): void
    {
        Mail::fake();
        $user = $this->studentUser();
        $user->update(['med_confab_eligible' => true]);
        Sanctum::actingAs($user);

        $med = $this->makeSpace('med', Space::TYPE_MEDICAL_CONFAB);

        $response = $this->postJson('/api/reservations', $this->reservationPayload($med->id));
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['space_id']);
    }

    public function test_student_cannot_reserve_avr(): void
    {
        Mail::fake();
        $user = $this->studentUser();
        Sanctum::actingAs($user);
        $avr = $this->makeSpace('avr', Space::TYPE_AVR);

        $response = $this->postJson('/api/reservations', array_merge(
            $this->reservationPayload($avr->id),
            $this->organizationEventAudiencePayload(),
        ));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['space_id']);
        Mail::assertNothingSent();
    }

    public function test_cancelled_reservation_does_not_reuse_sequence_number(): void
    {
        Mail::fake();
        $user = $this->studentUser();
        Sanctum::actingAs($user);
        $pool = $this->confabPool();

        $create = $this->postJson('/api/reservations', $this->reservationPayload($pool->id));
        $create->assertStatus(201);
        $id = (int) $create->json('data.id');
        $this->assertSame('CS1', $create->json('data.reservation_number'));

        $reservation = Reservation::findOrFail($id);
        $reservation->update([
            'status' => Reservation::STATUS_CANCELLED,
            'verification_token' => null,
        ]);

        $again = $this->postJson('/api/reservations', array_merge(
            $this->reservationPayload($pool->id),
            [
                'start_at' => now()->addDays(5)->setTime(11, 0)->toDateTimeString(),
                'end_at' => now()->addDays(5)->setTime(12, 0)->toDateTimeString(),
            ]
        ));
        $again->assertStatus(201);
        $this->assertSame('CS2', $again->json('data.reservation_number'));
    }

    public function test_cs_and_ce_counters_are_independent(): void
    {
        Mail::fake();
        $pool = $this->confabPool();

        $student = $this->studentUser();
        Sanctum::actingAs($student);
        $this->postJson('/api/reservations', $this->reservationPayload($pool->id))->assertStatus(201);

        $faculty = $this->facultyUser();
        Sanctum::actingAs($faculty);
        $facultyResp = $this->postJson('/api/reservations', array_merge(
            $this->reservationPayload($pool->id),
            [
                'start_at' => now()->addDays(6)->setTime(9, 0)->toDateTimeString(),
                'end_at' => now()->addDays(6)->setTime(10, 0)->toDateTimeString(),
            ]
        ));
        $facultyResp->assertStatus(201);
        $this->assertSame('CE1', $facultyResp->json('data.reservation_number'));
    }
}
