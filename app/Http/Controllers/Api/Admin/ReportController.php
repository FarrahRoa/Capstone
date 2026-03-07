<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Space;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'in:monthly,quarterly,annual,custom',
            'from' => 'required_if:period,custom|nullable|date',
            'to' => 'required_if:period,custom|nullable|date|after_or_equal:from',
        ]);

        $from = $this->resolveFrom($request);
        $to = $this->resolveTo($request);

        $reservations = Reservation::whereBetween('start_at', [$from, $to])
            ->with(['user', 'space'])
            ->get();

        $approved = $reservations->where('status', Reservation::STATUS_APPROVED);

        $byCollegeOffice = $reservations->groupBy(fn ($r) => $r->user->college_office ?? 'Not specified')
            ->map->count()
            ->sortDesc();

        $students = $reservations->filter(fn ($r) => $r->user->role && $r->user->role->slug === 'student');
        $byStudentCollege = $students->groupBy(fn ($r) => $r->user->college_office ?? 'Not specified')->map->count()->sortDesc();
        $byYearLevel = $students->groupBy(fn ($r) => $r->user->year_level ?? 'Not specified')->map->count()->sortDesc();

        $bySpace = $approved->groupBy('space_id')->map(function ($items, $spaceId) {
            $space = Space::find($spaceId);
            return [
                'space_name' => $space ? $space->name : 'Unknown',
                'count' => $items->count(),
            ];
        })->values();

        $byHour = $approved->groupBy(fn ($r) => $r->start_at->format('H'))->map->count()->sortKeys();

        $durations = $approved->map(fn ($r) => $r->start_at->diffInMinutes($r->end_at));
        $avgDurationMinutes = $durations->isEmpty() ? 0 : round($durations->avg(), 1);

        $approvalTimes = Reservation::whereBetween('start_at', [$from, $to])
            ->where('status', Reservation::STATUS_APPROVED)
            ->whereNotNull('verified_at')
            ->whereNotNull('approved_at')
            ->get()
            ->map(fn ($r) => $r->verified_at->diffInMinutes($r->approved_at));
        $avgApprovalMinutes = $approvalTimes->isEmpty() ? 0 : round($approvalTimes->avg(), 1);

        return response()->json([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'reservations_by_college_office' => $byCollegeOffice,
            'student_college' => $byStudentCollege,
            'student_year_level' => $byYearLevel,
            'room_utilization' => $bySpace,
            'peak_hours' => $byHour,
            'average_reservation_duration_minutes' => $avgDurationMinutes,
            'average_approval_time_minutes' => $avgApprovalMinutes,
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'period' => 'in:monthly,quarterly,annual,custom',
            'from' => 'required_if:period,custom|nullable|date',
            'to' => 'required_if:period,custom|nullable|date|after_or_equal:from',
            'format' => 'in:pdf,json',
        ]);
        $from = $this->resolveFrom($request);
        $to = $this->resolveTo($request);
        $json = $this->index($request)->getData(true);

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
}
