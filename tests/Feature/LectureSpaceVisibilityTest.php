<?php

namespace Tests\Feature;

use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LectureSpaceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecture_space_exists_as_active_space_without_seeding(): void
    {
        $this->assertSame(
            1,
            Space::query()->whereRaw('LOWER(name) = ?', ['lecture space'])->count(),
            'Lecture Space should exist exactly once from migrations.'
        );

        $lecture = Space::query()->whereRaw('LOWER(name) = ?', ['lecture space'])->first();
        $this->assertNotNull($lecture);
        $this->assertSame('Lecture Space', $lecture->name);
        $this->assertSame('lecture', (string) $lecture->type);
        $this->assertTrue((bool) $lecture->is_active);
    }

    public function test_lecture_space_appears_in_spaces_api_used_by_dropdowns_and_legends(): void
    {
        $res = $this->getJson('/api/spaces');
        $res->assertOk();
        $res->assertJsonFragment([
            'name' => 'Lecture Space',
            'type' => 'lecture',
        ]);
    }
}

