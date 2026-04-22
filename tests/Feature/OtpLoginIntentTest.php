<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpLoginIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_account_type_rejects_non_my_domain(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $resp = $this->postJson('/api/login', [
            'email' => 'someone@xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ]);

        $resp->assertStatus(422)->assertJsonValidationErrors('email');
        Mail::assertNothingSent();
    }

    public function test_employee_account_type_rejects_my_domain(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $resp = $this->postJson('/api/login', [
            'email' => 'someone@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_up',
        ]);

        $resp->assertStatus(422)->assertJsonValidationErrors('email');
        Mail::assertNothingSent();
    }

    public function test_sign_in_rejects_unknown_email(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $resp = $this->postJson('/api/login', [
            'email' => 'unknown@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_in',
        ]);

        $resp->assertStatus(404)->assertJson([
            'message' => 'No account found for this email. Please sign up first.',
        ]);
        Mail::assertNothingSent();
    }

    public function test_sign_up_rejects_existing_email(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $faculty = Role::where('slug', 'faculty')->first();
        $this->assertNotNull($faculty);

        User::create([
            'name' => 'Existing',
            'email' => 'existing@xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $faculty->id,
            'is_activated' => true,
        ]);

        $resp = $this->postJson('/api/login', [
            'email' => 'existing@xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_up',
        ]);

        $resp->assertStatus(409)->assertJson([
            'message' => 'This email is already registered. Please sign in instead.',
        ]);
        Mail::assertNothingSent();
    }

    public function test_sign_in_sends_otp_for_existing_email(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $faculty = Role::where('slug', 'faculty')->first();
        $this->assertNotNull($faculty);

        User::create([
            'name' => 'Existing',
            'email' => 'existing2@xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $faculty->id,
            'is_activated' => true,
        ]);

        $resp = $this->postJson('/api/login', [
            'email' => 'existing2@xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_in',
        ]);

        $resp->assertOk()->assertJson([
            'requires_otp' => true,
        ]);
        Mail::assertSent(OtpMail::class);
    }
}

