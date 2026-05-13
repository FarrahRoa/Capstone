<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reservation updated</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1e293b;">
    <p>Hello {{ $reservation->user->name ?? 'library user' }},</p>

    <p>
        An administrator (<strong>{{ $admin->name }}</strong>) applied a <strong>global override</strong> to your library reservation
        @if($reservation->reservation_number)
            (<strong>{{ $reservation->reservation_number }}</strong>)
        @endif.
    </p>

    <p><strong>Previous schedule</strong></p>
    <ul>
        <li>Time: {{ $previousStart->timezone(config('app.timezone'))->format('M j, Y g:i A') }} – {{ $previousEnd->timezone(config('app.timezone'))->format('g:i A') }}</li>
    </ul>

    <p><strong>New schedule</strong></p>
    <ul>
        <li>Space: {{ $reservation->space->name ?? '—' }}</li>
        <li>Time: {{ $reservation->start_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }} – {{ $reservation->end_at->timezone(config('app.timezone'))->format('g:i A') }}</li>
    </ul>

    <p><strong>Reason provided</strong></p>
    <p style="white-space: pre-wrap; background: #f8fafc; padding: 12px; border-radius: 8px;">{{ $reservation->override_reason }}</p>

    @if(!empty($adminContact))
        <p style="margin-top: 24px; font-size: 14px; color: #64748b;">
            Questions? Contact the library at <a href="mailto:{{ $adminContact }}">{{ $adminContact }}</a>.
        </p>
    @endif
</body>
</html>
