<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Library Reservation Report</title>
    <style>
        @page { margin: 12mm 11mm 14mm 11mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.25pt;
            line-height: 1.45;
            color: #1f2937;
        }
        h1 {
            font-size: 17pt;
            color: #283971;
            margin: 0 0 6px 0;
            font-weight: bold;
            letter-spacing: -0.02em;
        }
        h2 {
            font-size: 11.5pt;
            margin: 18px 0 8px 0;
            color: #283971;
            border-bottom: 2px solid #dce3f0;
            padding-bottom: 4px;
            font-weight: bold;
        }
        h2:first-of-type { margin-top: 0; }
        .meta { margin: 0 0 20px 0; color: #4b5563; font-size: 10pt; }
        .report-block { margin-bottom: 18px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            table-layout: fixed;
        }
        table.data th,
        table.data td {
            border: 1px solid #c5cdd9;
            padding: 8px 9px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.data th {
            background: #eef2fa;
            color: #1e2d5c;
            font-size: 9.5pt;
            font-weight: bold;
            padding: 9px 10px;
        }
        table.data td { font-size: 9.25pt; line-height: 1.42; }
        table.data tbody tr:nth-child(even) td { background: #fafbfd; }
        table.data.activity th,
        table.data.activity td { padding: 7px 8px; font-size: 9.5pt; }
        /* Reservation details: PDF-optimized 8 columns (merged schedule + workflow) */
        table.data.details-pdf { table-layout: fixed; width: 100%; margin-bottom: 16px; }
        table.data.details-pdf thead { display: table-header-group; }
        table.data.details-pdf tr.details-pdf-row { page-break-inside: avoid; }
        table.data.details-pdf th {
            white-space: nowrap;
            font-size: 8.75pt;
            padding: 8px 6px;
            vertical-align: bottom;
            line-height: 1.15;
        }
        table.data.details-pdf td {
            font-size: 8.75pt;
            padding: 8px 6px;
            vertical-align: top;
            line-height: 1.38;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.data.details-pdf th:nth-child(1), table.data.details-pdf td:nth-child(1) { width: 8%; }
        table.data.details-pdf th:nth-child(2), table.data.details-pdf td:nth-child(2) { width: 10%; }
        table.data.details-pdf th:nth-child(3), table.data.details-pdf td:nth-child(3) { width: 18%; }
        table.data.details-pdf th:nth-child(4), table.data.details-pdf td:nth-child(4) { width: 8%; }
        table.data.details-pdf th:nth-child(5), table.data.details-pdf td:nth-child(5) { width: 14%; }
        table.data.details-pdf th:nth-child(6), table.data.details-pdf td:nth-child(6) { width: 10%; }
        table.data.details-pdf th:nth-child(7), table.data.details-pdf td:nth-child(7) { width: 14%; }
        table.data.details-pdf th:nth-child(8), table.data.details-pdf td:nth-child(8) { width: 18%; }
        table.data.details-pdf .details-id { font-weight: bold; color: #283971; }
        table.data.details-pdf .details-wrap { word-break: break-word; }
        table.data.details-pdf .details-sched,
        table.data.details-pdf .details-workflow { font-size: 8.25pt; color: #1f2937; }
        table.data.details-pdf .details-empty { text-align: center; color: #6b7280; padding: 16px 10px; }
        table.data.activity th:nth-child(1), table.data.activity td:nth-child(1) { width: 12%; }
        table.data.activity th:nth-child(2), table.data.activity td:nth-child(2) { width: 10%; }
        table.data.activity th:nth-child(3), table.data.activity td:nth-child(3) { width: 10%; }
        table.data.activity th:nth-child(4), table.data.activity td:nth-child(4) { width: 11%; }
        table.data.activity th:nth-child(5), table.data.activity td:nth-child(5) { width: 18%; }
        table.data.activity th:nth-child(6), table.data.activity td:nth-child(6) { width: 11%; }
        table.data.activity th:nth-child(7), table.data.activity td:nth-child(7) { width: 18%; }
        .chart-card {
            background: #f8fafc;
            border: 1px solid #d8dee9;
            border-radius: 4px;
            padding: 14px 16px 16px 16px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .chart-title {
            font-size: 12pt;
            margin: 0 0 6px 0;
            color: #283971;
            border-bottom: none;
            padding-bottom: 0;
            font-weight: bold;
        }
        .chart-sub {
            font-size: 9pt;
            color: #5b6578;
            margin: 0 0 12px 0;
            line-height: 1.4;
        }
        .chart-empty {
            border: 1px dashed #b8c0d0;
            padding: 18px 14px;
            text-align: center;
            color: #6b7280;
            font-size: 10pt;
            margin: 0;
            background: #fff;
            border-radius: 3px;
        }
        table.chart-hbar { width: 100%; border-collapse: collapse; margin: 4px 0 0 0; }
        table.chart-hbar td { border: none; vertical-align: middle; padding: 6px 5px 6px 0; }
        .hbar-label {
            width: 38%;
            font-size: 9.5pt;
            color: #374151;
            font-weight: 600;
            line-height: 1.35;
        }
        .hbar-bar { width: 48%; padding-left: 4px; padding-right: 6px; }
        .hbar-count {
            width: 14%;
            text-align: right;
            font-weight: bold;
            font-size: 11pt;
            color: #283971;
            padding-right: 2px;
        }
        .bar-track {
            background: #e4e9f4;
            height: 16px;
            border-radius: 3px;
            width: 100%;
        }
        .bar-fill-primary { height: 16px; border-radius: 3px; background: #283971; min-width: 3px; }
        .bar-fill-secondary { height: 16px; border-radius: 3px; background: #3a52a3; min-width: 3px; }
        table.chart-vbar-wrap {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 4px 0;
            border-bottom: 2px solid #dce3f0;
        }
        .chart-vbar-cell {
            vertical-align: bottom;
            text-align: center;
            padding: 4px 5px 2px 5px;
            width: auto;
        }
        .vbar-val {
            font-size: 10pt;
            font-weight: bold;
            color: #283971;
            margin-bottom: 4px;
            min-height: 14px;
        }
        .vbar-col {
            width: 18px;
            max-width: 90%;
            margin: 0 auto;
            background: #3a52a3;
            border-radius: 3px 3px 0 0;
        }
        .vbar-lbl {
            font-size: 9pt;
            color: #374151;
            margin-top: 6px;
            line-height: 1.25;
            max-height: 54px;
            overflow: hidden;
            font-weight: 500;
        }
        table.stack-track {
            width: 100%;
            height: 26px;
            border-collapse: collapse;
            margin: 8px 0 12px 0;
            border: 1px solid #c5cdd9;
            border-radius: 3px;
        }
        .stack-seg {
            height: 26px;
            text-align: center;
            vertical-align: middle;
            color: #fff;
            font-size: 10pt;
            font-weight: bold;
        }
        .stack-seg-inner { display: inline-block; padding-top: 1px; }
        table.stack-legend { width: 100%; font-size: 9.5pt; margin-bottom: 4px; border-collapse: collapse; }
        table.stack-legend td { border: none; padding: 5px 6px; vertical-align: middle; }
        table.stack-legend td:nth-child(2) { word-wrap: break-word; overflow-wrap: break-word; max-width: 58%; }
        .leg-swatch { width: 22px; }
        .swatch { display: inline-block; width: 12px; height: 12px; border-radius: 2px; border: 1px solid rgba(0,0,0,0.08); }
        .leg-num { text-align: right; font-weight: bold; color: #283971; font-size: 10pt; white-space: nowrap; }
        /* Peak hours: horizontal bar list (24 rows) — readable clock labels + counts */
        table.chart-hbar.chart-hbar--peak td { padding: 4px 5px 4px 0; vertical-align: middle; }
        table.chart-hbar.chart-hbar--peak .hbar-label {
            width: 17%;
            font-family: DejaVu Sans Mono, DejaVu Sans, monospace;
            font-size: 10pt;
            font-weight: bold;
            color: #111827;
        }
        table.chart-hbar.chart-hbar--peak .hbar-bar { width: 61%; }
        table.chart-hbar.chart-hbar--peak .hbar-count {
            width: 14%;
            font-size: 11.5pt;
            font-weight: bold;
            color: #283971;
        }
        table.chart-hbar.chart-hbar--peak .bar-track { height: 14px; }
        table.chart-hbar.chart-hbar--peak .bar-fill-primary,
        table.chart-hbar.chart-hbar--peak .bar-fill-secondary { height: 14px; }
    </style>
</head>
<body>
    <h1>Xavier University Library – Reservation Report</h1>
    <p class="meta"><strong>Reporting period:</strong> {{ \App\Support\ReservationDisplayFormat::date($from) }} – {{ \App\Support\ReservationDisplayFormat::date($to) }}</p>

    <div class="report-block">
    <h2>Summary</h2>
    <table class="data">
        <tbody>
            <tr><th>Total Reservations</th><td>{{ $data['summary']['total_reservations'] ?? 0 }}</td></tr>
            <tr><th>Approved Reservations</th><td>{{ $data['summary']['approved_reservations'] ?? 0 }}</td></tr>
            <tr><th>Average Reservation Duration</th><td>{{ $data['summary']['average_reservation_duration_minutes'] ?? 0 }} min</td></tr>
            <tr><th>Average Approval Time</th><td>{{ $data['summary']['average_approval_time_minutes'] ?? 0 }} min</td></tr>
        </tbody>
    </table>
    </div>

    <div class="report-block">
    <h2>Status Totals</h2>
    <table class="data">
        <thead><tr><th>Status</th><th>Count</th></tr></thead>
        <tbody>
            @foreach ($data['status_totals'] ?? [] as $row)
                <tr><td>{{ $row['label'] ?? $row['status'] ?? '' }}</td><td>{{ $row['count'] ?? 0 }}</td></tr>
            @endforeach
        </tbody>
    </table>
    </div>

    <div class="report-block">
    <h2>Action Totals</h2>
    <table class="data">
        <thead><tr><th>Action</th><th>Count</th></tr></thead>
        <tbody>
            @foreach ($data['action_totals'] ?? [] as $row)
                <tr><td>{{ $row['label'] ?? $row['action'] ?? '' }}</td><td>{{ $row['count'] ?? 0 }}</td></tr>
            @endforeach
        </tbody>
    </table>
    </div>

    <div class="chart-card">
    @include('reports.partials.pdf-horizontal-bars', [
        'title' => 'Reservations by College/Office',
        'subtitle' => 'Horizontal comparison — long affiliation names.',
        'items' => $charts['college_office'] ?? [],
        'max' => $charts['college_office_max'] ?? 1,
        'fillClass' => 'bar-fill-primary',
    ])
    </div>

    <div class="chart-card">
    @include('reports.partials.pdf-vertical-bars', [
        'title' => 'Student – by College',
        'subtitle' => 'Column comparison by college (student accounts).',
        'items' => $charts['student_college'] ?? [],
        'max' => $charts['student_college_max'] ?? 1,
    ])
    </div>

    <div class="chart-card">
    @include('reports.partials.pdf-horizontal-bars', [
        'title' => 'Employee/Staff – by Office or Department',
        'subtitle' => 'Horizontal layout for long office or department names.',
        'items' => $charts['faculty_office'] ?? [],
        'max' => $charts['faculty_office_max'] ?? 1,
        'fillClass' => 'bar-fill-secondary',
    ])
    </div>

    <div class="chart-card">
    @include('reports.partials.pdf-year-distribution', [
        'title' => 'Student – by Year Level',
        'subtitle' => 'Share of student reservations by year level (distribution).',
        'stack' => $charts['year_level_stack'] ?? [],
    ])
    </div>

    <div class="chart-card">
    @include('reports.partials.pdf-horizontal-bars', [
        'title' => 'Room Utilization',
        'subtitle' => 'Approved reservations per space.',
        'items' => $charts['rooms'] ?? [],
        'max' => $charts['rooms_max'] ?? 1,
        'fillClass' => 'bar-fill-primary',
    ])
    </div>

    <div class="chart-card">
    @include('reports.partials.pdf-peak-hours', [
        'title' => 'Peak Hours',
        'subtitle' => 'Approved reservations by start hour (full day, library timezone).',
        'peak' => $charts['peak'] ?? [],
        'peakMax' => $charts['peak_max'] ?? 1,
    ])
    </div>

    <div class="report-block">
    <h2>Recent Activity</h2>
    <table class="data activity">
        <thead>
            <tr>
                <th>When</th>
                <th>Action</th>
                <th>Actor</th>
                <th>Requester</th>
                <th>Affiliation</th>
                <th>Space</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['recent_activity'] ?? [] as $row)
                <tr>
                    <td>{{ $row['created_at'] ?? '' }}</td>
                    <td>{{ $row['action_label'] ?? '' }}</td>
                    <td>{{ $row['actor_name'] ?? '—' }}</td>
                    <td>{{ $row['requester_name'] ?? '—' }}</td>
                    <td>
                        @php
                            $aff = trim((string) ($row['requester_affiliation'] ?? ''));
                            $co = trim((string) ($row['requester_college_office'] ?? ''));
                        @endphp
                        {{ $aff !== '' ? $aff : ($co !== '' ? $co : 'Not specified') }}
                    </td>
                    <td>{{ $row['space_name'] ?? '—' }}</td>
                    <td>{{ $row['notes'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="color:#888;">No activity in period.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <div class="report-block">
    <h2>Reservation Details</h2>
    <p class="chart-sub" style="margin-top: -4px;">One row per reservation. Schedule combines start and end; workflow combines status, timestamps, approver, and rejection reason.</p>
    @include('reports.partials.pdf-reservation-details', ['rows' => $data['reservation_rows'] ?? []])
    </div>
</body>
</html>
