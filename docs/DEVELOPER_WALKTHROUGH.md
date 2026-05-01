## Developer walkthrough (step-by-step, tied to code)

This document walks through how the system works internally using **exact file paths and method names** from this repository.

---

## 1) What happens when the app starts (Laravel bootstrap)

### Application boot

The app is bootstrapped in:

- `bootstrap/app.php`

Key entry sequence (high level, as configured in this repo):

1. `Application::configure(basePath: dirname(__DIR__))`
2. `->withRouting(...)` wires route files:
   - web: `routes/web.php`
   - api: `routes/api.php`
   - commands: `routes/console.php`
   - health: `'/up'`
3. `->withMiddleware(function (Middleware $middleware): void { ... })` registers middleware aliases:
   - alias `'role' => \App\Http\Middleware\EnsureUserHasRole::class`
4. `->create()` returns the configured application instance.

### SPA shell view

The SPA container HTML is:

- `resources/views/app.blade.php`

It loads frontend assets in two ways:

- If `public/build/manifest.json` exists, it reads it and includes built CSS/JS from `public/build/*`.
- Otherwise it uses Vite dev integration:
  - `@vite(['resources/css/app.css', 'resources/js/app.jsx'])`

React mounts into `<div id="root">`.

---

## 2) How routes are loaded

Route files are registered in `bootstrap/app.php` via `->withRouting(...)`.

### Web routes (serve SPA for all paths)

File:

- `routes/web.php`

Route:

- `GET /{any?}` → closure returning `view('app')`
  - `->where('any', '.*')` makes this a catch-all

Effect:

- Any browser URL (including `/admin/reports`, `/my-reservations`, etc.) returns the SPA shell (`resources/views/app.blade.php`) and React Router decides what page to render.

### API routes (JSON endpoints)

File:

- `routes/api.php`

All API endpoints are defined here and are requested by the React app via Axios with `baseURL: '/api'` (`resources/js/api.js`).

---

## 3) How middleware is applied

Middleware application is visible in two places in this repo:

1. Route definitions in `routes/api.php`
2. Middleware alias registration in `bootstrap/app.php`

### Sanctum auth middleware: `auth:sanctum`

Applied in `routes/api.php`:

```php
Route::middleware('auth:sanctum')->group(function () {
    // /me, /logout, /reservations (GET/POST), /reservations/{reservation}
});
```

Effect:

- These endpoints require a valid Bearer token issued by Sanctum (see AuthController token creation below).

### Custom role middleware: `role:admin`

Registration:

- `bootstrap/app.php` registers alias:
  - `'role' => \App\Http\Middleware\EnsureUserHasRole::class`

Use:

- `routes/api.php`:
  - `Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(...)`

Implementation:

- `app/Http/Middleware/EnsureUserHasRole.php`
  - Method: `EnsureUserHasRole::handle(Request $request, Closure $next, string ...$roles): Response`
  - Checks:
    - If no user: returns `401 {"message":"Unauthenticated."}`
    - If `$user->role` missing: returns `403 {"message":"User has no role assigned."}`
    - If `$user->role->slug` not in `$roles`: returns `403 {"message":"Insufficient permissions."}`
    - Else allows request to proceed.

---

## 4) How authentication happens (backend + frontend)

### Backend authentication endpoints and logic

All auth endpoints are in:

- `app/Http/Controllers/Api/AuthController.php`

#### `POST /api/login` → `AuthController::login(LoginRequest $request): JsonResponse`

Route:

- `routes/api.php`:
  - `Route::post('/login', [AuthController::class, 'login']);`

Validation:

- `app/Http/Requests/Api/LoginRequest.php`
  - Method: `LoginRequest::rules()`
  - Rules:
    - `email` required email
    - `password` required string
    - `name` nullable string max 255

Domain restriction & role mapping:

- `app/Models/User.php`
  - `User::isAllowedDomain(string $email): bool`
  - `User::getRoleSlugFromEmail(string $email): ?string`
    - `@xu.edu.ph` → `'faculty'`
    - `@my.xu.edu.ph` → `'student'`

Flow:

1. Lowercases email: `$email = strtolower($request->input('email'));`
2. If disallowed domain: returns 422 with message.
3. Finds user by `User::where('email', $email)->first()`.
4. If user does not exist:
   - derives `$roleSlug` from `User::getRoleSlugFromEmail($email)`
   - finds role: `Role::where('slug', $roleSlug)->first()` (`app/Models/Role.php`)
   - creates user with:
     - `role_id = $role->id`
     - `is_activated = false`
     - hashed password: `Hash::make(...)`
5. If user exists:
   - checks password: `Hash::check($request->input('password'), $user->password)`; else 401.
6. If user is not activated:
   - generates OTP: 6-digit string
   - stores OTP in DB: `$user->update(['otp'=>..., 'otp_expires_at'=>now()->addMinutes(10)])`
   - sends mail: `Mail::to($user->email)->send(new OtpMail($otp))`
   - returns JSON: `{ requires_otp: true }`
7. If activated:
   - issues Sanctum token: `$user->createToken('auth')->plainTextToken`
   - returns token + user (with role loaded): `$user->load('role')`

OTP mail:

- `app/Mail/OtpMail.php` (`OtpMail::content()` uses view `emails.otp`)
- `resources/views/emails/otp.blade.php`

#### `POST /api/otp/verify` → `AuthController::verifyOtp(VerifyOtpRequest $request): JsonResponse`

Route:

- `routes/api.php`:
  - `Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);`

Validation:

- `app/Http/Requests/Api/VerifyOtpRequest.php`
  - `VerifyOtpRequest::rules()`: `email` required email, `otp` required string size 6

Flow:

1. Finds user by email.
2. If user already activated:
   - still issues a token and returns `{ message: 'Already activated.', token, user }`
3. Compares OTP: `if ($user->otp !== $request->input('otp'))` → 422.
4. Checks expiration: `if ($user->otp_expires_at && $user->otp_expires_at->isPast())` → 422.
5. Activates:
   - sets `is_activated=true`
   - clears `otp` and `otp_expires_at`
6. Issues token and returns `{ message: 'Account activated.', token, user }`

#### `POST /api/otp/resend` → `AuthController::resendOtp(Request $request): JsonResponse`

Route:

- `routes/api.php`:
  - `Route::post('/otp/resend', [AuthController::class, 'resendOtp']);`

Flow:

1. Inline validates: `$request->validate(['email' => 'required|email']);`
2. Finds user; returns 422 if not found OR already activated.
3. Generates OTP, sets expiry (+10 min), sends `OtpMail`, returns `{ message: 'OTP resent...' }`.

#### Authenticated identity endpoints

Protected by `auth:sanctum` (`routes/api.php` group):

- `GET /api/me` → `AuthController::me(Request $request)`
  - returns `response()->json($request->user()->load('role'))`
- `POST /api/logout` → `AuthController::logout(Request $request)`
  - deletes current token: `$request->user()->currentAccessToken()->delete()`

### Frontend authentication wiring

#### API client and Bearer token header

File:

- `resources/js/api.js`

Key behavior:

- Axios `baseURL: '/api'`
- Request interceptor:
  - reads `localStorage.getItem('token')`
  - sets `config.headers.Authorization = \`Bearer ${token}\``
- Response interceptor:
  - on `401`, removes `token` and `user` from local storage and redirects to `/login`

#### Auth state storage & `/me` hydration

File:

- `resources/js/contexts/AuthContext.jsx`

Key behavior:

- On mount, if a token exists, calls `api.get('/me')` and stores user in state + local storage.
- `login(token, userData)` saves both token and user.
- `logout()` calls `api.post('/logout')` (best-effort), then clears local storage and state.

#### React route gating

File:

- `resources/js/app.jsx`

Component:

- `PrivateRoute({ children, adminOnly })`
  - If no `user`: redirects to `/login`
  - If `adminOnly` and `user.role?.slug !== 'admin'`: redirects to `/`

Note: real authorization is enforced on backend via middleware and request authorization; the frontend gating only controls UI navigation.

---

## 5) How a reservation is submitted (frontend → backend)

### Frontend page and endpoint call

File:

- `resources/js/pages/ReservationForm.jsx`

Workflow:

1. Loads spaces with `api.get('/spaces')` (calls `GET /api/spaces`).
2. On submit:
   - Builds ISO-like strings:
     - `start_at = ${date}T${startTime}:00`
     - `end_at = ${date}T${endTime}:00`
   - Sends reservation request:
     - `api.post('/reservations', { space_id: Number(spaceIdVal), start_at, end_at, purpose })`

### Backend route and controller

Route:

- `routes/api.php` (within `auth:sanctum` group):
  - `Route::post('/reservations', [App\Http\Controllers\Api\ReservationController::class, 'store']);`

Controller:

- `app/Http/Controllers/Api/ReservationController.php`
  - Method: `ReservationController::store(StoreReservationRequest $request): JsonResponse`

Flow inside `store(...)`:

1. `$data = $request->validated();`
2. Adds:
   - `user_id = $request->user()->id`
   - `status = Reservation::STATUS_EMAIL_VERIFICATION_PENDING`
   - `verification_token = Str::random(64)`
   - `verification_expires_at = now()->addHours(24)`
3. Creates record:
   - `Reservation::create($data)`
4. Sends verification email to the user:
   - `Mail::to($request->user()->email)->send(new ReservationVerificationMail($reservation))`
5. Returns 201 with message and reservation data.

Reservation email:

- Mailable: `app/Mail/ReservationVerificationMail.php`
- Template: `resources/views/emails/reservation-verify.blade.php`
  - Confirmation URL base: `config('app.frontend_url', config('app.url'))`
  - `config/app.php` defines:
    - `'frontend_url' => env('FRONTEND_URL', env('APP_URL'))`

---

## 6) How conflicts are checked (overlap detection)

Conflict detection is enforced during validation of the create-reservation request.

File:

- `app/Http/Requests/Api/StoreReservationRequest.php`

### Authorization gate (who can submit)

- `StoreReservationRequest::authorize(): bool`
  - returns `($this->user() && $this->user()->canManageReservations())`

This depends on:

- `app/Models/User.php`:
  - `User::canManageReservations(): bool`
- `app/Models/Role.php`:
  - `Role::canManageReservations(): bool` returns `!$this->isStudentAssistant()`

### Validation rules (time and required fields)

- `StoreReservationRequest::rules(): array`
  - `space_id` required and exists in `spaces`
  - `start_at` required date `after:now`
  - `end_at` required date `after:start_at`
  - `purpose` nullable string max 1000

### Overlap/conflict logic

- `StoreReservationRequest::withValidator($validator): void`
  - Adds an `after(...)` hook that runs a query:

1. Filters by same `space_id`.
2. Only considers reservations in statuses:
   - `Reservation::STATUS_APPROVED`
   - `Reservation::STATUS_PENDING_APPROVAL`
   - `Reservation::STATUS_EMAIL_VERIFICATION_PENDING`
3. Overlap where-clause:
   - existing `start_at` between requested range
   - OR existing `end_at` between requested range
   - OR existing fully covers requested range
4. If any exists:
   - `$validator->errors()->add('slot', 'Selected time slot is not available.');`

Important maintenance note:

- This is **application-level** conflict checking. There is no database constraint to prevent overlap if two requests race.

---

## 7) How approval / rejection is processed (admin)

### Admin route protection

File:

- `routes/api.php`

Admin route group:

- middleware: `['auth:sanctum', 'role:admin']`
- prefix: `/admin`

Role check middleware:

- `app/Http/Middleware/EnsureUserHasRole.php` (`EnsureUserHasRole::handle(...)`)

### Admin approval/rejection controller

File:

- `app/Http/Controllers/Api/Admin/ReservationController.php`

#### List reservations

- `Admin\ReservationController::index(Request $request): JsonResponse`
  - Loads `Reservation::with(['user','space','approver'])->latest()`
  - Optional filters: `status`, `from`, `to`
  - Returns `paginate(20)`

#### Approve

- Route: `POST /api/admin/reservations/{reservation}/approve`
- Method: `Admin\ReservationController::approve(Request $request, Reservation $reservation): JsonResponse`

Steps:

1. Preconditions:
   - If `$reservation->status !== Reservation::STATUS_PENDING_APPROVAL`, returns 422.
2. Generates reservation number:
   - `$reservationNumber = 'RES-' . strtoupper(Str::random(8));`
3. Updates reservation:
   - `status = approved`
   - `reservation_number = ...`
   - `approved_by = $request->user()->id`
   - `approved_at = now()`
4. Writes log row:
   - `ReservationLog::create([... 'action' => 'approve', 'notes' => $request->input('notes')])`
5. Sends mail:
   - `ReservationApprovedMail` to `$reservation->user->email`
6. Returns JSON with updated reservation.

#### Reject

- Route: `POST /api/admin/reservations/{reservation}/reject`
- Method: `Admin\ReservationController::reject(Request $request, Reservation $reservation): JsonResponse`

Steps:

1. Preconditions:
   - Only allowed if status in `[pending_approval, email_verification_pending]`, else 422.
2. Updates reservation:
   - `status = rejected`
   - `rejected_reason = $request->input('reason')`
3. Writes log row:
   - `ReservationLog::create([... 'action' => 'reject', 'notes' => $request->input('reason')])`
4. Sends mail:
   - `ReservationRejectedMail($reservation->fresh('space'), $request->input('reason'))`

#### Cancel

- Route: `POST /api/admin/reservations/{reservation}/cancel`
- Method: `Admin\ReservationController::cancel(Request $request, Reservation $reservation): JsonResponse`

Steps:

1. If already cancelled → 422.
2. Updates reservation: `status = cancelled`.
3. Writes log row: action `cancel`, notes from `$request->input('notes')`.
4. Sends mail using `ReservationRejectedMail` with reason = notes or default string.

#### Override

- Route: `POST /api/admin/reservations/{reservation}/override`
- Method: `Admin\ReservationController::override(Request $request, Reservation $reservation): JsonResponse`

Steps:

1. Validates `notes` max 500.
2. If status is `pending_approval`, force-approves (same fields as approve).
3. Writes log row: action `override`.
4. Sends `ReservationApprovedMail`.

---

## 8) How data is written to logs (audit trail)

Logging/audit trail for reservation actions is implemented as a database table + model:

### Storage

- Migration: `database/migrations/2025_02_21_000005_create_reservation_logs_table.php`
  - Table: `reservation_logs`
  - Columns: `reservation_id`, `admin_id`, `action`, `notes`, timestamps
  - FKs:
    - `reservation_id` → `reservations.id` (cascadeOnDelete)
    - `admin_id` → `users.id` (cascadeOnDelete)

### Model

- `app/Models/ReservationLog.php`
  - `$fillable = ['reservation_id', 'admin_id', 'action', 'notes']`
  - Relationships:
    - `ReservationLog::reservation()`
    - `ReservationLog::admin()` (belongsTo `User` via `admin_id`)

### Write points (where logs are created)

All writes happen in:

- `app/Http/Controllers/Api/Admin/ReservationController.php`
  - `approve(...)` → creates action `approve`
  - `reject(...)` → creates action `reject`
  - `cancel(...)` → creates action `cancel`
  - `override(...)` → creates action `override`

### Read points

- `Admin\ReservationController::show(Reservation $reservation)`
  - loads `logs.admin`:
    - `$reservation->load(['user', 'space', 'approver', 'logs.admin']);`

---

## 9) How reservation email confirmation works (public link → backend update)

### Frontend confirmation page

- `resources/js/pages/ConfirmReservation.jsx`
  - Reads `token` from query string.
  - Calls `api.post('/reservations/confirm-email', { token })`.

### Backend confirmation endpoint

Route:

- `routes/api.php`:
  - `Route::post('/reservations/confirm-email', [ReservationController::class, 'confirmEmail']);` (public)

Controller:

- `app/Http/Controllers/Api/ReservationController.php`
  - `ReservationController::confirmEmail(Request $request): JsonResponse`

Flow:

1. Validates token: required string size 64.
2. Finds reservation where:
   - `verification_token` matches
   - `status` is `Reservation::STATUS_EMAIL_VERIFICATION_PENDING`
3. If not found: 422 “Invalid or expired confirmation link.”
4. If expired:
   - sets status to `rejected`
   - returns 422 “Confirmation link has expired.”
5. Else:
   - sets status `pending_approval`
   - sets `verified_at = now()`
   - clears token + expiry fields
6. Returns reservation JSON.

Note:

- There is a comment `// Notify admin (could dispatch job to send email to admins)` but there is no implementation; admin notification is **not present** in code.

---

## 10) How frontend pages connect to backend endpoints (exact call sites)

This is the “wiring map” between React pages and API endpoints in `routes/api.php`.

### Authentication

- `resources/js/pages/Login.jsx`
  - `POST /api/login` → `App\Http\Controllers\Api\AuthController::login`
- `resources/js/pages/OTPVerify.jsx`
  - `POST /api/otp/verify` → `AuthController::verifyOtp`
  - `POST /api/otp/resend` → `AuthController::resendOtp`
- `resources/js/contexts/AuthContext.jsx`
  - `GET /api/me` → `AuthController::me`
  - `POST /api/logout` → `AuthController::logout`

### Public data

- `resources/js/pages/Calendar.jsx`
  - `GET /api/spaces` → `App\Http\Controllers\Api\SpaceController::index`
  - `GET /api/availability` → `App\Http\Controllers\Api\AvailabilityController::index`

### Reservations (user)

- `resources/js/pages/ReservationForm.jsx`
  - `POST /api/reservations` → `App\Http\Controllers\Api\ReservationController::store`
- `resources/js/pages/MyReservations.jsx`
  - `GET /api/reservations` → `ReservationController::index`
- `resources/js/pages/ConfirmReservation.jsx`
  - `POST /api/reservations/confirm-email` → `ReservationController::confirmEmail`

### Reservations (admin)

- `resources/js/pages/admin/AdminReservations.jsx`
  - `GET /api/admin/reservations` → `App\Http\Controllers\Api\Admin\ReservationController::index`
  - `POST /api/admin/reservations/{id}/approve` → `Admin\ReservationController::approve`
  - `POST /api/admin/reservations/{id}/reject` → `Admin\ReservationController::reject`
  - `POST /api/admin/reservations/{id}/cancel` → `Admin\ReservationController::cancel`

### Reports (admin)

- `resources/js/pages/admin/AdminReports.jsx`
  - `GET /api/admin/reports` → `App\Http\Controllers\Api\Admin\ReportController::index`
  - `GET /api/admin/reports/export?format=pdf` → `ReportController::export`
    - PDF view: `resources/views/reports/export.blade.php`

---

## 11) Where each important piece of logic lives (quick index)

- **Boot + middleware alias**: `bootstrap/app.php`
- **SPA catch-all**: `routes/web.php`
- **API route definitions**: `routes/api.php`
- **Role middleware implementation**: `app/Http/Middleware/EnsureUserHasRole.php` (`handle`)
- **Auth controller**: `app/Http/Controllers/Api/AuthController.php`
- **Auth validation**:
  - `app/Http/Requests/Api/LoginRequest.php`
  - `app/Http/Requests/Api/VerifyOtpRequest.php`
- **Reservation controller (user)**: `app/Http/Controllers/Api/ReservationController.php`
- **Reservation creation validation + conflict check**: `app/Http/Requests/Api/StoreReservationRequest.php` (`authorize`, `rules`, `withValidator`)
- **Reservation model and status constants**: `app/Models/Reservation.php`
- **Admin reservation actions + log writes**: `app/Http/Controllers/Api/Admin/ReservationController.php`
- **Reservation audit log model**: `app/Models/ReservationLog.php`
- **Reservation audit log migration**: `database/migrations/2025_02_21_000005_create_reservation_logs_table.php`
- **Availability calculation**: `app/Http/Controllers/Api/AvailabilityController.php` (`index`)
- **Spaces listing**: `app/Http/Controllers/Api/SpaceController.php` (`index`)
- **Reports + PDF export**: `app/Http/Controllers/Api/Admin/ReportController.php` (`index`, `export`)
- **Frontend routing**: `resources/js/app.jsx`
- **Frontend auth storage**: `resources/js/contexts/AuthContext.jsx`
- **Frontend API client**: `resources/js/api.js`

