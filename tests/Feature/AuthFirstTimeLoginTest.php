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

    public function test_sign_up_sends_otp_and_creates_user(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $response = $this->postJson('/api/login', [
            'email' => '20220024802@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
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
        $this->assertNull($user->otp, 'Raw OTP should not be stored.');
        $this->assertNotNull($user->otp_hash, 'OTP hash should be stored.');

        $studentRole = Role::where('slug', 'student')->first();
        $this->assertNotNull($studentRole);
        $this->assertSame($studentRole->id, $user->role_id);

        $sentOtp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$sentOtp) {
            $sentOtp = $mail->otp;
            return true;
        });

        $this->assertIsString($sentOtp);
        $this->assertSame(6, strlen($sentOtp));
        $this->assertTrue(Hash::check($sentOtp, $user->otp_hash));
    }

    public function test_first_time_login_returns_clear_message_when_role_record_is_missing(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => '20220024802@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
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
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_up',
        ]);

        $response->assertStatus(200);
        $user = User::where('email', 'juan.cruz@xu.edu.ph')->first();
        $this->assertNotNull($user);
        $this->assertSame('Juan Cruz', $user->name);
    }

    public function test_existing_activated_user_login_still_sends_otp_and_does_not_issue_token(): void
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
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_in',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'requires_otp' => true,
            'message' => 'OTP sent to your XU email.',
        ]);
        $this->assertArrayNotHasKey('token', $response->json());
        Mail::assertSent(OtpMail::class);
    }

    public function test_existing_activated_user_can_verify_otp_and_receive_token(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);
        $faculty = Role::where('slug', 'faculty')->first();
        $this->assertNotNull($faculty);

        $user = User::create([
            'name' => 'Existing Faculty',
            'email' => 'existing2@xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $faculty->id,
            'is_activated' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'existing2@xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_in',
        ])->assertOk();

        $otp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });
        $this->assertNotNull($otp);

        $verify = $this->postJson('/api/otp/verify', [
            'email' => 'existing2@xu.edu.ph',
            'otp' => $otp,
        ]);
        $verify->assertOk();
        $verify->assertJsonStructure(['token', 'user']);

        $user->refresh();
        $this->assertTrue($user->is_activated);
        $this->assertNull($user->otp_hash);
    }

    public function test_otp_verify_still_activates_after_first_time_login_without_name(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'newperson@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ]);

        $user = User::where('email', 'newperson@my.xu.edu.ph')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->otp);
        $this->assertNotNull($user->otp_hash);

        $otp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });
        $this->assertNotNull($otp);

        $response = $this->postJson('/api/otp/verify', [
            'email' => 'newperson@my.xu.edu.ph',
            'otp' => $otp,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user']);
        $response->assertJsonPath('user.requires_profile_completion', true);
        $user->refresh();
        $this->assertTrue($user->is_activated);
        $this->assertNull($user->otp_hash);
        $this->assertNull($user->otp_expires_at);
        $this->assertSame('Newperson', $user->name);
    }

    public function test_otp_verify_response_flags_profile_enrichment_when_name_is_fallback(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => '20220024802@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ]);

        $user = User::where('email', '20220024802@my.xu.edu.ph')->first();
        $this->assertNotNull($user);
        $this->assertSame('XU User', $user->name);

        $otp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });
        $this->assertNotNull($otp);

        $response = $this->postJson('/api/otp/verify', [
            'email' => '20220024802@my.xu.edu.ph',
            'otp' => $otp,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('user.name', 'XU User');
        $response->assertJsonPath('user.requires_profile_completion', true);
        $response->assertJsonPath('user.user_type', User::USER_TYPE_STUDENT);
    }

    public function test_resend_otp_invalidates_previous_and_only_new_otp_verifies(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'resend@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertStatus(200);

        $user = User::where('email', 'resend@my.xu.edu.ph')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_activated);

        $firstOtp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$firstOtp) {
            $firstOtp = $mail->otp;
            return true;
        });
        $this->assertNotNull($firstOtp);

        Mail::fake();
        $this->postJson('/api/otp/resend', [
            'email' => 'resend@my.xu.edu.ph',
        ])->assertStatus(200);

        $user->refresh();
        $this->assertNotNull($user->otp_hash);

        $secondOtp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$secondOtp) {
            $secondOtp = $mail->otp;
            return true;
        });
        $this->assertNotNull($secondOtp);
        $this->assertNotSame($firstOtp, $secondOtp);

        $this->postJson('/api/otp/verify', [
            'email' => 'resend@my.xu.edu.ph',
            'otp' => $firstOtp,
        ])->assertStatus(422);

        $this->postJson('/api/otp/verify', [
            'email' => 'resend@my.xu.edu.ph',
            'otp' => $secondOtp,
        ])->assertStatus(200);

        $user->refresh();
        $this->assertTrue($user->is_activated);
        $this->assertNull($user->otp_hash);
        $this->assertNull($user->otp_expires_at);
    }

    public function test_expired_otp_fails_and_does_not_activate(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'expired@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertStatus(200);

        $user = User::where('email', 'expired@my.xu.edu.ph')->first();
        $this->assertNotNull($user);

        $otp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });
        $this->assertNotNull($otp);

        $user->update(['otp_expires_at' => now()->subSecond()]);

        $this->postJson('/api/otp/verify', [
            'email' => 'expired@my.xu.edu.ph',
            'otp' => $otp,
        ])->assertStatus(422);

        $user->refresh();
        $this->assertFalse($user->is_activated);
    }

    public function test_otp_cannot_be_reused_after_successful_verification(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'reuse@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertStatus(200);

        $user = User::where('email', 'reuse@my.xu.edu.ph')->first();
        $this->assertNotNull($user);

        $otp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });
        $this->assertNotNull($otp);

        $this->postJson('/api/otp/verify', [
            'email' => 'reuse@my.xu.edu.ph',
            'otp' => $otp,
        ])->assertStatus(200);

        $user->refresh();
        $this->assertNull($user->otp_hash);

        $this->postJson('/api/otp/verify', [
            'email' => 'reuse@my.xu.edu.ph',
            'otp' => $otp,
        ])->assertStatus(422);
    }

    public function test_throttling_blocks_excessive_login_attempts(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'throttle-login@my.xu.edu.ph',
                'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
                'action' => 'sign_up',
            ])->assertStatus(200);
        }

        $this->postJson('/api/login', [
            'email' => 'throttle-login@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertStatus(429);
    }

    public function test_throttling_blocks_excessive_otp_verify_attempts(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'throttle-verify@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertStatus(200);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/otp/verify', [
                'email' => 'throttle-verify@my.xu.edu.ph',
                'otp' => '000000',
            ])->assertStatus(422);
        }

        $this->postJson('/api/otp/verify', [
            'email' => 'throttle-verify@my.xu.edu.ph',
            'otp' => '000000',
        ])->assertStatus(429);
    }

    public function test_throttling_blocks_excessive_otp_resend_attempts(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'throttle-resend@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertStatus(200);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/otp/resend', [
                'email' => 'throttle-resend@my.xu.edu.ph',
            ])->assertStatus(200);
        }

        $this->postJson('/api/otp/resend', [
            'email' => 'throttle-resend@my.xu.edu.ph',
        ])->assertStatus(429);
    }

    public function test_login_and_otp_normalize_email_for_lookup(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => '  CASETEST@MY.XU.EDU.PH ',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertStatus(200);

        $user = User::where('email', 'casetest@my.xu.edu.ph')->first();
        $this->assertNotNull($user);

        $otp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });
        $this->assertNotNull($otp);

        $this->postJson('/api/otp/verify', [
            'email' => 'CASETEST@my.xu.edu.ph',
            'otp' => $otp,
        ])->assertStatus(200);

        $user->refresh();
        $this->assertTrue($user->is_activated);
    }

    public function test_login_throttle_uses_normalized_email_not_raw_casing(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => '  THROTTLECASE@MY.XU.EDU.PH ',
                'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
                'action' => 'sign_up',
            ])->assertStatus(200);
        }

        $this->postJson('/api/login', [
            'email' => 'throttlecase@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertStatus(429);
    }

    public function test_otp_verify_rejects_non_numeric_otp(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'digits@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertStatus(200);

        $this->postJson('/api/otp/verify', [
            'email' => 'digits@my.xu.edu.ph',
            'otp' => 'ABCDEF',
        ])->assertStatus(422)->assertJsonValidationErrors('otp');
    }

    public function test_login_rejects_non_xu_email(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $response = $this->postJson('/api/login', [
            'email' => 'someone@gmail.com',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        Mail::assertNothingSent();
    }
}
