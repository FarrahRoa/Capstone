<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Pending Approval</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Xavier University Library – Reservation Pending Approval</h2>
    <p>A reservation has been confirmed by email and is now pending admin approval.</p>
    <p>
        <strong>User:</strong> {{ $reservation->user->name }} ({{ $reservation->user->email }})<br>
        <strong>Space:</strong> {{ $reservation->space->name }}<br>
        <strong>Date &amp; time:</strong> {{ $reservation->start_at->format('M j, Y g:i A') }} – {{ $reservation->end_at->format('g:i A') }}
    </p>
    <p>You can review this reservation in the admin panel:</p>
    <p>
        <a href="{{ config('app.frontend_url', config('app.url')) }}/admin/reservations"
           style="display: inline-block; background: #1a365d; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px;">
            Open admin reservations
        </a>
    </p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
    <p style="font-size: 12px; color: #888;">Xavier University Library - Space Reservation System</p>
</body>
</html>

