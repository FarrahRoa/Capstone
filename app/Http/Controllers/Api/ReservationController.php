<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReservationRequest;
use App\Mail\ReservationPendingApprovalAdminMail;
use App\Mail\ReservationVerificationMail;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->reservations()->with(['space', 'approver'])->latest();
        $reservations = $query->paginate(20);
        return response()->json($reservations);
    }

    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $reservation->load(['space', 'user', 'approver']);
        return response()->json($reservation);
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['status'] = Reservation::STATUS_EMAIL_VERIFICATION_PENDING;
        $data['verification_token'] = Str::random(64);
        $data['verification_expires_at'] = now()->addHours(24);

        $reservation = Reservation::create($data);
        $reservation->load('space');

        \Illuminate\Support\Facades\Mail::to($request->user()->email)
            ->send(new ReservationVerificationMail($reservation));

        return response()->json([
            'message' => 'Reservation created. Please confirm your email using the link sent to your XU email.',
            'reservation' => $reservation,
        ], 201);
    }

    public function confirmEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
        ]);
        $reservation = Reservation::where('verification_token', $request->input('token'))
            ->where('status', Reservation::STATUS_EMAIL_VERIFICATION_PENDING)
            ->first();
        if (!$reservation) {
            return response()->json(['message' => 'Invalid or expired confirmation link.'], 422);
        }
        if ($reservation->verification_expires_at && $reservation->verification_expires_at->isPast()) {
            $reservation->update(['status' => Reservation::STATUS_REJECTED]);
            return response()->json(['message' => 'Confirmation link has expired.'], 422);
        }
        $reservation->update([
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'verified_at' => now(),
            'verification_token' => null,
            'verification_expires_at' => null,
        ]);
        $reservation->load('space', 'user');
        $admins = User::whereHas('role', function ($q) {
            $q->where('slug', 'admin');
        })->get();
        if ($admins->isNotEmpty()) {
            foreach ($admins as $admin) {
                \Illuminate\Support\Facades\Mail::to($admin->email)
                    ->send(new ReservationPendingApprovalAdminMail($reservation));
            }
        }
        return response()->json([
            'message' => 'Reservation confirmed. It is now pending admin approval.',
            'reservation' => $reservation,
        ]);
    }
}
