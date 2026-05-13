<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reschedule required</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1e293b;">
    <p>Hello {{ $displaced->user->name ?? 'library user' }},</p>

    <p>
        Your reservation
        @if($displaced->reservation_number)
            (<strong>{{ $displaced->reservation_number }}</strong>)
        @endif
        for <strong>{{ $displaced->space->name ?? 'a library space' }}</strong>
        could not be kept because a library administrator applied a <strong>global override</strong> that required your time slot.
    </p>

    <p><strong>What was booked instead (admin override)</strong></p>
    <ul>
        <li>Space: {{ $newSpace->name }}</li>
        <li>Time: {{ $newStart->timezone(config('app.timezone'))->format('M j, Y g:i A') }} – {{ $newEnd->timezone(config('app.timezone'))->format('g:i A') }}</li>
    </ul>

    <p><strong>Reason for override</strong></p>
    <p style="white-space: pre-wrap; background: #fff7ed; padding: 12px; border-radius: 8px;">{{ $overrideReason }}</p>

    <p>
        <strong>What you need to do:</strong> Sign in to the library reservation system and choose a new available time or room.
        Your booking is marked <em>Reschedule required</em> until you update or cancel it.
    </p>

    @if(!empty($adminContact))
        <p style="margin-top: 24px; font-size: 14px; color: #64748b;">
            Questions? Contact <strong>{{ $admin->name }}</strong> or the library at
            <a href="mailto:{{ $adminContact }}">{{ $adminContact }}</a>.
        </p>
    @endif
</body>
</html>
