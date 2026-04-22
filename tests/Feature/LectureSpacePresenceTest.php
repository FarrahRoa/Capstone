<?php

namespace Tests\Feature;

use App\Models\Space;
use Database\Seeders\SpaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LectureSpacePresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecture_space_is_seeded_once_and_active(): void
    {
        $this->seed(SpaceSeeder::class);
        $this->seed(SpaceSeeder::class);

        $this->assertSame(
            1,
            Space::query()->whereRaw('LOWER(name) = ?', ['lecture space'])->count(),
            'Lecture Space should exist exactly once after repeated seeding.'
        );

        $lecture = Space::query()->whereRaw('LOWER(name) = ?', ['lecture space'])->first();
        $this->assertNotNull($lecture);
        $this->assertSame('Lecture Space', $lecture->name);
        $this->assertSame('lecture', (string) $lecture->type);
        $this->assertTrue((bool) $lecture->is_active);
    }

    public function test_lecture_space_appears_in_public_spaces_api_list(): void
    {
        $this->seed(SpaceSeeder::class);

        $res = $this->getJson('/api/spaces');
        $res->assertOk();

        $res->assertJsonFragment([
            'name' => 'Lecture Space',
            'type' => 'lecture',
        ]);
    }
}

