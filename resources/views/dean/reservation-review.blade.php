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
        .actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        button { padding: 12px 20px; border-radius: 8px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; }
        .approve { background: #166534; color: #fff; }
        .reject { background: #b91c1c; color: #fff; }
        .note { font-size: 13px; color: #64748b; margin-top: 20px; }
        .back { font-size: 14px; color: #1a365d; margin-top: 16px; display: inline-block; }
        .callout { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 0.95rem; }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="card">
            @if($intent === 'approve')
                <h1>Confirm approval</h1>
                <div class="callout">You opened the <strong>approve</strong> link. Nothing is recorded until you confirm below.</div>
            @elseif($intent === 'reject')
                <h1>Confirm rejection</h1>
                <div class="callout">You opened the <strong>reject</strong> link. Nothing is recorded until you confirm below.</div>
            @else
                <h1>Review reservation</h1>
            @endif
            <p class="meta">
                <strong>User:</strong> {{ $reservation->user->name }} ({{ $reservation->user->email }})<br>
                <strong>Space:</strong> {{ $reservation->space->name }}<br>
                <strong>When:</strong> {{ \App\Support\ReservationDisplayFormat::dateAndTimes($reservation->start_at, $reservation->end_at) }}
                @if($reservation->event_title)<br><strong>Event:</strong> {{ $reservation->event_title }}@endif
            </p>
            @if($intent === null)
                <p>This page does not record a decision until you submit one of the actions below.</p>
            @else
                <p>Submit only if this matches your decision.</p>
            @endif
            <div class="actions">
                @if($intent === null || $intent === 'approve')
                    <form method="POST" action="{{ $approveUrl }}">
                        <button type="submit" class="approve">{{ $intent === 'approve' ? 'Confirm approval' : 'Approve reservation' }}</button>
                    </form>
                @endif
                @if($intent === null || $intent === 'reject')
                    <form method="POST" action="{{ $rejectUrl }}">
                        <button type="submit" class="reject">{{ $intent === 'reject' ? 'Confirm rejection' : 'Reject reservation' }}</button>
                    </form>
                @endif
            </div>
            @if($intent !== null)
                <p><a class="back" href="{{ $neutralReviewUrl }}">See both approve and reject options</a></p>
            @endif
            <p class="note">Submitting approves or rejects this request for library follow-up. If this reservation was already decided, you will see a message that no further change was made.</p>
        </div>
    </main>
</body>
</html>
