<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Mail\OtpMail;
use App\Models\Role;
use App\Models\User;
use App\Support\RegistrationDisplayName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AuthController extends Controller
{
    private function authUserPayload(User $user): array
    {
        return $user->toApiArray();
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = strtolower($request->input('email'));
        if (!User::isAllowedDomain($email)) {
            return response()->json([
                'message' => 'Invalid email domain.',
            ], 422);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $roleSlug = User::getRoleSlugFromEmail($email);
            if (!$roleSlug) {
                return response()->json(['message' => 'Invalid email domain.'], 422);
            }

            $role = Role::where('slug', $roleSlug)->first();
            if (!$role) {
                return response()->json([
                    'message' => "Role '{$roleSlug}' is not configured. Please contact the administrator.",
                ], 422);
            }
            $user = User::create([
                'name' => RegistrationDisplayName::fromEmail($email),
                'email' => $email,
                'password' => Hash::make($request->input('password')),
                'role_id' => $role->id,
                'is_activated' => false,
            ]);
        } else {
            if (!Hash::check($request->input('password'), $user->password)) {
                return response()->json(['message' => 'Invalid credentials.'], 401);
            }
        }

        if (!$user->is_activated) {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->update([
                'otp' => $otp,
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

        $token = $user->createToken('auth')->plainTextToken;
        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->authUserPayload($user),
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($user->is_activated) {
            $token = $user->createToken('auth')->plainTextToken;
            return response()->json([
                'message' => 'Already activated.',
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->authUserPayload($user),
            ]);
        }
        if ($user->otp !== $request->input('otp')) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }
        if ($user->otp_expires_at && $user->otp_expires_at->isPast()) {
            return response()->json(['message' => 'OTP has expired.'], 422);
        }
        $user->update([
            'is_activated' => true,
            'otp' => null,
            'otp_expires_at' => null,
        ]);
        $token = $user->createToken('auth')->plainTextToken;
        return response()->json([
            'message' => 'Account activated.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->authUserPayload($user),
        ]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->input('email'))->first();
        if (!$user || $user->is_activated) {
            return response()->json(['message' => 'Invalid request.'], 422);
        }
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->update([
            'otp' => $otp,
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
        return response()->json($this->authUserPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }
}
