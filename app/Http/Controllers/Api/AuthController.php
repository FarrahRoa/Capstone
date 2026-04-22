<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdminLoginRequest;
use App\Http\Requests\Api\CompleteProfileRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\ResendOtpRequest;
use App\Http\Requests\Api\UpdateAccountRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Support\AuthEmail;
use App\Mail\OtpMail;
use App\Models\Role;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\RegistrationDisplayName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Throwable;

class AuthController extends Controller
{
    private function authUserPayload(User $user): array
    {
        return $user->toApiArray();
    }

    private function randomPasswordFallback(): string
    {
        return bin2hex(random_bytes(24));
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function trustedDeviceCookieName(): string
    {
        return (string) config('trusted_device.cookie');
    }

    private function trustedDeviceTtlMinutes(): int
    {
        return max(1, (int) config('trusted_device.lifetime_days')) * 24 * 60;
    }

    private function makeTrustedDeviceCookie(string $plainToken): SymfonyCookie
    {
        return cookie(
            $this->trustedDeviceCookieName(),
            $plainToken,
            $this->trustedDeviceTtlMinutes(),
            '/',
            null,
            (bool) config('session.secure'),
            true,
            false,
            'lax'
        );
    }

    private function forgetTrustedDeviceCookie(): SymfonyCookie
    {
        return Cookie::forget($this->trustedDeviceCookieName());
    }

    /**
     * @return array{0: string, 1: TrustedDevice}
     */
    private function createTrustedDevice(User $user, Request $request): array
    {
        $plain = bin2hex(random_bytes(32));
        $days = max(1, (int) config('trusted_device.lifetime_days'));
        $device = TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => Hash::make($plain),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'last_used_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        return [$plain, $device];
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = AuthEmail::normalize($request->input('email'));
        $accountType = (string) $request->input('account_type');
        $action = (string) $request->input('action');

        $user = User::findByNormalizedEmail($email);
        if ($user && $user->loadMissing('role')->isAdminPortalAccount()) {
            return response()->json([
                'message' => 'This account must sign in via the admin login page.',
            ], 403);
        }

        if ($action === LoginRequest::ACTION_SIGN_IN && !$user) {
            return response()->json([
                'message' => 'No account found for this email. Please sign up first.',
            ], 404);
        }

        if ($action === LoginRequest::ACTION_SIGN_UP && $user) {
            return response()->json([
                'message' => 'This email is already registered. Please sign in instead.',
            ], 409);
        }

        if (!$user) {
            $roleSlug = User::getRoleSlugFromEmail($email);
            $role = $roleSlug ? Role::where('slug', $roleSlug)->first() : null;
            if (!$roleSlug || !$role) {
                return response()->json([
                    'message' => $roleSlug
                        ? "Role '{$roleSlug}' is not configured. Please contact the administrator."
                        : 'Invalid email domain.',
                ], 422);
            }

            $user = User::create([
                'name' => RegistrationDisplayName::fromEmail($email),
                'email' => $email,
                // Password is no longer used for sign-in; keep a random value for legacy schema compatibility.
                'password' => Hash::make($this->randomPasswordFallback()),
                'role_id' => $role->id,
                'user_type' => User::getUserTypeFromEmail($email),
                'is_activated' => false,
            ]);
        }

        // Trusted-device bypass is only valid for existing accounts signing in (not sign-up).
        if ($action === LoginRequest::ACTION_SIGN_IN) {
            $trustedPlain = (string) $request->cookie($this->trustedDeviceCookieName(), '');
            if ($trustedPlain !== '' && $user->is_activated) {
                $device = TrustedDevice::findActiveForUserToken($user, $trustedPlain);
                if ($device) {
                    $days = max(1, (int) config('trusted_device.lifetime_days'));
                    $device->update([
                        'last_used_at' => now(),
                        'expires_at' => now()->addDays($days),
                    ]);
                    $token = $user->createToken('auth')->plainTextToken;

                    return response()->json([
                        'message' => 'Signed in.',
                        'requires_otp' => false,
                        'token' => $token,
                        'token_type' => 'Bearer',
                        'user' => $this->authUserPayload($user),
                    ])->withCookie($this->makeTrustedDeviceCookie($trustedPlain));
                }
            }
        }

        // Untrusted device (or expired / invalid token): send OTP.
        $otp = $this->generateOtp();
        $user->update([
            // Phase 1 hardening: do not store raw OTP.
            'otp' => null,
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
        ]);
        try {
            Mail::to($user->email)->send(new OtpMail($otp));
        } catch (Throwable $e) {
            Log::error('OTP email send failed on login', [
                'user_id' => $user->id,
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'We could not send the verification email. Check mail configuration or try again shortly. If this continues, contact support.',
            ], 503);
        }

        return response()->json([
            'message' => 'OTP sent to your XU email.',
            'requires_otp' => true,
        ]);
    }

    public function adminLogin(AdminLoginRequest $request): JsonResponse
    {
        $email = AuthEmail::normalize($request->input('email'));
        if (!User::isAllowedDomain($email)) {
            return response()->json(['message' => 'Invalid email domain.'], 422);
        }

        $user = User::findByNormalizedEmail($email);
        if (!$user) {
            return response()->json([
                'message' => 'Admin account not found.',
            ], 404);
        }

        $user->loadMissing('role');
        if (!$user->isAdminPortalAccount()) {
            return response()->json([
                'message' => 'This account is not allowed to use admin login.',
            ], 403);
        }

        if (!Hash::check((string) $request->input('password'), (string) $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $user->createToken('auth')->plainTextToken;
        return response()->json([
            'message' => 'Signed in.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->authUserPayload($user),
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = User::findByNormalizedEmail($request->input('email'));
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if (!$user->otp_hash) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }
        if ($user->otp_expires_at && $user->otp_expires_at->isPast()) {
            return response()->json(['message' => 'OTP has expired.'], 422);
        }
        if (!Hash::check((string) $request->input('otp'), $user->otp_hash)) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }
        $user->update([
            'is_activated' => true,
            'otp' => null,
            'otp_hash' => null,
            'otp_expires_at' => null,
        ]);
        $user->loadMissing('role');
        $token = $user->createToken('auth')->plainTextToken;

        $payload = [
            'message' => 'Signed in.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->authUserPayload($user),
        ];

        // Trusted-device cookies are for the normal user OTP flow only, not admin accounts.
        if (!$user->isAdmin()) {
            [$trustedPlain, ] = $this->createTrustedDevice($user, $request);

            return response()->json($payload)
                ->withCookie($this->makeTrustedDeviceCookie($trustedPlain));
        }

        return response()->json($payload);
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $user = User::findByNormalizedEmail($request->input('email'));
        if (!$user) {
            return response()->json(['message' => 'Invalid request.'], 422);
        }
        $otp = $this->generateOtp();
        $user->update([
            // Phase 1 hardening: resend invalidates previous hash by overwriting it.
            'otp' => null,
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
        ]);
        try {
            Mail::to($user->email)->send(new OtpMail($otp));
        } catch (Throwable $e) {
            Log::error('OTP email send failed on resend', [
                'user_id' => $user->id,
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'We could not resend the verification email. Check mail configuration or try again shortly. If this continues, contact support.',
            ], 503);
        }

        return response()->json(['message' => 'OTP resent to your email.']);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::data($this->authUserPayload($request->user()));
    }

    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $userType = $user->user_type ?? User::getUserTypeFromEmail($user->email);
        if (!$userType) {
            return response()->json(['message' => 'Invalid email domain.'], 422);
        }

        $name = trim((string) $request->input('name'));
        $unit = trim((string) $request->input('college_office'));
        $mobile = trim((string) $request->input('mobile_number'));

        $allowed = $userType === User::USER_TYPE_STUDENT
            ? User::allowedStudentColleges()
            : User::allowedFacultyOffices();

        if (!in_array($unit, $allowed, true)) {
            return response()->json([
                'message' => 'Invalid college/office selection.',
                'errors' => [
                    'college_office' => ['Selected value is not allowed for this account type.'],
                ],
            ], 422);
        }

        $user->update([
            'name' => $name,
            'college_office' => $unit,
            'mobile_number' => $mobile,
            'user_type' => $userType,
            'profile_completed_at' => now(),
        ]);

        return ApiResponse::data($this->authUserPayload($user->fresh()));
    }

    public function updateAccount(UpdateAccountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('role');

        $data = [
            'name' => trim((string) $request->input('name')),
        ];

        if ($user->isAdminPortalAccount()) {
            $data['email'] = AuthEmail::normalize((string) $request->input('email'));
            $mobile = $request->input('mobile_number');
            if ($mobile !== null && trim((string) $mobile) !== '') {
                $data['mobile_number'] = trim((string) $mobile);
            }
            $newPassword = $request->validated('password');
            if (is_string($newPassword) && $newPassword !== '') {
                $data['password'] = Hash::make($newPassword);
            }
        } else {
            $data['mobile_number'] = trim((string) $request->input('mobile_number'));
        }

        $user->update($data);

        return ApiResponse::data($this->authUserPayload($user->fresh()));
    }

    public function logout(Request $request): JsonResponse
    {
        $trustedPlain = (string) $request->cookie($this->trustedDeviceCookieName(), '');
        if ($trustedPlain !== '') {
            $device = TrustedDevice::findActiveForUserToken($request->user(), $trustedPlain);
            $device?->revoke();
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.'])
            ->withCookie($this->forgetTrustedDeviceCookie());
    }
}
