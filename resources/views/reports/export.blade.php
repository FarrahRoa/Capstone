<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Library Reservation Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.4; color: #333; }
        h1 { font-size: 16px; }
        h2 { font-size: 13px; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .meta { margin-bottom: 20px; color: #666; }
    </style>
</head>
<body>
    <h1>Xavier University Library – Reservation Report</h1>
    <p class="meta">Period: {{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}</p>

    <h2>Reservations by College/Office</h2>
    <table>
        <thead><tr><th>College/Office</th><th>Count</th></tr></thead>
        <tbody>
            @foreach (is_array($data['reservations_by_college_office'] ?? null) ? $data['reservations_by_college_office'] : [] as $name => $count)
                <tr><td>{{ $name }}</td><td>{{ $count }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Student – by College</h2>
    <table>
        <thead><tr><th>College</th><th>Count</th></tr></thead>
        <tbody>
            @foreach (is_array($data['student_college'] ?? null) ? $data['student_college'] : [] as $name => $count)
                <tr><td>{{ $name }}</td><td>{{ $count }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Student – by Year Level</h2>
    <table>
        <thead><tr><th>Year Level</th><th>Count</th></tr></thead>
        <tbody>
            @foreach (is_array($data['student_year_level'] ?? null) ? $data['student_year_level'] : [] as $name => $count)
                <tr><td>{{ $name }}</td><td>{{ $count }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Room Utilization</h2>
    <table>
        <thead><tr><th>Space</th><th>Reservations</th></tr></thead>
        <tbody>
            @foreach ($data['room_utilization'] ?? [] as $row)
                <tr><td>{{ $row['space_name'] ?? '' }}</td><td>{{ $row['count'] ?? 0 }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Peak Hours (by start hour)</h2>
    <table>
        <thead><tr><th>Hour</th><th>Count</th></tr></thead>
        <tbody>
            @foreach (is_array($data['peak_hours'] ?? null) ? $data['peak_hours'] : [] as $hour => $count)
                <tr><td>{{ $hour }}:00</td><td>{{ $count }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <p><strong>Average reservation duration:</strong> {{ $data['average_reservation_duration_minutes'] ?? 0 }} minutes</p>
    <p><strong>Average approval time:</strong> {{ $data['average_approval_time_minutes'] ?? 0 }} minutes</p>
</body>
</html>
