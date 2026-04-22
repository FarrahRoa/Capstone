<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_requires_password(): void
    {
        $this->seed(RoleSeeder::class);
        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@xu.edu.ph',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_non_admin_account_cannot_use_admin_login(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $faculty = Role::where('slug', 'faculty')->first();
        $this->assertNotNull($faculty);
        User::create([
            'name' => 'Faculty User',
            'email' => 'faculty@xu.edu.ph',
            'password' => Hash::make('pw'),
            'role_id' => $faculty->id,
            'is_activated' => true,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'faculty@xu.edu.ph',
            'password' => 'pw',
        ]);

        $response->assertStatus(403);
        Mail::assertNothingSent();
    }

    public function test_admin_account_can_use_admin_login_and_receive_otp(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $admin = Role::where('slug', 'admin')->first();
        $this->assertNotNull($admin);
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin1@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $admin->id,
            'is_activated' => true,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin1@xu.edu.ph',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user']);
        $response->assertJsonPath('user.role.slug', 'admin');

        $user->refresh();
        $this->assertNull($user->otp_hash);
        $this->assertNull($user->otp_expires_at);
        Mail::assertNothingSent();
    }

    public function test_admin_account_cannot_use_user_login_endpoint(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $admin = Role::where('slug', 'admin')->first();
        $this->assertNotNull($admin);
        User::create([
            'name' => 'Admin User',
            'email' => 'admin2@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $admin->id,
            'is_activated' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin2@xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_in',
        ]);

        $response->assertStatus(403);
        Mail::assertNothingSent();
    }

    public function test_admin_login_does_not_use_otp_flow(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $admin = Role::where('slug', 'admin')->first();
        $this->assertNotNull($admin);
        User::create([
            'name' => 'Admin User',
            'email' => 'admin3@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $admin->id,
            'is_activated' => true,
        ]);

        $login = $this->postJson('/api/admin/login', [
            'email' => 'admin3@xu.edu.ph',
            'password' => 'secret123',
        ]);

        $login->assertOk();
        $login->assertJsonStructure(['token', 'user']);
        Mail::assertNothingSent();
    }
}

