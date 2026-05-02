<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dean/office approval</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Xavier University Library – Dean/office approval</h2>
    <p>A requester has confirmed an AVR or Lobby reservation by email. Please approve or reject it. <strong>Opening this message does not record a decision.</strong> Use the links below; they open a secure page in your browser where you confirm the final choice.</p>
    <p>
        <strong>User:</strong> {{ $reservation->user->name }} ({{ $reservation->user->email }})<br>
        <strong>Space:</strong> {{ $reservation->space->name }}<br>
        <strong>Date &amp; time:</strong> {{ \App\Support\ReservationDisplayFormat::dateAndTimes($reservation->start_at, $reservation->end_at) }}<br>
        @if($reservation->event_request_type)
            <strong>Event audience:</strong>
            @if($reservation->event_request_type === \App\Models\Reservation::EVENT_REQUEST_ORGANIZATION)
                Organization event (SACDEV routing)
            @elseif($reservation->event_request_type === \App\Models\Reservation::EVENT_REQUEST_EMPLOYEE)
                Employee event (requester affiliation routing)
            @else
                {{ $reservation->event_request_type }}
            @endif
        @endif
    </p>
    @if($reservation->event_title)
        <p><strong>Event:</strong> {{ $reservation->event_title }}</p>
    @endif
    <p style="margin: 24px 0;">
        <a href="{{ $approveReviewUrl }}"
           style="display: inline-block; background: #166534; color: #fff; padding: 12px 20px; text-decoration: none; border-radius: 4px; margin: 0 8px 8px 0; font-size: 15px;">
            Approve reservation
        </a>
        <a href="{{ $rejectReviewUrl }}"
           style="display: inline-block; background: #b91c1c; color: #fff; padding: 12px 20px; text-decoration: none; border-radius: 4px; margin: 0 8px 8px 0; font-size: 15px;">
            Reject reservation
        </a>
    </p>
    <p>Or open the full review page (both options):</p>
    <p>
        <a href="{{ $reviewUrl }}"
           style="display: inline-block; background: #1a365d; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px;">
            Review reservation (secure link)
        </a>
    </p>
    <p style="font-size: 13px; color: #555;">These links expire after several weeks. Each decision can only be applied once; repeated clicks after a decision will show that no further change was made.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
    <p style="font-size: 12px; color: #888;">Xavier University Library - Space Reservation System</p>
</body>
</html>
