## Overview

This repository uses **Laravel Mailables** (classes in `app/Mail/`) and Blade templates in `resources/views/emails/`.

- Default mailer in `.env.example` is `MAIL_MAILER=log` (emails go to logs unless configured).
- No `app/Notifications/*` classes were found (glob returned 0).

## Mail classes (inventory)

Found in `app/Mail/`:

- `App\Mail\OtpMail`
- `App\Mail\ReservationVerificationMail`
- `App\Mail\ReservationApprovedMail`
- `App\Mail\ReservationRejectedMail`

No other mail classes were found.

## `App\Mail\OtpMail`

**File**: `app/Mail/OtpMail.php`  
**View**: `resources/views/emails/otp.blade.php`  
**Subject**: `Your XU Library Login OTP`

### When it is triggered

- `App\Http\Controllers\Api\AuthController::login` (when `is_activated=false`)
- `App\Http\Controllers\Api\AuthController::resendOtp`

### Recipients

- `Mail::to($user->email)`

### Data passed to view

- Public property `string $otp` (referenced as `{{ $otp }}` in blade).

## `App\Mail\ReservationVerificationMail`

**File**: `app/Mail/ReservationVerificationMail.php`  
**View**: `resources/views/emails/reservation-verify.blade.php`  
**Subject**: `Confirm your library space reservation`

### When it is triggered

- `App\Http\Controllers\Api\ReservationController::store`

### Recipients

- `Mail::to($request->user()->email)`

### Data passed to view

- Public property `Reservation $reservation`

### Template behavior and config dependencies

The blade template builds the confirmation link using:

- `config('app.frontend_url', config('app.url'))`

The config key is defined in `config/app.php` as:

- `frontend_url` → `env('FRONTEND_URL', env('APP_URL'))`

`FRONTEND_URL` is **not present** in `.env.example`.
It is now included; set it to the SPA base URL when the frontend is deployed on a different origin than the API.

## `App\Mail\ReservationApprovedMail`

**File**: `app/Mail/ReservationApprovedMail.php`  
**View**: `resources/views/emails/reservation-approved.blade.php`  
**Subject**: `Library reservation approved – <reservation_number>`

### When it is triggered

- `App\Http\Controllers\Api\Admin\ReservationController::approve`
- `App\Http\Controllers\Api\Admin\ReservationController::override`

### Recipients

- `Mail::to($reservation->user->email)`

### Data passed to view

- Public property `Reservation $reservation`

## `App\Mail\ReservationRejectedMail`

**File**: `app/Mail/ReservationRejectedMail.php`  
**View**: `resources/views/emails/reservation-rejected.blade.php`  
**Subject**: `Library reservation update`

### When it is triggered

- `App\Http\Controllers\Api\Admin\ReservationController::reject`
- `App\Http\Controllers\Api\Admin\ReservationController::cancel` (used for cancellation emails)

### Recipients

- `Mail::to($reservation->user->email)`

### Data passed to view

- Public property `Reservation $reservation`
- Public property `string $reason` (defaults to “Your reservation was not approved.”)

## Other notification mechanisms

- `ReservationController::confirmEmail` includes a comment “Notify admin …” but no code dispatches an email/notification/job to admins.
- No SMS, push notifications, or in-app notifications were found in code.

## Missing config or risks

- **Default mailer is log**: production must set `MAIL_MAILER=smtp` (or another transport) for real emails.
- **Synchronous mail sending**: controllers call `send(...)` directly; a slow SMTP server will slow down API responses.
- **Unclear `frontend_url` config**: if not configured, links may point to `APP_URL`, which is backend host, not necessarily the SPA host.
- **Fix**: set `FRONTEND_URL` in `.env` to the SPA base URL.

