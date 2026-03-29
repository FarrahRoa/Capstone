<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_guidelines_and_demo_accounts(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertDatabaseHas('policy_documents', [
            'slug' => PolicyDocument::SLUG_RESERVATION_GUIDELINES,
        ]);

        $this->assertTrue(User::where('email', 'admin@xu.edu.ph')->exists());
        $this->assertTrue(User::where('email', 'demo.student@my.xu.edu.ph')->exists());
        $this->assertTrue(User::where('email', 'demo.librarian@xu.edu.ph')->exists());
    }
}
