## Authentication overview

### Mechanism

- **API auth** uses **Laravel Sanctum personal access tokens**.
  - Tokens are created via `User::createToken('auth')->plainTextToken` in:
    - `App\Http\Controllers\Api\AuthController::login`
    - `App\Http\Controllers\Api\AuthController::verifyOtp`
  - Protected routes use `Route::middleware('auth:sanctum')` (`routes/api.php`).

### Token transport

- Frontend stores the token in `localStorage` key `token` and sends it in `Authorization: Bearer <token>` via Axios request interceptor (`resources/js/api.js`).
- On `401` responses, frontend clears token/user and redirects to `/login` (`resources/js/api.js`).

### Account creation / login flow

**Endpoint**: `POST /api/login` (`App\Http\Controllers\Api\AuthController::login`)

- Validates input via `App\Http\Requests\Api\LoginRequest`.
- Allowed email domains are enforced by `User::isAllowedDomain()` which only allows:
  - `@xu.edu.ph`
  - `@my.xu.edu.ph`
  (`User::getRoleSlugFromEmail` / `User::isAllowedDomain` in `app/Models/User.php`)
- If a user does not exist:
  - User is auto-created
  - Role is assigned from `roles.slug` based on domain:
    - `@xu.edu.ph` → `'faculty'`
    - `@my.xu.edu.ph` → `'student'`
  - If role slug not found in DB, returns 422 (`Role::where('slug', $roleSlug)->first()` in `AuthController::login`)
- If user exists:
  - Password is checked with `Hash::check(...)`

### OTP-based activation

If `is_activated` is false, login triggers OTP:

- OTP is a **6-digit string** stored in DB column `users.otp` and expires in 10 minutes (`users.otp_expires_at`).
- OTP email is sent immediately using `Mail::to(...)->send(new OtpMail($otp))` (`AuthController::login`, `AuthController::resendOtp`).
- Verification endpoint: `POST /api/otp/verify` (`AuthController::verifyOtp`)
  - Requires `email` and `otp` (`VerifyOtpRequest`)
  - Checks OTP equality and expiration
  - Sets `is_activated=true`, clears otp fields, issues Sanctum token

## Authorization overview

Authorization in this project is enforced through:

- **Route middleware**: `auth:sanctum` and a custom role middleware alias `role`.
- **Form request authorization**: `StoreReservationRequest::authorize()`.
- **In-method checks**: `ReservationController::show` (owner/admin check).
- **Frontend route gating**: React `PrivateRoute` and `adminOnly` gating in `resources/js/app.jsx` (note: frontend gating is not a security boundary; backend checks exist for admin APIs).

## Guards, providers, and sessions

### Auth config

From `config/auth.php`:

- Default guard: `env('AUTH_GUARD', 'web')`
- Guards defined: only `web` with driver `session`
- Provider: `users` uses Eloquent model `env('AUTH_MODEL', App\Models\User::class)`

API authentication in practice relies on Sanctum middleware (`auth:sanctum`) despite only `web` guard being configured in this file; Sanctum provides its own guard integration (Sanctum config file is **not found in code** because `config/sanctum.php` was not present in this repo snapshot).

### Session storage

- `.env.example` sets `SESSION_DRIVER=database` which uses the `sessions` table created by `database/migrations/0001_01_01_000000_create_users_table.php`.

## Middleware and enforcement points

### `auth:sanctum`

Applied in `routes/api.php` to protect:

- `/api/me`
- `/api/logout`
- `/api/reservations` (GET, POST)
- `/api/reservations/{reservation}`
- Admin routes group (combined with role middleware)

### Role middleware: `role:admin`

Admin route group uses:

- `Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')...` (`routes/api.php`)

The middleware alias is registered in `bootstrap/app.php`:

- `Middleware::alias(['role' => \App\Http\Middleware\EnsureUserHasRole::class])`

Role enforcement implementation:

- `App\Http\Middleware\EnsureUserHasRole::handle(Request $request, Closure $next, string ...$roles)`
  - 401 if no authenticated user
  - 403 if user has no role relationship
  - 403 if `user->role->slug` not in roles list

## Role handling and permissions

Roles are stored in `roles` table and seeded in `database/seeders/RoleSeeder.php`.

Role checks in code:

- `Role::isAdmin()` → `slug === 'admin'`
- `Role::isStudentAssistant()` → `slug === 'student_assistant'`
- `Role::canManageReservations()` → `!isStudentAssistant()`
- `User::{isAdmin,isStudentAssistant,canManageReservations}` delegate to `Role`

Enforced permissions:

- **Admin-only**: all `/api/admin/*` routes via middleware `role:admin`
- **Reservation creation**: denied for `student_assistant` via `StoreReservationRequest::authorize()`
- **Reservation detail access**:
  - `ReservationController::show` denies access unless reservation owner or admin (`User::isAdmin()`)

## Security weaknesses / missing checks (based on code)

- **OTP stored in plaintext**: `users.otp` stores the code directly (no hashing). Anyone with DB read access can see active OTPs.
- **User creation without password confirmation/strength checks**: `LoginRequest` only requires `password` be `string` (no min length or complexity enforced).
- **Admin notification after email confirmation is not implemented**:
  - `ReservationController::confirmEmail` contains comment “Notify admin …” but no actual notification dispatch occurs.
- **`Role::canManageReservations()` logic**:
  - It returns `!isStudentAssistant()` which means **admin**, **student**, **faculty**, **staff**, **librarian** can create reservations. If the intended rule differs, it is **unclear from repository**.
- **Conflict detection is only at validation layer**:
  - Overlap checks run in `StoreReservationRequest::withValidator` only; concurrent requests could race without a DB-level constraint/locking strategy.

