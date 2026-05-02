<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dean/office review – reservation</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f1f5f9; color: #0f172a; }
        .wrap { min-height: 100vh; display: flex; align-items: flex-start; justify-content: center; padding: 24px; }
        .card { width: 100%; max-width: 560px; background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 24px; }
        h1 { font-size: 1.35rem; color: #1a365d; margin: 0 0 12px; }
        .meta { font-size: 0.95rem; line-height: 1.5; margin-bottom: 20px; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; }
        button { padding: 12px 20px; border-radius: 8px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; }
        .approve { background: #166534; color: #fff; }
        .reject { background: #b91c1c; color: #fff; }
        .note { font-size: 13px; color: #64748b; margin-top: 20px; }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="card">
            <h1>Review reservation</h1>
            <p class="meta">
                <strong>User:</strong> {{ $reservation->user->name }} ({{ $reservation->user->email }})<br>
                <strong>Space:</strong> {{ $reservation->space->name }}<br>
                <strong>When:</strong> {{ \App\Support\ReservationDisplayFormat::dateAndTimes($reservation->start_at, $reservation->end_at) }}
                @if($reservation->event_title)<br><strong>Event:</strong> {{ $reservation->event_title }}@endif
            </p>
            <p>This page does not record a decision until you submit one of the actions below.</p>
            <div class="actions">
                <form method="POST" action="{{ $approveUrl }}">
                    <button type="submit" class="approve">Approve reservation</button>
                </form>
                <form method="POST" action="{{ $rejectUrl }}">
                    <button type="submit" class="reject">Reject reservation</button>
                </form>
            </div>
            <p class="note">Submitting approves or rejects this request for library follow-up. If this reservation was already decided, you will see a message that no further change was made.</p>
        </div>
    </main>
</body>
</html>
