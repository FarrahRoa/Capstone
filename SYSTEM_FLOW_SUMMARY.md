## End-to-end technical walkthrough (from repository)

This walkthrough follows the system as implemented in Laravel controllers and the React SPA.

## 1) App boot and routing

- Laravel is configured in `bootstrap/app.php` with:
  - Web routes: `routes/web.php`
  - API routes: `routes/api.php`
  - A health check endpoint at `/up`
  - Middleware alias `role` → `App\Http\Middleware\EnsureUserHasRole`

### Web: SPA entry

- Any browser path `GET /{any?}` returns `view('app')` (`routes/web.php`).
- The HTML shell in `resources/views/app.blade.php` loads built assets (via `public/build/manifest.json`) or dev assets (via `@vite(...)`) and provides `<div id="root">`.

### React mounting and client-side routes

- React mounts from `resources/js/app.jsx` and defines routes:
  - `/login`, `/otp`, `/confirm-reservation` (public client pages)
  - `/`, `/reserve`, `/my-reservations` (private)
  - `/admin/reservations`, `/admin/reports` (private + admin-only)

## 2) Login and activation (OTP)

### Frontend

- User goes to `/login` (`resources/js/pages/Login.jsx`) and submits email + password (and optional name).
- The page calls `POST /api/login`.

### Backend: `POST /api/login`

Controller: `App\Http\Controllers\Api\AuthController::login`

- Lowercases the email.
- Enforces allowed domains using `User::isAllowedDomain()`:
  - only `@xu.edu.ph` and `@my.xu.edu.ph`
- If user is new:
  - picks role slug via `User::getRoleSlugFromEmail()` (`faculty` vs `student`)
  - finds role record in `roles`
  - creates user with `is_activated=false`
- If existing user:
  - verifies password via `Hash::check`

If not activated:

- generates 6-digit OTP, sets `users.otp` and `users.otp_expires_at = now + 10 minutes`
- sends `App\Mail\OtpMail` using `resources/views/emails/otp.blade.php`
- returns `{ requires_otp: true }`

If activated:

- issues a Sanctum personal access token: `createToken('auth')->plainTextToken`
- returns `{ token, token_type: 'Bearer', user }`

### Frontend OTP verify

- If `requires_otp`, the UI routes to `/otp` and calls `POST /api/otp/verify` from `resources/js/pages/OTPVerify.jsx`.
- On success it stores token and user in `localStorage` via `AuthContext` (`resources/js/contexts/AuthContext.jsx`).

## 3) Authenticated session behavior

- Axios sends `Authorization: Bearer <token>` to `/api/*` (`resources/js/api.js`).
- On app load, `AuthContext` calls `GET /api/me` to hydrate `user` from backend.
- Logout calls `POST /api/logout` and removes local storage token/user.

## 4) Viewing spaces and availability

### Spaces list

- UI calls `GET /api/spaces` (public) to list active spaces:
  - `App\Http\Controllers\Api\SpaceController::index` returns spaces where `is_active=true`.

### Availability calendar

- Calendar UI (`resources/js/pages/Calendar.jsx`) calls:
  - `GET /api/availability?date=YYYY-MM-DD` (optional `space_id`)

Backend: `App\Http\Controllers\Api\AvailabilityController::index`

- Loads active spaces.
- For each space, fetches reservations on that date where status in:
  - `approved`
  - `pending_approval`
  - `email_verification_pending`
- Returns `reserved_slots` (id, start_at, end_at, status).

## 5) Creating a reservation

### Frontend

- User navigates to `/reserve` (`ReservationForm.jsx`), chooses room/date/time, submits.
- UI posts `POST /api/reservations`.

### Backend validation + creation

Route: `POST /api/reservations` (middleware `auth:sanctum`)

- Controller: `App\Http\Controllers\Api\ReservationController::store`
- Validated by `App\Http\Requests\Api\StoreReservationRequest`:
  - Authorization: `user()->canManageReservations()` (blocks `student_assistant`)
  - Time constraints and overlap detection against existing reservations (status approved/pending_approval/email_verification_pending)

If valid:

- Creates `Reservation` with status `email_verification_pending`
- Generates `verification_token` (64 chars) and expiry now + 24 hours
- Sends `App\Mail\ReservationVerificationMail`
  - Template `resources/views/emails/reservation-verify.blade.php`
  - Link points to frontend route `/confirm-reservation?token=...`

## 6) Email confirmation → pending approval

### Frontend confirmation page

- Link opens `/confirm-reservation?token=...` (React page `ConfirmReservation.jsx`)
- Page posts `POST /api/reservations/confirm-email` (public endpoint)

### Backend confirmation

Controller: `ReservationController::confirmEmail`

- Validates token and finds matching reservation still in `email_verification_pending`.
- If token expired:
  - sets status to `rejected`
  - returns 422
- Else:
  - sets status to `pending_approval`
  - sets `verified_at`
  - clears token fields

Admin notification is not implemented (comment only).

## 7) Admin approval and logging

### Admin access enforcement

- Admin API routes are under `/api/admin/*` and use middleware:
  - `auth:sanctum`
  - `role:admin`
- `role` alias maps to `App\Http\Middleware\EnsureUserHasRole` in `bootstrap/app.php`.

### Admin reviews reservations

Frontend:

- `/admin/reservations` UI calls `GET /api/admin/reservations` (optionally filtered by status).

Backend:

- `Admin\ReservationController::index` returns paginated reservations with `user`, `space`, `approver`.

### Admin actions and audit logs

When admin takes actions, backend writes `reservation_logs` entries:

- Approve:
  - `POST /api/admin/reservations/{id}/approve`
  - Preconditions: status must be `pending_approval`
  - Sets approved fields + reservation number
  - Writes `ReservationLog` action `approve`
  - Sends approval email (`ReservationApprovedMail`)

- Reject:
  - `POST /api/admin/reservations/{id}/reject`
  - Preconditions: status is `pending_approval` or `email_verification_pending`
  - Sets status rejected + rejected_reason
  - Writes `ReservationLog` action `reject`
  - Sends rejected email (`ReservationRejectedMail`)

- Cancel:
  - `POST /api/admin/reservations/{id}/cancel`
  - Sets status cancelled
  - Writes `ReservationLog` action `cancel`
  - Sends mail using `ReservationRejectedMail` (cancellation messaging)

- Override:
  - `POST /api/admin/reservations/{id}/override`
  - Logs `override` and sends approval email; only force-approves if status is `pending_approval`.

### Admin reports

Frontend:

- `/admin/reports` calls `GET /api/admin/reports` and can request `GET /api/admin/reports/export?format=pdf`.

Backend:

- `Admin\ReportController::index` computes metrics over a period.
- `export` returns JSON or PDF (via DomPDF if installed).

