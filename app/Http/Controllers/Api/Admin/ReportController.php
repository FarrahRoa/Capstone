<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Space;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\ReportPdfPresenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Trimmed college/office from profile completion; only "Not specified" when truly empty.
     */
    private function requesterAffiliationLabel(?User $user): string
    {
        if (!$user) {
            return 'Not specified';
        }
        $unit = trim((string) ($user->college_office ?? ''));

        return $unit !== '' ? $unit : 'Not specified';
    }

    /**
     * Effective user_type for reporting (saved profile, else inferred from email domain).
     */
    private function effectiveUserType(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        return $user->user_type ?: User::getUserTypeFromEmail((string) $user->email);
    }

    public function index(Request $request): JsonResponse
    {
        [$from, $to, $payload] = $this->buildReportPayload($request);

        return ApiResponse::data($payload);
    }

    public function export(Request $request)
    {
        [$from, $to, $json] = $this->buildReportPayload($request, true);

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.export', [
                'data' => $json,
                'from' => $from,
                'to' => $to,
                'charts' => ReportPdfPresenter::compile($json),
            ]);
            return $pdf->download('library-report-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.pdf');
        }

        return ApiResponse::data($json);
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
            'format' => 'nullable|in:pdf',
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

        $byCollegeOffice = $reservations->groupBy(fn ($r) => $this->requesterAffiliationLabel($r->user))
            ->map->count()
            ->sortDesc();

        $students = $reservations->filter(fn ($r) => $this->effectiveUserType($r->user) === User::USER_TYPE_STUDENT);
        $byStudentCollege = $students->groupBy(fn ($r) => $this->requesterAffiliationLabel($r->user))->map->count()->sortDesc();

        $facultyStaff = $reservations->filter(fn ($r) => $this->effectiveUserType($r->user) === User::USER_TYPE_FACULTY_STAFF);
        $byFacultyOffice = $facultyStaff->groupBy(fn ($r) => $this->requesterAffiliationLabel($r->user))->map->count()->sortDesc();
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
            ->with(['reservation.user.role', 'reservation.space', 'actor'])
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
                'actor_name' => $log->actor?->name,
                'actor_email' => $log->actor?->email,
                'actor_user_type' => $log->actor?->user_type ?? (\App\Models\User::getUserTypeFromEmail((string) $log->actor?->email)),
                'actor_college_office' => $log->actor?->college_office,
                'requester_name' => $log->reservation?->user?->name,
                'requester_email' => $log->reservation?->user?->email,
                'requester_role' => $log->reservation?->user?->role?->name,
                'requester_user_type' => $log->reservation?->user?->user_type ?? (User::getUserTypeFromEmail((string) $log->reservation?->user?->email)),
                'requester_college_office' => $log->reservation?->user?->college_office,
                'requester_affiliation' => $this->requesterAffiliationLabel($log->reservation?->user),
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
                'requester_user_type' => $reservation->user?->user_type ?? (User::getUserTypeFromEmail((string) $reservation->user?->email)),
                'requester_college_office' => $reservation->user?->college_office,
                'requester_affiliation' => $this->requesterAffiliationLabel($reservation->user),
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
            'recent_activity' => $recentActivity->all(),
            'reservation_rows' => $reservationRows->values()->all(),
            'reservations_by_college_office' => $byCollegeOffice->all(),
            'student_college' => $byStudentCollege->all(),
            'faculty_staff_office' => $byFacultyOffice->all(),
            'student_year_level' => $byYearLevel->all(),
            'room_utilization' => $bySpace->values()->all(),
            'peak_hours' => $byHour->all(),
            'average_reservation_duration_minutes' => $avgDurationMinutes,
            'average_approval_time_minutes' => $avgApprovalMinutes,
        ];

        return [$from, $to, $payload];
    }
}
