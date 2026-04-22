<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcceptLibrarianInviteRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AuthEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LibrarianInviteController extends Controller
{
    public function validateInvite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'token' => ['required', 'string'],
        ]);

        $email = AuthEmail::normalize($data['email']);
        $user = User::findByNormalizedEmail($email);
        if (!$user || !$user->loadMissing('role')->role || $user->role->slug !== 'librarian') {
            return response()->json(['message' => 'Invalid invite.'], 404);
        }

        if (!$user->admin_invite_token_hash) {
            return response()->json(['message' => 'This invite has already been used.'], 410);
        }

        if ($user->admin_invite_expires_at && $user->admin_invite_expires_at->isPast()) {
            return response()->json(['message' => 'This invite link has expired.'], 410);
        }

        if (!Hash::check((string) $data['token'], $user->admin_invite_token_hash)) {
            return response()->json(['message' => 'Invalid invite token.'], 404);
        }

        return ApiResponse::data([
            'email' => $user->email,
            'expires_at' => optional($user->admin_invite_expires_at)?->toISOString(),
        ]);
    }

    public function accept(AcceptLibrarianInviteRequest $request): JsonResponse
    {
        $email = AuthEmail::normalize($request->input('email'));
        $token = (string) $request->input('token');

        $user = User::findByNormalizedEmail($email);
        if (!$user || !$user->loadMissing('role')->role || $user->role->slug !== 'librarian') {
            return response()->json(['message' => 'Invalid invite.'], 404);
        }

        if (!$user->admin_invite_token_hash) {
            return response()->json(['message' => 'This invite has already been used.'], 410);
        }

        if ($user->admin_invite_expires_at && $user->admin_invite_expires_at->isPast()) {
            return response()->json(['message' => 'This invite link has expired.'], 410);
        }

        if (!Hash::check($token, $user->admin_invite_token_hash)) {
            return response()->json(['message' => 'Invalid invite token.'], 404);
        }

        $user->update([
            'password' => Hash::make((string) $request->input('password')),
            'admin_password_set_at' => now(),
            'admin_invite_token_hash' => null,
            'admin_invite_expires_at' => null,
            'is_activated' => true,
        ]);

        return response()->json([
            'message' => 'Password set. You can now sign in via the admin login page.',
        ]);
    }
}

