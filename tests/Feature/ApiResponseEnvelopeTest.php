<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiResponseEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_spaces_index_wraps_collection_in_data(): void
    {
        Space::create([
            'name' => 'Room A',
            'slug' => 'room-a',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/spaces');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $this->assertIsArray($response->json('data'));
        $this->assertNotEmpty($response->json('data'));
        $first = $response->json('data.0');
        $this->assertIsArray($first);
        $this->assertArrayHasKey('guideline_details', $first);
        $this->assertSame([], $first['guideline_details']);
    }

    public function test_me_wraps_user_in_data(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test']
        );
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');
        $response->assertOk();
        $response->assertJsonStructure(['data' => ['id', 'email', 'name']]);
        $response->assertJsonPath('data.id', $user->id);
    }

    public function test_reservations_index_keeps_laravel_paginator_shape(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test']
        );
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reservations');
        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'current_page',
            'total',
            'per_page',
        ]);
    }

    public function test_reservation_create_returns_message_and_data_resource(): void
    {
        Mail::fake();

        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test']
        );
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
        $space = Space::create([
            'name' => 'AVR',
            'slug' => 'avr-env',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'space_id' => $space->id,
            'start_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'purpose' => 'Envelope test',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'status']]);
        $this->assertNotNull($response->json('data.id'));
    }
}
