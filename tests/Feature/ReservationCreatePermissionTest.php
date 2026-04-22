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

class ReservationCreatePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $slug, string $name): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => 'Test role']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function makeSpace(): Space
    {
        return Space::create([
            'name' => 'Room A',
            'slug' => 'room-a',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    public function test_student_can_create_reservation(): void
    {
        Mail::fake();

        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);
        $space = $this->makeSpace();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Study session',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'message' => 'Reservation created. Please confirm your email using the link sent to your XU email.',
        ]);

        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_student_assistant_cannot_create_reservation(): void
    {
        $assistant = $this->makeUserWithRole('student_assistant', 'Student Assistant');
        Sanctum::actingAs($assistant);
        $space = $this->makeSpace();

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Assist work',
        ]);

        $response->assertStatus(403);
    }
}
