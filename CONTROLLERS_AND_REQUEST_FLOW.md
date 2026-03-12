## Overview of request flow

Typical flow patterns in this repo:

- **React UI** → Axios (`resources/js/api.js`) → **Laravel route** (`routes/api.php`) → **Controller method** → **Eloquent model(s)** → JSON response.
- Validation is implemented via:
  - `FormRequest` classes for some endpoints (`LoginRequest`, `VerifyOtpRequest`, `StoreReservationRequest`)
  - `Request::validate(...)` inline in controllers for other endpoints (admin actions, report filters, confirm-email token, resend OTP).

## Controllers inventory (from `app/Http/Controllers`)

Found controllers:

- `app/Http/Controllers/Api/AuthController.php` (`App\Http\Controllers\Api\AuthController`)
- `app/Http/Controllers/Api/SpaceController.php` (`App\Http\Controllers\Api\SpaceController`)
- `app/Http/Controllers/Api/AvailabilityController.php` (`App\Http\Controllers\Api\AvailabilityController`)
- `app/Http/Controllers/Api/ReservationController.php` (`App\Http\Controllers\Api\ReservationController`)
- `app/Http/Controllers/Api/Admin/ReservationController.php` (`App\Http\Controllers\Api\Admin\ReservationController`)
- `app/Http/Controllers/Api/Admin/ReportController.php` (`App\Http\Controllers\Api\Admin\ReportController`)
- `app/Http/Controllers/Controller.php` (base controller)

No other controllers were found in the provided glob results.

## `App\Http\Controllers\Api\AuthController`

**File**: `app/Http/Controllers/Api/AuthController.php`

### `login(LoginRequest $request): JsonResponse`

Route:

- `POST /api/login` (`routes/api.php`)

Flow:

- Validates with `App\Http\Requests\Api\LoginRequest`:
  - `email` required email
  - `password` required string
  - `name` optional string max 255
- Enforces allowed domain:
  - `User::isAllowedDomain($email)` else 422
- Loads user by `email`:
  - If missing: determines role slug from email domain and creates user with `role_id`, `is_activated=false`
  - If exists: checks password with `Hash::check` else 401
- If not activated:
  - generates OTP and sets `otp_expires_at` to now + 10 minutes
  - sends `OtpMail`
  - returns JSON `{ requires_otp: true }`
- Else:
  - issues Sanctum token `createToken('auth')`
  - returns JSON `{ token, token_type, user: user->load('role') }`

### `verifyOtp(VerifyOtpRequest $request): JsonResponse`

Route:

- `POST /api/otp/verify`

Flow:

- Validates with `VerifyOtpRequest`:
  - `email` required email
  - `otp` required string size 6
- Loads user by email; 404 if not found
- If already activated: issues token and returns “Already activated.”
- Checks OTP equality and expiry; returns 422 if invalid or expired
- Updates user: activated, clears OTP fields
- Issues Sanctum token and returns user + token

### `resendOtp(Request $request): JsonResponse`

Route:

- `POST /api/otp/resend`

Flow:

- Inline validation: `email` required email
- Loads user; returns 422 if not found or already activated
- Regenerates OTP and expiry (+10 minutes)
- Sends `OtpMail`

### `me(Request $request): JsonResponse`

Route:

- `GET /api/me` (middleware `auth:sanctum`)

Flow:

- Returns `request->user()->load('role')`

### `logout(Request $request): JsonResponse`

Route:

- `POST /api/logout` (middleware `auth:sanctum`)

Flow:

- Deletes current access token: `currentAccessToken()->delete()`

## `App\Http\Controllers\Api\SpaceController`

**File**: `app/Http/Controllers/Api/SpaceController.php`

### `index(): JsonResponse`

Route:

- `GET /api/spaces` (public)

Flow:

- Queries `Space::where('is_active', true)->orderBy('name')->get()`
- Returns JSON array of spaces

## `App\Http\Controllers\Api\AvailabilityController`

**File**: `app/Http/Controllers/Api/AvailabilityController.php`

### `index(Request $request): JsonResponse`

Route:

- `GET /api/availability` (public)

Validation:

- `space_id`: `required_without:date|nullable|exists:spaces,id`
- `date`: `required|date`

Note: The `required_without:date` on `space_id` is redundant because `date` is required; `space_id` is effectively optional.

Flow:

- Parses `date` into Carbon day start.
- Loads active spaces, optionally filtered by `space_id`.
- For each space, queries reservations for that date where status in:
  - `approved`
  - `pending_approval`
  - `email_verification_pending`
- Returns per-space list: `{ space, reserved_slots: [{id,start_at,end_at,status}] }`

## `App\Http\Controllers\Api\ReservationController`

**File**: `app/Http/Controllers/Api/ReservationController.php`

### `index(Request $request): JsonResponse`

Route:

- `GET /api/reservations` (auth)

Flow:

- Uses relationship `request->user()->reservations()`
- Eager loads `space` and `approver`
- Orders latest and paginates 20

### `show(Request $request, Reservation $reservation): JsonResponse`

Route:

- `GET /api/reservations/{reservation}` (auth)

Authorization:

- If reservation not owned by user and user is not admin: returns 403.

Flow:

- Loads relationships `space`, `user`, `approver`
- Returns reservation JSON

### `store(StoreReservationRequest $request): JsonResponse`

Route:

- `POST /api/reservations` (auth)

Validation & authorization:

- `StoreReservationRequest`:
  - denies `student_assistant`
  - enforces time and overlap rules

Flow:

- Creates `Reservation` with:
  - status `email_verification_pending`
  - verification token + expiry (+24h)
- Sends `ReservationVerificationMail` to current user
- Returns 201 with message and reservation payload

### `confirmEmail(Request $request): JsonResponse`

Route:

- `POST /api/reservations/confirm-email` (public)

Validation:

- `token`: required string size 64

Flow:

- Looks up reservation by token and status `email_verification_pending`
- If expired: sets status `rejected` and returns 422
- Else: updates to `pending_approval`, sets `verified_at`, clears token/expires, returns reservation

## `App\Http\Controllers\Api\Admin\ReservationController`

**File**: `app/Http/Controllers/Api/Admin/ReservationController.php`

Routes:

- All routes are under `/api/admin/*` and require `auth:sanctum` and `role:admin` (`routes/api.php`, `bootstrap/app.php`, `EnsureUserHasRole`)

### `index(Request $request): JsonResponse`

- `GET /api/admin/reservations`
- Optional filters:
  - `status`
  - `from` (whereDate start_at >= from)
  - `to` (whereDate start_at <= to)
- Paginates 20

### `show(Reservation $reservation): JsonResponse`

- `GET /api/admin/reservations/{reservation}`
- Loads: `user`, `space`, `approver`, `logs.admin`

### `approve(Request $request, Reservation $reservation): JsonResponse`

- `POST /api/admin/reservations/{reservation}/approve`
- Preconditions:
  - status must be `pending_approval` else 422
- Mutations:
  - status approved
  - reservation_number `RES-` + random 8
  - approved_by current admin
  - approved_at now
- Side-effects:
  - creates `ReservationLog` action `approve`
  - sends `ReservationApprovedMail`

### `reject(Request $request, Reservation $reservation): JsonResponse`

- `POST /api/admin/reservations/{reservation}/reject`
- Preconditions:
  - status in `pending_approval` or `email_verification_pending`
- Mutations:
  - status rejected
  - rejected_reason from request `reason`
- Side-effects:
  - creates `ReservationLog` action `reject`
  - sends `ReservationRejectedMail`

### `cancel(Request $request, Reservation $reservation): JsonResponse`

- `POST /api/admin/reservations/{reservation}/cancel`
- Preconditions:
  - cannot cancel if already cancelled
- Mutations:
  - status cancelled
- Side-effects:
  - creates `ReservationLog` action `cancel`
  - sends `ReservationRejectedMail` (used as cancellation mail)

### `override(Request $request, Reservation $reservation): JsonResponse`

- `POST /api/admin/reservations/{reservation}/override`
- Validates:
  - `notes` nullable string max 500
- Behavior:
  - If status is `pending_approval`, force-approves (status + reservation_number + approved fields)
  - Always logs action `override`
  - Sends `ReservationApprovedMail`

## `App\Http\Controllers\Api\Admin\ReportController`

**File**: `app/Http/Controllers/Api/Admin/ReportController.php`

Routes:

- `GET /api/admin/reports`
- `GET /api/admin/reports/export`

### `index(Request $request): JsonResponse`

Validation:

- `period`: in `monthly,quarterly,annual,custom`
- `from`: required_if period=custom, date
- `to`: required_if period=custom, date, after_or_equal:from

Flow:

- Computes date range using `resolveFrom()` / `resolveTo()`
- Loads reservations between `start_at` range with `user` and `space`
- Computes:
  - by college/office (all statuses)
  - student college & year-level stats (all statuses, filtered where user role slug === 'student')
  - room utilization by space (approved only)
  - peak hours (approved only)
  - average duration minutes (approved only)
  - average approval time minutes (approved only, requires verified_at and approved_at)
- Returns JSON payload containing the metrics

### `export(Request $request)`

Validation:

- Same period rules as index
- `format`: `pdf` or `json`

Flow:

- Reuses `index($request)->getData(true)` for data payload.
- If `format=json`: returns JSON payload.
- If `format=pdf`:
  - If DomPDF facade exists: renders `resources/views/reports/export.blade.php` and downloads a PDF.
  - Else: returns JSON payload (fallback).

## Form requests inventory

Found request validators:

- `App\Http\Requests\Api\LoginRequest`
- `App\Http\Requests\Api\VerifyOtpRequest`
- `App\Http\Requests\Api\StoreReservationRequest`

Duplicated logic / “fat controller” notes

- `Admin\ReservationController` repeats similar patterns for approve/reject/cancel/override (update status → create log → send mail). This could be extracted into a service, but no such service exists in the repo.
- Validation is inconsistent:
  - Some endpoints use `FormRequest` classes.
  - Admin endpoints accept `notes`/`reason` mostly without validation (except override notes max 500).

