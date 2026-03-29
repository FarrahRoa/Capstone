<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Space;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$from, $to, $payload] = $this->buildReportPayload($request);
        return response()->json($payload);
    }

    public function export(Request $request)
    {
        [$from, $to, $json] = $this->buildReportPayload($request, true);

        if ($request->input('format') === 'json') {
            return response()->json($json);
        }

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.export', [
                'data' => $json,
                'from' => $from,
                'to' => $to,
            ]);
            return $pdf->download('library-report-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.pdf');
        }

        return response()->json($json);
    }

    private function resolveFrom(Request $request): Carbon
    {
        $period = $request->input('period', 'monthly');
        if ($period === 'custom' && $request->has('from')) {
            return Carbon::parse($request->input('from'))->startOfDay();
        }
        $now = Carbon::now();
        return match ($period) {
            'monthly' => $now->copy()->subMonth()->startOfMonth(),
            'quarterly' => $now->copy()->subQuarter()->startOfQuarter(),
            'annual' => $now->copy()->subYear()->startOfYear(),
            default => $now->copy()->subMonth()->startOfMonth(),
        };
    }

    private function resolveTo(Request $request): Carbon
    {
        $period = $request->input('period', 'monthly');
        if ($period === 'custom' && $request->has('to')) {
            return Carbon::parse($request->input('to'))->endOfDay();
        }
        return Carbon::now()->endOfDay();
    }

    private function buildReportPayload(Request $request, bool $forExport = false): array
    {
        $request->validate([
            'period' => 'in:monthly,quarterly,annual,custom',
            'from' => 'required_if:period,custom|nullable|date',
            'to' => 'required_if:period,custom|nullable|date|after_or_equal:from',
            'format' => 'in:pdf,json',
        ]);

        $from = $this->resolveFrom($request);
        $to = $this->resolveTo($request);

        $reservations = Reservation::whereBetween('start_at', [$from, $to])
            ->with(['user.role', 'space', 'approver'])
            ->get();
        $approved = $reservations->where('status', Reservation::STATUS_APPROVED);

        $statusTotals = collect(Reservation::statusLabels())->map(
            fn ($label, $status) => ['status' => $status, 'label' => $label, 'count' => $reservations->where('status', $status)->count()]
        )->values();

        $byCollegeOffice = $reservations->groupBy(fn ($r) => $r->user->college_office ?? 'Not specified')
            ->map->count()
            ->sortDesc();

        $students = $reservations->filter(fn ($r) => $r->user->role && $r->user->role->slug === 'student');
        $byStudentCollege = $students->groupBy(fn ($r) => $r->user->college_office ?? 'Not specified')->map->count()->sortDesc();
        $byYearLevel = $students->groupBy(fn ($r) => $r->user->year_level ?? 'Not specified')->map->count()->sortDesc();

        $spacesById = Space::whereIn('id', $approved->pluck('space_id')->filter()->unique()->values())->get()->keyBy('id');
        $bySpace = $approved->groupBy('space_id')->map(function ($items, $spaceId) use ($spacesById) {
            $space = $spacesById->get($spaceId);
            return [
                'space_name' => $space ? $space->name : 'Unknown',
                'count' => $items->count(),
            ];
        })->values();

        $byHour = $approved->groupBy(fn ($r) => $r->start_at->format('H'))->map->count()->sortKeys();

        $durations = $approved->map(fn ($r) => $r->start_at->diffInMinutes($r->end_at));
        $avgDurationMinutes = $durations->isEmpty() ? 0 : round($durations->avg(), 1);

        $approvalTimes = $reservations
            ->where('status', Reservation::STATUS_APPROVED)
            ->whereNotNull('verified_at')
            ->whereNotNull('approved_at')
            ->map(fn ($r) => $r->verified_at->diffInMinutes($r->approved_at));
        $avgApprovalMinutes = $approvalTimes->isEmpty() ? 0 : round($approvalTimes->avg(), 1);

        $logs = ReservationLog::whereBetween('created_at', [$from, $to])
            ->with(['reservation.user.role', 'reservation.space', 'admin'])
            ->latest()
            ->get();

        $actionTotals = collect(ReservationLog::actionLabels())->map(
            fn ($label, $action) => ['action' => $action, 'label' => $label, 'count' => $logs->where('action', $action)->count()]
        )->values();

        $recentActivity = $logs->take(20)->map(function ($log) {
            return [
                'id' => $log->id,
                'reservation_id' => $log->reservation_id,
                'action' => $log->action,
                'action_label' => ReservationLog::actionLabel($log->action),
                'actor_name' => $log->admin?->name,
                'actor_email' => $log->admin?->email,
                'requester_name' => $log->reservation?->user?->name,
                'requester_email' => $log->reservation?->user?->email,
                'requester_role' => $log->reservation?->user?->role?->name,
                'space_name' => $log->reservation?->space?->name,
                'notes' => $log->notes,
                'created_at' => optional($log->created_at)->toDateTimeString(),
            ];
        })->values();

        $reservationRows = $reservations->sortByDesc('created_at')->values()->map(function ($reservation) {
            return [
                'reservation_id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'requester_name' => $reservation->user?->name,
                'requester_email' => $reservation->user?->email,
                'requester_role' => $reservation->user?->role?->name,
                'space_name' => $reservation->space?->name,
                'start_at' => optional($reservation->start_at)->toDateTimeString(),
                'end_at' => optional($reservation->end_at)->toDateTimeString(),
                'status' => $reservation->status,
                'status_label' => Reservation::statusLabel($reservation->status),
                'created_at' => optional($reservation->created_at)->toDateTimeString(),
                'approved_at' => optional($reservation->approved_at)->toDateTimeString(),
                'approved_by' => $reservation->approver?->name,
                'rejected_reason' => $reservation->rejected_reason,
                'verified_at' => optional($reservation->verified_at)->toDateTimeString(),
            ];
        });

        $payload = [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total_reservations' => $reservations->count(),
                'approved_reservations' => $approved->count(),
                'average_reservation_duration_minutes' => $avgDurationMinutes,
                'average_approval_time_minutes' => $avgApprovalMinutes,
            ],
            'status_totals' => $statusTotals,
            'action_totals' => $actionTotals,
            'recent_activity' => $recentActivity,
            'reservation_rows' => $reservationRows,
            'reservations_by_college_office' => $byCollegeOffice,
            'student_college' => $byStudentCollege,
            'student_year_level' => $byYearLevel,
            'room_utilization' => $bySpace,
            'peak_hours' => $byHour,
            'average_reservation_duration_minutes' => $avgDurationMinutes,
            'average_approval_time_minutes' => $avgApprovalMinutes,
        ];

        return [$from, $to, $payload];
    }
}
