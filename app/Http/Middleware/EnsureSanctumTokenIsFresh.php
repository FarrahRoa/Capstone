<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EnsureSanctumTokenIsFresh
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if (!$token) {
            return $next($request);
        }

        $user->loadMissing('role');
        $isAdminPortal = method_exists($user, 'isAdminPortalAccount') && $user->isAdminPortalAccount();

        $idleMinutes = (int) ($isAdminPortal
            ? config('sanctum.idle_timeout_admin_minutes')
            : config('sanctum.idle_timeout_minutes'));

        if ($idleMinutes > 0) {
            /** @var Carbon|null $lastUsed */
            $lastUsed = $token->last_used_at ?? $token->created_at;
            if ($lastUsed) {
                $inactiveFor = $lastUsed->diffInMinutes(now());
                if ($inactiveFor > $idleMinutes) {
                    $token->delete();

                    return response()->json([
                        'message' => 'Session expired due to inactivity. Please sign in again.',
                    ], 401);
                }
            }
        }

        return $next($request);
    }
}

