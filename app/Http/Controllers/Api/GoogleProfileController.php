<?php

namespace App\Http\Controllers\Api;

use App\Contracts\GoogleCredentialVerifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGoogleProfileCredentialRequest;
use App\Models\User;
use App\Support\RegistrationDisplayName;
use Illuminate\Http\JsonResponse;

class GoogleProfileController extends Controller
{
    public function store(
        StoreGoogleProfileCredentialRequest $request,
        GoogleCredentialVerifier $verifier,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if (! RegistrationDisplayName::needsEnrichment($user->name)) {
            return response()->json([
                'message' => 'Your profile already has a display name.',
            ], 422);
        }

        $payload = $verifier->verify($request->input('credential'));
        if ($payload === null) {
            return response()->json([
                'message' => 'Invalid or expired Google credential. Check GOOGLE_CLIENT_ID matches your Google Cloud OAuth client.',
            ], 422);
        }

        $googleEmail = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($googleEmail === '' || $googleEmail !== strtolower($user->email)) {
            return response()->json([
                'message' => 'Google account email must match your XU account email.',
            ], 422);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $given = trim((string) ($payload['given_name'] ?? ''));
            $family = trim((string) ($payload['family_name'] ?? ''));
            $name = trim($given.' '.$family);
        }

        if ($name === '' || strlen($name) > 255) {
            return response()->json([
                'message' => 'Google did not return a usable name for this account.',
            ], 422);
        }

        $user->update(['name' => $name]);

        return response()->json([
            'message' => 'Profile updated from Google.',
            'user' => $user->fresh()->toApiArray(),
        ]);
    }
}
