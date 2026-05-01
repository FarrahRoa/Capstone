## Features (from repository)

This document describes features evidenced in controllers, requests, models, migrations, and the React frontend.

## Authentication / onboarding

### Email domain restriction

- Implemented in `App\Models\User`:
  - `User::DOMAIN_XU = 'xu.edu.ph'`
  - `User::DOMAIN_MY_XU = 'my.xu.edu.ph'`
  - `User::getRoleSlugFromEmail()` allows only those domains; everything else returns `null`
- Used in `App\Http\Controllers\Api\AuthController::login`:
  - Returns 422 with message “Invalid email domain…” if disallowed.

### First-time user creation

When a user logs in and does not exist:

- `AuthController::login` creates `User` with:
  - `name` from request or email local-part
  - `email` lowercased
  - `password` hashed
  - `role_id` resolved from `roles.slug` returned by `User::getRoleSlugFromEmail()`
  - `is_activated = false`

### OTP activation flow

- `AuthController::login` sends OTP if `is_activated` false.
  - OTP expires in 10 minutes.
  - Mailable: `App\Mail\OtpMail` view `emails.otp`
- `AuthController::verifyOtp` verifies OTP and activates account.
- `AuthController::resendOtp` regenerates OTP if user exists and is not activated.

## Reservation creation flow (end-user)

### Frontend (React)

- Page: `resources/js/pages/ReservationForm.jsx`
  - Loads spaces via `GET /api/spaces`
  - Posts reservation to `POST /api/reservations` with `{ space_id, start_at, end_at, purpose }`
  - On success: navigates to `/my-reservations` and alerts user about email confirmation
- Access control:
  - Navigation hides “New Reservation” for `student_assistant` role (`resources/js/components/Layout.jsx`)
  - Backend enforces via request authorize (see below)

### Backend validation and business rules

Request: `App\Http\Requests\Api\StoreReservationRequest`

- Authorization:
  - `authorize()` requires authenticated user and `User::canManageReservations()` (blocks `student_assistant`)
- Validation rules:
  - `space_id`: required, exists in `spaces`
  - `start_at`: required, date, `after:now`
  - `end_at`: required, date, `after:start_at`
  - `purpose`: nullable, max 1000 chars
- Conflict detection rule:
  - Queries existing `Reservation` rows for same `space_id` where status in:
    - `approved`
    - `pending_approval`
    - `email_verification_pending`
  - Overlap logic checks three cases:
    - Existing start within requested range
    - Existing end within requested range
    - Existing fully covers requested range
  - If overlap exists, adds validation error `slot: Selected time slot is not available.`

### Reservation creation

Controller: `App\Http\Controllers\Api\ReservationController::store`

- Creates `Reservation`:
  - `user_id` = current user
  - `status` = `Reservation::STATUS_EMAIL_VERIFICATION_PENDING`
  - `verification_token` = random 64 chars
  - `verification_expires_at` = now + 24 hours
- Sends verification email:
  - Mailable: `App\Mail\ReservationVerificationMail`
  - View: `resources/views/emails/reservation-verify.blade.php`
  - Link target: `config('app.frontend_url', config('app.url')) . '/confirm-reservation?token=...'`

## Reservation email verification flow

### Frontend

- Page: `resources/js/pages/ConfirmReservation.jsx`
  - Reads `token` query param
  - Calls `POST /api/reservations/confirm-email` with `{ token }`
  - Shows success/failure message

### Backend

Controller: `ReservationController::confirmEmail`

- Validates:
  - `token` required, string, size 64
- Finds reservation:
  - where `verification_token` matches
  - and `status` is `email_verification_pending`
- If reservation not found: 422 “Invalid or expired confirmation link.”
- If `verification_expires_at` is past:
  - sets status to `rejected`
  - returns 422 “Confirmation link has expired.”
- Else:
  - sets status to `pending_approval`
  - sets `verified_at = now`
  - clears verification token and expiry fields
- Admin notification:
  - A comment says “Notify admin …” but no code exists for this.

## Reservation approval / rejection / cancellation / override flow (admin)

### Admin UI

- Page: `resources/js/pages/admin/AdminReservations.jsx`
  - Lists reservations via `GET /api/admin/reservations` with optional `status` filter
  - Approves pending approvals via `POST /api/admin/reservations/{id}/approve`
  - Rejects via `POST /api/admin/reservations/{id}/reject` with `{ reason }`
  - Cancels via `POST /api/admin/reservations/{id}/cancel`
  - Does not call override endpoint (override exists in backend, but UI does not use it; **appears unused by frontend**)

### Backend enforcement

- Route group uses middleware `['auth:sanctum', 'role:admin']` (`routes/api.php`)
- Role middleware: `App\Http\Middleware\EnsureUserHasRole` checks `user->role->slug === 'admin'`

### Approval

Controller: `App\Http\Controllers\Api\Admin\ReservationController::approve`

- Precondition: status must be `pending_approval` else 422.
- Sets:
  - `status = approved`
  - `reservation_number = 'RES-' + random 8 chars`
  - `approved_by = admin user id`
  - `approved_at = now`
- Creates `ReservationLog` with:
  - `action = 'approve'`
  - `notes = $request->input('notes')` (no validation rule for `notes` here)
- Sends `ReservationApprovedMail` with `reservation->fresh(['space','approver'])`

### Rejection

Controller: `Admin\ReservationController::reject`

- Allowed statuses: `pending_approval` or `email_verification_pending` else 422.
- Sets:
  - `status = rejected`
  - `rejected_reason = $request->input('reason')` (no validation rule; can be null)
- Creates `ReservationLog` action `reject` with notes = reason
- Sends `ReservationRejectedMail($reservation->fresh('space'), reason)`

### Cancellation

Controller: `Admin\ReservationController::cancel`

- If already cancelled: 422.
- Sets `status = cancelled` (no other fields).
- Creates `ReservationLog` action `cancel` with notes = `notes` request field (not validated).
- Sends `ReservationRejectedMail` with default reason:
  - `notes` or “Your reservation was cancelled by admin.”

### Override

Controller: `Admin\ReservationController::override`

- Validates: `notes` nullable string max 500.
- If reservation is `pending_approval`, it force-approves similarly to approve (reservation number, approved fields).
- Creates `ReservationLog` action `override`.
- Sends `ReservationApprovedMail`.

## Space management flow

### Backend

- `GET /api/spaces` returns all `Space` rows where `is_active=true`, ordered by `name` (`App\Http\Controllers\Api\SpaceController::index`).

No admin CRUD routes for spaces were found in `routes/api.php`. Space creation/updates appear to be done via seeding (`database/seeders/SpaceSeeder.php`) or direct DB management (**unclear from repository** beyond seeding).

## User management flow

Backend user management endpoints (CRUD, role assignment, activation toggles) were **not found in code** in `routes/api.php` or in controllers present in `app/Http/Controllers`.

What does exist:

- Auto-provisioning users at first login (`AuthController::login`)
- Activation via OTP (`AuthController::verifyOtp`)
- Role assignment at creation time based on email domain; no code path changes role later.
- Seeded default admin user (`DatabaseSeeder`) with password `password`

## Logging / audit flow

- Table: `reservation_logs` (`database/migrations/2025_02_21_000005_create_reservation_logs_table.php`)
- Model: `App\Models\ReservationLog`
- Written by: `App\Http\Controllers\Api\Admin\ReservationController` for actions:
  - approve
  - reject
  - cancel
  - override
- Read by:
  - `Admin\ReservationController::show` loads `logs.admin` relationship.

No general application audit logging beyond reservation logs was found in code.

## Email / notification flow

Mailables found in `app/Mail/`:

- `OtpMail` → `emails.otp`
- `ReservationVerificationMail` → `emails.reservation-verify`
- `ReservationApprovedMail` → `emails.reservation-approved`
- `ReservationRejectedMail` → `emails.reservation-rejected`

No `app/Notifications/*` were found, and no queued mail dispatch is used in these controllers (they call `send(...)` directly).

## Reporting flow

### Backend

Controller: `App\Http\Controllers\Api\Admin\ReportController`

- `GET /api/admin/reports` returns metrics based on reservations between a date range.
  - Period options: monthly, quarterly, annual, custom
  - For custom: requires `from` and `to`
- Metrics include:
  - `reservations_by_college_office` (based on `users.college_office`)
  - Student-specific:
    - `student_college`
    - `student_year_level`
  - `room_utilization` (approved only)
  - `peak_hours` (approved only; grouped by start hour)
  - average reservation duration
  - average approval time (verified_at → approved_at)

- `GET /api/admin/reports/export` supports `format=pdf`
  - PDF export uses `Barryvdh\DomPDF\Facade\Pdf::loadView('reports.export', ...)` if DomPDF facade exists
  - View: `resources/views/reports/export.blade.php`

### Frontend

- Page: `resources/js/pages/admin/AdminReports.jsx`
  - Calls `GET /api/admin/reports`
  - Exports PDF via `GET /api/admin/reports/export` with `responseType: 'blob'`

