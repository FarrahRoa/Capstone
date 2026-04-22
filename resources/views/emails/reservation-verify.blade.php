<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Reservation</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Xavier University Library - Confirm Reservation</h2>
    <p>Hello {{ $reservation->user->name }},</p>
    <p>You requested a reservation for <strong>{{ $reservation->space->userFacingName() }}</strong> on {{ \App\Support\ReservationDisplayFormat::dateAndTimes($reservation->start_at, $reservation->end_at) }}.</p>
    <p>Please confirm by clicking the link below (valid for 24 hours):</p>
    <p><a href="{{ url('/confirm-reservation') }}?token={{ $reservation->verification_token }}" style="display: inline-block; background: #1a365d; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Confirm reservation</a></p>
    <p>Or copy this URL: {{ url('/confirm-reservation') }}?token={{ $reservation->verification_token }}</p>
    <p>If you did not make this request, please ignore this email.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
    <p style="font-size: 12px; color: #888;">Xavier University Library - Space Reservation System</p>
</body>
</html>
