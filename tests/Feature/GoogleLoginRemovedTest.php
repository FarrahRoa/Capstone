<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleLoginRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_login_route_is_not_available(): void
    {
        $this->seed(RoleSeeder::class);

        $resp = $this->postJson('/api/login/google', [
            'id_token' => str_repeat('x', 60),
            'account_type' => 'student',
        ]);

        $resp->assertStatus(404);
    }
}

