<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Role;
use App\Models\TrustedDevice;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TrustedDeviceLoginTest extends TestCase
{
    use RefreshDatabase;

    private function trustedCookieName(): string
    {
        return (string) config('trusted_device.cookie');
    }

    private function parseTrustedDevicePlainFromResponse(TestResponse $response): ?string
    {
        $name = $this->trustedCookieName();
        foreach ($response->headers->all() as $headerName => $lines) {
            if (strtolower((string) $headerName) !== 'set-cookie') {
                continue;
            }
            foreach ((array) $lines as $line) {
                if (! is_string($line) || ! str_starts_with($line, $name.'=')) {
                    continue;
                }
                $value = substr($line, strlen($name) + 1);
                $pos = strpos($value, ';');

                return $pos === false ? $value : substr($value, 0, $pos);
            }
        }

        return null;
    }

    public function test_first_login_on_new_device_requires_otp_and_does_not_issue_bearer_token(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $response = $this->postJson('/api/login', [
            'email' => 'newdevice@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ]);

        $response->assertOk();
        $response->assertJson([
            'requires_otp' => true,
            'message' => 'OTP sent to your XU email.',
        ]);
        $response->assertJsonMissingPath('token');
        Mail::assertSent(OtpMail::class);
    }

    public function test_successful_otp_verification_creates_trusted_device_and_sets_cookie(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'afterotp@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertOk();

        $otp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });
        $this->assertNotNull($otp);

        $verify = $this->postJson('/api/otp/verify', [
            'email' => 'afterotp@my.xu.edu.ph',
            'otp' => $otp,
        ]);
        $verify->assertOk();
        $verify->assertJsonStructure(['token', 'user']);

        $user = User::where('email', 'afterotp@my.xu.edu.ph')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseCount('trusted_devices', 1);
        $this->assertDatabaseHas('trusted_devices', [
            'user_id' => $user->id,
        ]);

        $plain = $this->parseTrustedDevicePlainFromResponse($verify);
        $this->assertNotNull($plain);
        $this->assertGreaterThan(40, strlen($plain));

        $device = TrustedDevice::where('user_id', $user->id)->first();
        $this->assertNotNull($device);
        $this->assertTrue(Hash::check($plain, $device->token_hash));
        $this->assertNull($device->revoked_at);
        $this->assertTrue($device->expires_at->isFuture());
    }

    public function test_second_login_on_same_trusted_device_skips_otp(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'samebrowser@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertOk();

        $otp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        $verify = $this->postJson('/api/otp/verify', [
            'email' => 'samebrowser@my.xu.edu.ph',
            'otp' => $otp,
        ]);
        $verify->assertOk();
        $plain = $this->parseTrustedDevicePlainFromResponse($verify);
        $this->assertNotNull($plain);

        Mail::fake();

        $again = $this->withCookie($this->trustedCookieName(), $plain)->postJson('/api/login', [
            'email' => 'samebrowser@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_in',
        ]);

        $again->assertOk();
        $again->assertJson([
            'requires_otp' => false,
            'message' => 'Signed in.',
        ]);
        $again->assertJsonStructure(['token', 'user']);
        Mail::assertNothingSent();
    }

    public function test_login_without_cookie_on_other_device_still_requires_otp(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'otherdev@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertOk();
        $otp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });
        $this->postJson('/api/otp/verify', [
            'email' => 'otherdev@my.xu.edu.ph',
            'otp' => $otp,
        ])->assertOk();

        Mail::fake();

        $noCookie = $this->postJson('/api/login', [
            'email' => 'otherdev@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_in',
        ]);
        $noCookie->assertOk();
        $noCookie->assertJson([
            'requires_otp' => true,
            'message' => 'OTP sent to your XU email.',
        ]);
        Mail::assertSent(OtpMail::class);
    }

    public function test_expired_trusted_device_requires_otp_again(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $student = Role::where('slug', 'student')->first();
        $this->assertNotNull($student);
        $user = User::create([
            'name' => 'Expired Trust',
            'email' => 'expiredtrust@my.xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        $plain = bin2hex(random_bytes(16));
        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => Hash::make($plain),
            'user_agent' => 'PHPUnit',
            'last_used_at' => now()->subDays(40),
            'expires_at' => now()->subDay(),
        ]);

        Mail::fake();

        $response = $this->withCookie($this->trustedCookieName(), $plain)->postJson('/api/login', [
            'email' => 'expiredtrust@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_in',
        ]);

        $response->assertOk();
        $response->assertJsonPath('requires_otp', true);
        Mail::assertSent(OtpMail::class);
    }

    public function test_revoked_trusted_device_requires_otp_again(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $student = Role::where('slug', 'student')->first();
        $this->assertNotNull($student);
        $user = User::create([
            'name' => 'Revoked Trust',
            'email' => 'revokedtrust@my.xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        $plain = bin2hex(random_bytes(16));
        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => Hash::make($plain),
            'user_agent' => 'PHPUnit',
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
            'revoked_at' => now(),
        ]);

        Mail::fake();

        $response = $this->withCookie($this->trustedCookieName(), $plain)->postJson('/api/login', [
            'email' => 'revokedtrust@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_in',
        ]);

        $response->assertOk();
        $response->assertJsonPath('requires_otp', true);
        Mail::assertSent(OtpMail::class);
    }

    public function test_logout_revokes_current_trusted_device_and_expires_cookie(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'logouttrust@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_up',
        ])->assertOk();
        $otp = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });
        $verify = $this->postJson('/api/otp/verify', [
            'email' => 'logouttrust@my.xu.edu.ph',
            'otp' => $otp,
        ]);
        $verify->assertOk();
        $plain = $this->parseTrustedDevicePlainFromResponse($verify);
        $token = $verify->json('token');
        $this->assertNotNull($plain);
        $this->assertNotNull($token);

        $user = User::where('email', 'logouttrust@my.xu.edu.ph')->first();
        $this->assertNotNull($user);
        $deviceId = TrustedDevice::where('user_id', $user->id)->value('id');
        $this->assertNotNull($deviceId);

        $logout = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withCookie($this->trustedCookieName(), $plain)
            ->postJson('/api/logout');
        $logout->assertOk();

        $this->assertNotNull(TrustedDevice::find($deviceId)?->revoked_at);

        Mail::fake();
        $after = $this->withCookie($this->trustedCookieName(), $plain)->postJson('/api/login', [
            'email' => 'logouttrust@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_in',
        ]);
        $after->assertOk();
        $after->assertJsonPath('requires_otp', true);
        Mail::assertSent(OtpMail::class);
    }

    public function test_trusted_device_direct_login_respects_incomplete_profile_gating(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $student = Role::where('slug', 'student')->first();
        $this->assertNotNull($student);
        $user = User::create([
            'name' => 'XU User',
            'email' => 'incompletegate@my.xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $student->id,
            'is_activated' => true,
            'college_office' => null,
            'user_type' => null,
        ]);

        $plain = bin2hex(random_bytes(16));
        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => Hash::make($plain),
            'user_agent' => 'PHPUnit',
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withCookie($this->trustedCookieName(), $plain)->postJson('/api/login', [
            'email' => 'incompletegate@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_in',
        ]);

        $response->assertOk();
        $response->assertJsonPath('requires_otp', false);
        $response->assertJsonPath('user.requires_profile_completion', true);
        $response->assertJsonPath('user.profile_complete', false);
        Mail::assertNothingSent();
    }

    public function test_admin_login_is_unchanged_and_does_not_set_trusted_device_cookie(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $admin = Role::where('slug', 'admin')->first();
        $this->assertNotNull($admin);
        User::create([
            'name' => 'Admin User',
            'email' => 'admintrust@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $admin->id,
            'is_activated' => true,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admintrust@xu.edu.ph',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user']);
        $this->assertNull($this->parseTrustedDevicePlainFromResponse($response));
        $this->assertDatabaseCount('trusted_devices', 0);
        Mail::assertNothingSent();
    }

    public function test_user_otp_verify_does_not_issue_trusted_device_for_admin_role(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = Role::where('slug', 'admin')->first();
        $this->assertNotNull($admin);
        $user = User::create([
            'name' => 'Admin Edge',
            'email' => 'adminotpedge@xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $admin->id,
            'is_activated' => false,
        ]);
        $otp = '555555';
        $user->forceFill([
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $verify = $this->postJson('/api/otp/verify', [
            'email' => 'adminotpedge@xu.edu.ph',
            'otp' => $otp,
        ]);

        $verify->assertOk();
        $this->assertDatabaseCount('trusted_devices', 0);
        $this->assertNull($this->parseTrustedDevicePlainFromResponse($verify));
    }

    public function test_invalid_trusted_cookie_does_not_bypass_otp(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $student = Role::where('slug', 'student')->first();
        User::create([
            'name' => 'No Match',
            'email' => 'nomatch@my.xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        Mail::fake();

        $response = $this->withCookie($this->trustedCookieName(), 'not-a-matching-token-xxxxxxxxxxxx')->postJson('/api/login', [
            'email' => 'nomatch@my.xu.edu.ph',
            'account_type' => User::PUBLIC_ACCOUNT_STUDENT,
            'action' => 'sign_in',
        ]);

        $response->assertOk();
        $response->assertJsonPath('requires_otp', true);
        Mail::assertSent(OtpMail::class);
    }
}
