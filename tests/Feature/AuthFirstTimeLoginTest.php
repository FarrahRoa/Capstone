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

class AuthFirstTimeLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_time_login_with_my_xu_domain_assigns_student_role_and_sends_otp(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $response = $this->postJson('/api/login', [
            'email' => '20220024802@my.xu.edu.ph',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'requires_otp' => true,
            'message' => 'OTP sent to your XU email.',
        ]);

        $user = User::where('email', '20220024802@my.xu.edu.ph')->first();
        $this->assertNotNull($user);
        $this->assertSame('XU User', $user->name);
        $this->assertFalse($user->is_activated);

        $studentRole = Role::where('slug', 'student')->first();
        $this->assertNotNull($studentRole);
        $this->assertSame($studentRole->id, $user->role_id);

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_first_time_login_returns_clear_message_when_role_record_is_missing(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => '20220024802@my.xu.edu.ph',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => "Role 'student' is not configured. Please contact the administrator.",
        ]);
    }

    public function test_first_time_faculty_email_sets_derived_display_name_from_local_part(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $response = $this->postJson('/api/login', [
            'email' => 'juan.cruz@xu.edu.ph',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $user = User::where('email', 'juan.cruz@xu.edu.ph')->first();
        $this->assertNotNull($user);
        $this->assertSame('Juan Cruz', $user->name);
    }

    public function test_existing_activated_user_login_returns_token_without_otp(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $faculty = Role::where('slug', 'faculty')->first();
        $this->assertNotNull($faculty);

        User::create([
            'name' => 'Existing Faculty',
            'email' => 'existing@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $faculty->id,
            'is_activated' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'existing@xu.edu.ph',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user']);
        $this->assertArrayNotHasKey('requires_otp', $response->json());
        $this->assertSame('Existing Faculty', $response->json('user.name'));
        Mail::assertNothingSent();
    }

    public function test_otp_verify_still_activates_after_first_time_login_without_name(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'newperson@my.xu.edu.ph',
            'password' => 'pw123456',
        ]);

        $user = User::where('email', 'newperson@my.xu.edu.ph')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->otp);

        $response = $this->postJson('/api/otp/verify', [
            'email' => 'newperson@my.xu.edu.ph',
            'otp' => $user->otp,
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertTrue($user->is_activated);
        $this->assertSame('Newperson', $user->name);
    }

    public function test_otp_verify_response_flags_profile_enrichment_when_name_is_fallback(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => '20220024802@my.xu.edu.ph',
            'password' => 'secret123',
        ]);

        $user = User::where('email', '20220024802@my.xu.edu.ph')->first();
        $this->assertNotNull($user);
        $this->assertSame('XU User', $user->name);

        $response = $this->postJson('/api/otp/verify', [
            'email' => '20220024802@my.xu.edu.ph',
            'otp' => $user->otp,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('user.needs_profile_enrichment', true);
        $response->assertJsonPath('user.name', 'XU User');
    }
}
