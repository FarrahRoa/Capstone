## Overview

- **Backend API routes**: defined in `routes/api.php` and served under `/api/*` (default Laravel API routing).
- **Web routes**: `routes/web.php` serves the React SPA for all paths via `view('app')`.

This map lists routes explicitly found in code. If a controller method is not present in the repository, it is marked **not found in code** (not applicable here; all referenced methods were found).

## Web routes (SPA shell)

### SPA catch-all

- **GET** `/{any?}`
  - **File**: `routes/web.php`
  - **Handler**: closure returning `view('app')`
  - **Constraints**: `where('any', '.*')`
  - **Purpose**: hand off routing to React Router (`resources/js/app.jsx`)

## API routes (public)

### Authentication (public)

- **POST** `/api/login`
  - **Controller**: `App\Http\Controllers\Api\AuthController`
  - **Method**: `login(LoginRequest $request)`
  - **Middleware**: none (public)
  - **Auth required**: no

- **POST** `/api/otp/verify`
  - **Controller**: `App\Http\Controllers\Api\AuthController`
  - **Method**: `verifyOtp(VerifyOtpRequest $request)`
  - **Middleware**: none (public)
  - **Auth required**: no

- **POST** `/api/otp/resend`
  - **Controller**: `App\Http\Controllers\Api\AuthController`
  - **Method**: `resendOtp(Request $request)`
  - **Middleware**: none (public)
  - **Auth required**: no

### Spaces & availability (public)

- **GET** `/api/spaces`
  - **Controller**: `App\Http\Controllers\Api\SpaceController`
  - **Method**: `index()`
  - **Middleware**: none (public)
  - **Auth required**: no

- **GET** `/api/availability`
  - **Controller**: `App\Http\Controllers\Api\AvailabilityController`
  - **Method**: `index(Request $request)`
  - **Middleware**: none (public)
  - **Auth required**: no

### Reservation email confirmation (public)

- **POST** `/api/reservations/confirm-email`
  - **Controller**: `App\Http\Controllers\Api\ReservationController`
  - **Method**: `confirmEmail(Request $request)`
  - **Middleware**: none (public)
  - **Auth required**: no

## API routes (authenticated user: `auth:sanctum`)

Group:

- **Middleware**: `auth:sanctum`
- **File**: `routes/api.php`

### Authenticated user session

- **GET** `/api/me`
  - **Controller**: `App\Http\Controllers\Api\AuthController`
  - **Method**: `me(Request $request)`
  - **Auth required**: yes (Sanctum token)

- **POST** `/api/logout`
  - **Controller**: `App\Http\Controllers\Api\AuthController`
  - **Method**: `logout(Request $request)`
  - **Auth required**: yes (Sanctum token)

### Reservations (user)

- **GET** `/api/reservations`
  - **Controller**: `App\Http\Controllers\Api\ReservationController`
  - **Method**: `index(Request $request)`
  - **Auth required**: yes

- **GET** `/api/reservations/{reservation}`
  - **Controller**: `App\Http\Controllers\Api\ReservationController`
  - **Method**: `show(Request $request, Reservation $reservation)`
  - **Auth required**: yes
  - **Authorization logic**: blocks unless `reservation.user_id === currentUser.id` OR `currentUser->isAdmin()` (`ReservationController::show`)

- **POST** `/api/reservations`
  - **Controller**: `App\Http\Controllers\Api\ReservationController`
  - **Method**: `store(StoreReservationRequest $request)`
  - **Auth required**: yes
  - **Authorization logic**:
    - `StoreReservationRequest::authorize()` requires `user()->canManageReservations()`
    - This currently denies `role.slug === 'student_assistant'` (`User::canManageReservations` → `Role::canManageReservations`)

## API routes (admin: `auth:sanctum` + `role:admin`)

Group:

- **Prefix**: `/api/admin`
- **Middleware**: `auth:sanctum`, `role:admin`
- **Route name prefix**: `admin.` (route names are defined, but the names are not used elsewhere in the repository; **unclear from repository** whether names matter)
- **File**: `routes/api.php`
- **Role middleware implementation**: `App\Http\Middleware\EnsureUserHasRole::handle`

### Admin reservations

- **GET** `/api/admin/reservations`
  - **Controller**: `App\Http\Controllers\Api\Admin\ReservationController`
  - **Method**: `index(Request $request)`
  - **Auth required**: yes, must be `role.slug === 'admin'`

- **GET** `/api/admin/reservations/{reservation}`
  - **Controller**: `App\Http\Controllers\Api\Admin\ReservationController`
  - **Method**: `show(Reservation $reservation)`
  - **Auth required**: admin

- **POST** `/api/admin/reservations/{reservation}/approve`
  - **Controller**: `App\Http\Controllers\Api\Admin\ReservationController`
  - **Method**: `approve(Request $request, Reservation $reservation)`
  - **Auth required**: admin

- **POST** `/api/admin/reservations/{reservation}/reject`
  - **Controller**: `App\Http\Controllers\Api\Admin\ReservationController`
  - **Method**: `reject(Request $request, Reservation $reservation)`
  - **Auth required**: admin

- **POST** `/api/admin/reservations/{reservation}/cancel`
  - **Controller**: `App\Http\Controllers\Api\Admin\ReservationController`
  - **Method**: `cancel(Request $request, Reservation $reservation)`
  - **Auth required**: admin

- **POST** `/api/admin/reservations/{reservation}/override`
  - **Controller**: `App\Http\Controllers\Api\Admin\ReservationController`
  - **Method**: `override(Request $request, Reservation $reservation)`
  - **Auth required**: admin

### Admin reports

- **GET** `/api/admin/reports`
  - **Controller**: `App\Http\Controllers\Api\Admin\ReportController`
  - **Method**: `index(Request $request)`
  - **Auth required**: admin

- **GET** `/api/admin/reports/export`
  - **Controller**: `App\Http\Controllers\Api\Admin\ReportController`
  - **Method**: `export(Request $request)`
  - **Auth required**: admin

## Routes that appear unused, duplicated, or suspicious

- **Unused/duplicated**: no duplicates found in `routes/api.php` and `routes/web.php`.
- **Suspicious**:
  - `routes/web.php` is a catch-all for SPA; any server-side blade pages other than `resources/views/app.blade.php` and email templates are not routed directly.
  - Admin UI has routes in React (`/admin/*`) but they are client-side only; server will still hit `routes/web.php` for those and then React will handle.

