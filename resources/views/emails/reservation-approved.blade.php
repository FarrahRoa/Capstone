<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Approved</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Xavier University Library – Reservation Approved</h2>
    <p>Hello {{ $reservation->user->name }},</p>
    <p>Your reservation has been approved.</p>
    <p><strong>Reservation number:</strong> {{ $reservation->reservation_number }}</p>
    <p><strong>Space:</strong> {{ $reservation->space->userFacingName() }}</p>
    <p><strong>Date & time:</strong> {{ \App\Support\ReservationDisplayFormat::dateAndTimes($reservation->start_at, $reservation->end_at) }}</p>
    <p>Please present this reservation number when you use the space.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
    <p style="font-size: 12px; color: #888;">Xavier University Library - Space Reservation System</p>
</body>
</html>
