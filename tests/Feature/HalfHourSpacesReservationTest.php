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

class HalfHourSpacesReservationTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): User
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);
        return User::factory()->create(['role_id' => $role->id, 'is_activated' => true]);
    }

    private function makeSpace(string $slug, string $type, string $name): Space
    {
        return Space::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'capacity' => 10,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);
    }

    public function test_confab_requires_half_hour_minutes_and_details(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $confab = $this->makeSpace('confab-1', Space::TYPE_CONFAB, 'Confab 1');

        $bad = $this->postJson('/api/reservations', [
            'space_id' => $confab->id,
            'start_at' => now()->addDays(2)->setTime(9, 15)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(9, 45)->toDateTimeString(),
            'event_title' => 'Confab Title',
            'participant_count' => 5,
        ]);
        $bad->assertStatus(422);
        $bad->assertJsonValidationErrors(['start_at']);

        $ok = $this->postJson('/api/reservations', [
            'space_id' => $confab->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(9, 30)->toDateTimeString(),
            'event_title' => 'Confab Title',
            'event_description' => 'Notes',
            'participant_count' => 5,
        ]);
        $ok->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_medical_confab_requires_half_hour_minutes_and_details(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $med = $this->makeSpace('medical-confab-1', Space::TYPE_MEDICAL_CONFAB, 'Medical Confab 1');

        $bad = $this->postJson('/api/reservations', [
            'space_id' => $med->id,
            'start_at' => now()->addDays(2)->setTime(10, 45)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(11, 0)->toDateTimeString(),
            'event_title' => 'Med Confab',
            'participant_count' => 1,
        ]);
        $bad->assertStatus(422);
        $bad->assertJsonValidationErrors(['start_at']);

        $ok = $this->postJson('/api/reservations', [
            'space_id' => $med->id,
            'start_at' => now()->addDays(2)->setTime(10, 30)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(11, 0)->toDateTimeString(),
            'event_title' => 'Med Confab',
            'participant_count' => 1,
        ]);
        $ok->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_lecture_space_requires_half_hour_minutes_and_details(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $lecture = $this->makeSpace('lecture', 'lecture', 'Lecture Space');

        $bad = $this->postJson('/api/reservations', [
            'space_id' => $lecture->id,
            'start_at' => now()->addDays(2)->setTime(8, 15)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(8, 45)->toDateTimeString(),
            'event_title' => 'Lecture',
            'participant_count' => 20,
        ]);
        $bad->assertStatus(422);
        $bad->assertJsonValidationErrors(['start_at']);

        $ok = $this->postJson('/api/reservations', [
            'space_id' => $lecture->id,
            'start_at' => now()->addDays(2)->setTime(8, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(8, 30)->toDateTimeString(),
            'event_title' => 'Lecture',
            'participant_count' => 20,
        ]);
        $ok->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_confab_assignment_pool_rejects_non_half_hour_minutes(): void
    {
        Mail::fake();
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $pool = Space::create([
            'name' => 'Confab (general)',
            'slug' => 'confab-pool-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 10,
            'is_active' => true,
            'is_confab_pool' => true,
        ]);

        $bad = $this->postJson('/api/reservations', [
            'space_id' => $pool->id,
            'start_at' => now()->addDays(2)->setTime(11, 15)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(12, 0)->toDateTimeString(),
            'event_title' => 'Pool request',
            'participant_count' => 3,
        ]);
        $bad->assertStatus(422);
        $bad->assertJsonValidationErrors(['start_at']);

        $ok = $this->postJson('/api/reservations', [
            'space_id' => $pool->id,
            'start_at' => now()->addDays(2)->setTime(11, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(11, 30)->toDateTimeString(),
            'event_title' => 'Pool request',
            'participant_count' => 3,
        ]);
        $ok->assertStatus(201);
        Mail::assertSent(ReservationVerificationMail::class);
    }
}

