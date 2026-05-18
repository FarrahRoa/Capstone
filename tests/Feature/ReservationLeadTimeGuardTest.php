<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Models\PolicyDocument;
use App\Support\ReservationLeadTimePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationLeadTimeGuardTest extends TestCase
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
        $this->seedSacdevDeanMappingForTests();
    }

    private function makeBoardroom(): Space
    {
        return Space::create([
            'name' => 'Boardroom Test',
            'slug' => 'boardroom-lead-test',
            'type' => Space::TYPE_BOARDROOM,
            'capacity' => 20,
            'is_active' => true,
        ]);
    }

    private function actingStudent(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test role']
        );

        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function actingAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'description' => 'Test role']
        );

        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_blocks_same_calendar_day_manila_for_student(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-05-16 10:15:00', 'Asia/Manila'));

        $this->actingStudent();
        $space = $this->makeBoardroom();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2026-05-16T14:00:00+08:00',
            'end_at' => '2026-05-16T14:30:00+08:00',
            'purpose' => 'Study session',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.start_at.0', ReservationLeadTimePolicy::SAME_DAY_DENIED_MESSAGE);
    }

    public function test_blocks_tomorrow_at_exactly_sixteen_thirty_manila(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-05-16 16:30:00', 'Asia/Manila'));

        $this->actingStudent();
        $space = $this->makeBoardroom();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2026-05-17T09:00:00+08:00',
            'end_at' => '2026-05-17T09:30:00+08:00',
            'purpose' => 'Study session',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.reservation.0', PolicyDocument::reservationCutoffValidationMessage());
    }

    public function test_blocks_day_after_tomorrow_during_evening_blackout(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-05-16 18:00:00', 'Asia/Manila'));

        $this->actingStudent();
        $space = $this->makeBoardroom();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2026-05-18T09:00:00+08:00',
            'end_at' => '2026-05-18T09:30:00+08:00',
            'purpose' => 'Study session',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.reservation.0', PolicyDocument::reservationCutoffValidationMessage());
    }

    public function test_blocks_tomorrow_before_nine_am_morning_blackout(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-05-17 08:59:00', 'Asia/Manila'));

        $this->actingStudent();
        $space = $this->makeBoardroom();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2026-05-18T09:00:00+08:00',
            'end_at' => '2026-05-18T09:30:00+08:00',
            'purpose' => 'Study session',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.reservation.0', PolicyDocument::reservationCutoffValidationMessage());
    }

    public function test_allows_tomorrow_at_exactly_nine_am_after_blackout(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-05-17 09:00:00', 'Asia/Manila'));

        $this->actingStudent();
        $space = $this->makeBoardroom();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2026-05-18T09:00:00+08:00',
            'end_at' => '2026-05-18T09:30:00+08:00',
            'purpose' => 'Study session',
        ]);

        $response->assertStatus(201);
    }

    public function test_allows_tomorrow_before_sixteen_thirty_manila(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-05-16 16:29:00', 'Asia/Manila'));

        $this->actingStudent();
        $space = $this->makeBoardroom();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2026-05-17T09:00:00+08:00',
            'end_at' => '2026-05-17T09:30:00+08:00',
            'purpose' => 'Study session',
        ]);

        $response->assertStatus(201);
    }

    public function test_admin_may_book_same_day_manila(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-05-16 11:00:00', 'Asia/Manila'));

        $this->actingAdmin();
        $space = $this->makeBoardroom();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => '2026-05-16T14:00:00+08:00',
            'end_at' => '2026-05-16T14:30:00+08:00',
            'purpose' => 'Staff hold',
        ]);

        $response->assertStatus(201);
    }

    public function test_booking_clock_endpoint_returns_manila_iso(): void
    {
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-05-16 08:30:00', 'UTC'));

        $response = $this->getJson('/api/policies/booking-clock');

        $response->assertOk();
        $response->assertJsonPath('data.timezone', 'Asia/Manila');
        $this->assertStringContainsString('+08:00', (string) $response->json('data.now_iso'));
    }
}
