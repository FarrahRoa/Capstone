<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Xavier University Library</h2>
    <p>Hello {{ $reservation->user->name }},</p>
    <p>Your reservation for <strong>{{ $reservation->space->name }}</strong> on {{ \App\Support\ReservationDisplayFormat::dateAndTimes($reservation->start_at, $reservation->end_at) }} was <strong>approved by the dean/office approver</strong>.</p>
    <p>It is now <strong>waiting for librarian review</strong>. You will receive another email when the library makes a final decision.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
    <p style="font-size: 12px; color: #888;">Xavier University Library - Space Reservation System</p>
</body>
</html>
