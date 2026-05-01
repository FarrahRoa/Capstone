## Reservation business rules (enforced by code)

This section lists reservation rules as implemented in:

- `App\Http\Requests\Api\StoreReservationRequest`
- `App\Http\Controllers\Api\ReservationController`
- `App\Http\Controllers\Api\Admin\ReservationController`
- `App\Http\Controllers\Api\AvailabilityController`
- `App\Models\Reservation`

## Status model and allowed transitions (by code paths)

Statuses are defined as constants in `app/Models/Reservation.php`:

- `email_verification_pending`
- `pending_approval`
- `approved`
- `rejected`
- `cancelled`

### Observed transitions

- **Create reservation**: (none) → `email_verification_pending`
  - `ReservationController::store`
- **Confirm email (valid)**: `email_verification_pending` → `pending_approval`
  - `ReservationController::confirmEmail`
- **Confirm email (expired)**: `email_verification_pending` → `rejected`
  - `ReservationController::confirmEmail`
- **Admin approve**: `pending_approval` → `approved`
  - `Admin\ReservationController::approve`
- **Admin reject**:
  - `pending_approval` → `rejected`
  - `email_verification_pending` → `rejected`
  - `Admin\ReservationController::reject`
- **Admin cancel**: (any status except already cancelled) → `cancelled`
  - `Admin\ReservationController::cancel`
- **Admin override**:
  - If `pending_approval`: `pending_approval` → `approved`
  - Otherwise: no status change (still logs override and sends approval mail)
  - `Admin\ReservationController::override`

Missing/unclear transitions:

- No user-initiated cancellation route exists (`routes/api.php`).
- No “verified” status exists; verification is recorded via `verified_at` timestamp.

## Eligibility rules

### Allowed email domains

- Only `@xu.edu.ph` and `@my.xu.edu.ph` are allowed (`User::isAllowedDomain`).

### Who can create reservations

Enforced in `StoreReservationRequest::authorize()`:

- Must be authenticated AND `user()->canManageReservations()`.
- `Role::canManageReservations()` returns `!$this->isStudentAssistant()`.

Implication:

- `student_assistant` cannot create reservations.
- `student`, `faculty`, `staff`, `librarian`, `admin` can create reservations (based on current `RoleSeeder` slugs and `Role::canManageReservations()` logic).

## Time rules

### Start/end time constraints

Validation rules in `StoreReservationRequest`:

- `start_at` must be in the future: `after:now`
- `end_at` must be after `start_at`: `after:start_at`

There are no rules in code for:

- Minimum/maximum duration
- Business hours (e.g., library open/close)
- Maximum reservations per user/day/week

These constraints are **not found in code**.

## Conflict detection logic

Implemented in `StoreReservationRequest::withValidator()`:

- Conflict check filters existing reservations:
  - same `space_id`
  - status in:
    - `approved`
    - `pending_approval`
    - `email_verification_pending`
- Overlap conditions:
  - existing `start_at` between requested `[start_at, end_at]`
  - OR existing `end_at` between requested `[start_at, end_at]`
  - OR existing reservation fully covers requested slot (`start_at <= requested.start_at` and `end_at >= requested.end_at`)

If a conflict exists:

- Adds a validation error at key `slot` with message “Selected time slot is not available.”

Important implication:

- Reservations that are `rejected` or `cancelled` do not block availability.
- Reservations pending email verification do block availability.

## Email verification rules

Implemented in `ReservationController::store` and `ReservationController::confirmEmail`:

- On creation:
  - `verification_token` is set to a random 64-character string
  - `verification_expires_at` is now + 24 hours
- Confirmation endpoint:
  - Requires token size 64
  - Only works if reservation status is `email_verification_pending`
  - If expired, marks reservation `rejected`

## Approval rules

### Admin requirement

Admin endpoints require `role:admin` middleware:

- Alias `role` is registered in `bootstrap/app.php`
- Middleware implementation: `App\Http\Middleware\EnsureUserHasRole`

### Approval constraints

- `approve()` only applies to reservations where `status === pending_approval` else 422.

### Reservation number issuance

- On approve and on override (pending_approval):
  - `reservation_number` is generated as `RES-` + 8 random chars via `Str::random(8)`
  - Saved in `reservations.reservation_number` (unique in DB).

No verification step requiring the reservation number is implemented in API routes; usage is shown in email template and in UI labels only.

## Availability calculation rules

Implemented in `AvailabilityController::index`:

- Only includes spaces where `is_active=true`.
- Uses `whereDate('start_at', $date)` to select reservations on the given date:
  - This means reservations spanning midnight could be missed or misrepresented (no spanning logic in code).
- Treats these statuses as blocking:
  - `approved`, `pending_approval`, `email_verification_pending`

## Hardcoded values and policy decisions

- OTP expiry: **10 minutes** (`AuthController::login`, `resendOtp`)
- Reservation email confirmation link expiry: **24 hours** (`ReservationController::store`)
- Reservation number format: `RES-` + 8 random uppercase characters (`Admin\ReservationController::approve`, `override`)
- Domain → role mapping:
  - `@xu.edu.ph` → `faculty`
  - `@my.xu.edu.ph` → `student`
  (`User::getRoleSlugFromEmail`)

