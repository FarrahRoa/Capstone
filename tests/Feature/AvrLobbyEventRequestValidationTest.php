<?php

namespace Tests\Feature;

use App\Models\DeanEmailMapping;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvrLobbyEventRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'college_office' => 'College of Computer Studies',
        ]);
    }

    private function avrSpace(): Space
    {
        return Space::create([
            'name' => 'AVR',
            'slug' => 'avr-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    public function test_avr_create_fails_422_when_organization_chosen_but_sacdev_mapping_missing(): void
    {
        $user = $this->student();
        Sanctum::actingAs($user);
        $space = $this->avrSpace();

        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Test',
            'event_request_type' => Reservation::EVENT_REQUEST_ORGANIZATION,
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['event_request_type']);
    }

    public function test_avr_create_succeeds_when_sacdev_mapping_exists(): void
    {
        Mail::fake();

        DeanEmailMapping::create([
            'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
            'affiliation_name' => 'SACDEV',
            'approver_name' => 'SACDEV Dean',
            'approver_email' => 'sacdev.approver@test.xu.edu.ph',
            'is_active' => true,
        ]);

        $user = $this->student();
        Sanctum::actingAs($user);
        $space = $this->avrSpace();

        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Test',
            'event_request_type' => Reservation::EVENT_REQUEST_ORGANIZATION,
        ]);

        $resp->assertStatus(201);
    }

    public function test_avr_employee_event_requires_active_college_mapping(): void
    {
        $user = $this->student();
        Sanctum::actingAs($user);
        $space = $this->avrSpace();

        $resp = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Test',
            'event_request_type' => Reservation::EVENT_REQUEST_EMPLOYEE,
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['event_request_type']);
    }
}
