<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\User\CompleteProfileRequest;
use App\Http\Requests\User\UpdateAccountRequest;
use App\Support\AuthEmail;
use App\Mail\Auth\OtpMail;
use App\Models\Role;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\OtpAttemptLimiter;
use App\Support\RegistrationDisplayName;
use App\Support\UserAffiliationChangePolicy;
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

    private const OTP_TTL_SECONDS = 60;

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function otpLockoutResponse(string $email): ?JsonResponse
    {
        if (! OtpAttemptLimiter::isLockedOut($email)) {
            return null;
        }

        return response()->json(['message' => OtpAttemptLimiter::LOCKOUT_MESSAGE], 429);
    }

    /**
     * @return array{0: string, 1: User}
     */
    private function issueOtpToUser(User $user): array
    {
        $otp = $this->generateOtp();
        $user->update([
            'otp' => null,
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addSeconds(self::OTP_TTL_SECONDS),
        ]);

        return [$otp, $user];
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
        if ($user && $user->loadMissing('role')->requiresDedicatedAdminPasswordLogin()) {
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
            // Trusted-device OTP skip applies only after onboarding; see verifyOtp / completeProfile.
            if ($trustedPlain !== '' && $user->is_activated && $user->isProfileComplete()) {
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
        if ($lockout = $this->otpLockoutResponse($email)) {
            return $lockout;
        }

        [$otp, $user] = $this->issueOtpToUser($user);
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
        $email = AuthEmail::normalize($request->input('email'));
        if ($lockout = $this->otpLockoutResponse($email)) {
            return $lockout;
        }

        $user = User::findByNormalizedEmail($email);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if (!$user->otp_hash) {
            OtpAttemptLimiter::recordAttempt($email);

            return response()->json(['message' => 'Invalid OTP.'], 422);
        }
        if ($user->otp_expires_at && now()->greaterThan($user->otp_expires_at)) {
            OtpAttemptLimiter::recordAttempt($email);

            return response()->json(['message' => 'OTP has expired.'], 422);
        }
        if (!Hash::check((string) $request->input('otp'), $user->otp_hash)) {
            OtpAttemptLimiter::recordAttempt($email);
            if (OtpAttemptLimiter::isLockedOut($email)) {
                return response()->json(['message' => OtpAttemptLimiter::LOCKOUT_MESSAGE], 429);
            }

            return response()->json(['message' => 'Invalid OTP.'], 422);
        }

        OtpAttemptLimiter::clear($email);
        $user->update([
            'is_activated' => true,
            'otp' => null,
            'otp_hash' => null,
            'otp_expires_at' => null,
        ]);
        $user = $user->fresh();
        $user->loadMissing('role');
        $token = $user->createToken('auth')->plainTextToken;

        $payload = [
            'message' => 'Signed in.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->authUserPayload($user),
        ];

        // Trusted device + cookie: only for fully onboarded non-admin users (after profile completion).
        // First-time users keep Bearer access for /me/profile without trusted-device or idle cutoff.
        if (! $user->isAdmin() && $user->isProfileComplete()) {
            [$trustedPlain, ] = $this->createTrustedDevice($user, $request);

            return response()->json($payload)
                ->withCookie($this->makeTrustedDeviceCookie($trustedPlain));
        }

        return response()->json($payload);
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $email = AuthEmail::normalize($request->input('email'));
        if ($lockout = $this->otpLockoutResponse($email)) {
            return $lockout;
        }

        $user = User::findByNormalizedEmail($email);
        if (!$user) {
            return response()->json(['message' => 'Invalid request.'], 422);
        }

        OtpAttemptLimiter::recordAttempt($email);
        if (OtpAttemptLimiter::isLockedOut($email)) {
            return response()->json(['message' => OtpAttemptLimiter::LOCKOUT_MESSAGE], 429);
        }

        [$otp, $user] = $this->issueOtpToUser($user);
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

        return response()->json([
            'message' => 'OTP resent to your email.',
            'expires_in_seconds' => self::OTP_TTL_SECONDS,
            'resend_cooldown_seconds' => 30,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::data($this->authUserPayload($request->user()));
    }

    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $userType = $actor->user_type ?? User::getUserTypeFromEmail($actor->email);
        if (!$userType) {
            return response()->json(['message' => 'Invalid email domain.'], 422);
        }

        $name = trim((string) $request->input('name'));
        $unit = trim((string) $request->input('college_office'));
        $mobile = trim((string) $request->input('mobile_number'));

        // New (preferred): store normalized IDs; keep legacy college_office for display/back-compat.
        $collegeId = $request->input('college_id');
        $officeId = $request->input('office_id');

        if ($userType === User::USER_TYPE_STUDENT) {
            if ($collegeId !== null && $collegeId !== '') {
                $collegeRow = \App\Models\College::query()->whereKey((int) $collegeId)->first();
                if (! $collegeRow) {
                    return response()->json([
                        'message' => 'Invalid college selection.',
                        'errors' => ['college_id' => ['Select a valid college.']],
                    ], 422);
                }
                $unit = $collegeRow->name;
            } else {
                $allowed = User::allowedStudentColleges();
                if (!in_array($unit, $allowed, true)) {
                    return response()->json([
                        'message' => 'Invalid college selection.',
                        'errors' => ['college_office' => ['Selected value is not allowed for this account type.']],
                    ], 422);
                }
            }
        } else {
            if ($officeId !== null && $officeId !== '') {
                $officeRow = \App\Models\Office::query()->whereKey((int) $officeId)->first();
                if (! $officeRow) {
                    return response()->json([
                        'message' => 'Invalid office selection.',
                        'errors' => ['office_id' => ['Select a valid office/department.']],
                    ], 422);
                }
                $unit = $officeRow->name;
            } else {
                $allowed = User::allowedFacultyOffices();
                if (!in_array($unit, $allowed, true)) {
                    return response()->json([
                        'message' => 'Invalid office selection.',
                        'errors' => ['college_office' => ['Selected value is not allowed for this account type.']],
                    ], 422);
                }
            }
        }

        $actor->update([
            'name' => $name,
            'college_office' => $unit,
            'college_id' => $userType === User::USER_TYPE_STUDENT
                ? (\App\Models\College::query()->where('name', $unit)->value('id') ?: null)
                : null,
            'office_id' => $userType === User::USER_TYPE_STUDENT
                ? null
                : (\App\Models\Office::query()->where('name', $unit)->value('id') ?: null),
            'mobile_number' => $mobile,
            'user_type' => $userType,
            'profile_completed_at' => now(),
        ]);

        $fresh = $actor->fresh();
        $fresh->loadMissing('role');
        $payload = $this->authUserPayload($fresh);

        // New session + trusted device: onboarding finished; inactivity timeout now applies to this token.
        if (! $fresh->isAdmin()) {
            $actor->currentAccessToken()?->delete();
            $newToken = $fresh->createToken('auth')->plainTextToken;
            [$trustedPlain, ] = $this->createTrustedDevice($fresh, $request);

            return response()->json([
                'data' => $payload,
                'token' => $newToken,
                'token_type' => 'Bearer',
            ])->withCookie($this->makeTrustedDeviceCookie($trustedPlain));
        }

        return ApiResponse::data($payload);
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

            $affiliationChanged = false;
            if ($request->has('college_id')) {
                $resolved = UserAffiliationChangePolicy::resolveAffiliationUpdate(
                    $user,
                    (int) $request->input('college_id'),
                    null
                );
                if ($resolved === null) {
                    return response()->json([
                        'message' => 'Invalid college selection.',
                        'errors' => ['college_id' => ['Select a valid college.']],
                    ], 422);
                }
                if (UserAffiliationChangePolicy::studentCollegeChanged($user, $resolved['college_id'])) {
                    $data = array_merge($data, $resolved);
                    $affiliationChanged = true;
                }
            }

            if ($request->has('office_id')) {
                $resolved = UserAffiliationChangePolicy::resolveAffiliationUpdate(
                    $user,
                    null,
                    (int) $request->input('office_id')
                );
                if ($resolved === null) {
                    return response()->json([
                        'message' => 'Invalid office selection.',
                        'errors' => ['office_id' => ['Select a valid office/department.']],
                    ], 422);
                }
                if (UserAffiliationChangePolicy::employeeOfficeChanged($user, $resolved['office_id'])) {
                    $data = array_merge($data, $resolved);
                    $affiliationChanged = true;
                }
            }

            if ($affiliationChanged) {
                $data['last_affiliation_changed_at'] = now();
            }
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
