<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Update</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Xavier University Library – Reservation Update</h2>
    <p>Hello {{ $reservation->user->name }},</p>
    <p>Your reservation for <strong>{{ $reservation->space->name }}</strong> on {{ $reservation->start_at->format('M j, Y') }} is no longer confirmed.</p>
    <p><strong>Reason:</strong> {{ $reason }}</p>
    <p>You may submit a new reservation through the library reservation system.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
    <p style="font-size: 12px; color: #888;">Xavier University Library - Space Reservation System</p>
</body>
</html>
