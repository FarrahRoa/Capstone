<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Role;
use App\Models\User;
use App\Support\OtpAttemptLimiter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpAttemptLimitTest extends TestCase
{
    use RefreshDatabase;

    private function seedFacultyUser(string $email): User
    {
        $this->seed(RoleSeeder::class);
        $faculty = Role::where('slug', 'faculty')->first();
        $this->assertNotNull($faculty);

        return User::create([
            'name' => 'OTP User',
            'email' => $email,
            'password' => Hash::make('legacy'),
            'role_id' => $faculty->id,
            'is_activated' => true,
        ]);
    }

    public function test_otp_expires_after_sixty_seconds(): void
    {
        Mail::fake();
        $user = $this->seedFacultyUser('ttl@my.xu.edu.ph');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_in',
        ])->assertOk();

        $user->refresh();
        $this->assertNotNull($user->otp_expires_at);
        $this->assertTrue($user->otp_expires_at->lte(now()->addSeconds(60)));
        $this->assertTrue($user->otp_expires_at->gte(now()->addSeconds(59)));
    }

    public function test_fifth_failed_verify_returns_429_and_locks_out(): void
    {
        Mail::fake();
        $email = 'lock@my.xu.edu.ph';
        $user = $this->seedFacultyUser($email);

        $this->postJson('/api/login', [
            'email' => $email,
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_in',
        ])->assertOk();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/otp/verify', [
                'email' => $email,
                'otp' => '000000',
            ])->assertStatus(422);
        }

        $this->postJson('/api/otp/verify', [
            'email' => $email,
            'otp' => '000000',
        ])
            ->assertStatus(429)
            ->assertJson(['message' => OtpAttemptLimiter::LOCKOUT_MESSAGE]);

        $this->postJson('/api/otp/resend', ['email' => $email])
            ->assertStatus(429)
            ->assertJson(['message' => OtpAttemptLimiter::LOCKOUT_MESSAGE]);

        $this->postJson('/api/login', [
            'email' => $email,
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_in',
        ])->assertStatus(429);
    }

    public function test_resend_requests_count_toward_lockout(): void
    {
        Mail::fake();
        $email = 'resendlock@my.xu.edu.ph';
        $this->seedFacultyUser($email);

        $this->postJson('/api/login', [
            'email' => $email,
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_in',
        ])->assertOk();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/otp/resend', ['email' => $email])->assertOk();
        }

        $this->postJson('/api/otp/resend', ['email' => $email])
            ->assertStatus(429)
            ->assertJson(['message' => OtpAttemptLimiter::LOCKOUT_MESSAGE]);

        $this->assertGreaterThanOrEqual(5, OtpAttemptLimiter::attempts($email));
    }

    public function test_successful_verify_clears_attempt_counter(): void
    {
        Mail::fake();
        $email = 'clear@my.xu.edu.ph';
        $this->seedFacultyUser($email);

        $this->postJson('/api/login', [
            'email' => $email,
            'account_type' => User::PUBLIC_ACCOUNT_EMPLOYEE,
            'action' => 'sign_in',
        ])->assertOk();

        $otp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });
        $this->assertNotNull($otp);

        $this->postJson('/api/otp/verify', ['email' => $email, 'otp' => '000000'])->assertStatus(422);
        $this->assertSame(1, OtpAttemptLimiter::attempts($email));

        $this->postJson('/api/otp/verify', ['email' => $email, 'otp' => $otp])->assertOk();
        $this->assertSame(0, OtpAttemptLimiter::attempts($email));
    }
}
