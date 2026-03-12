## What the project is

This repository is a **Laravel 12** backend with a **React (Vite) single-page app (SPA)** frontend for a **library space reservation system** branded as “**XU Library – Space Reservation**”.

- **Backend entry**: `artisan` (Laravel)
- **Frontend shell view**: `resources/views/app.blade.php` (serves the SPA for any web path)

## Main purpose

- Allow Xavier University users to **log in with an XU email**, **create space reservations**, **confirm reservations via email**, and then have **admins approve/reject/cancel/override** reservations.
- Provide admin **reports** (JSON + PDF when DomPDF is installed).

## Tech stack used

### Backend

- **PHP**: `^8.2` (`composer.json`)
- **Laravel**: `laravel/framework ^12.0` (`composer.json`)
- **Auth API tokens**: `laravel/sanctum` (`composer.json`)
- **PDF export**: `barryvdh/laravel-dompdf ^3.1` (`composer.json`)

### Frontend

- **React**: `react ^18.3.1`, `react-dom ^18.3.1` (`package.json`)
- **Routing**: `react-router-dom ^7.0.0` (`package.json`)
- **Build tooling**: Vite (`vite.config.js`, `package.json`)
- **CSS**: Tailwind via `@tailwindcss/vite` and `tailwindcss` (`package.json`, `vite.config.js`)
- **HTTP client**: Axios (`resources/js/api.js`, `package.json`)

### Data & infrastructure (as configured by default)

- **Database default**: SQLite (`.env.example` `DB_CONNECTION=sqlite`; `config/database.php`)
- **Session driver**: database (`.env.example` `SESSION_DRIVER=database`)
- **Queue driver**: database (`.env.example` `QUEUE_CONNECTION=database`)
- **Cache store**: database (`.env.example` `CACHE_STORE=database`)
- **Mail default**: log (`.env.example` `MAIL_MAILER=log`; `config/mail.php`)

## High-level architecture

- **SPA routing**: All web routes (`GET /{any?}`) return `view('app')` and React Router renders pages client-side (`routes/web.php`, `resources/js/app.jsx`).
- **JSON API** under `/api` (`routes/api.php`) consumed by the React frontend using Axios with `baseURL: '/api'` (`resources/js/api.js`).
- **Authentication**: Laravel Sanctum personal access tokens, passed as `Authorization: Bearer <token>` from the frontend (`resources/js/api.js`, `App\Http\Controllers\Api\AuthController`).
- **Authorization**:
  - `auth:sanctum` protects authenticated API routes (`routes/api.php`).
  - `role:admin` protects admin API routes and is implemented by `App\Http\Middleware\EnsureUserHasRole` (`routes/api.php`, `app/Http/Middleware/EnsureUserHasRole.php`).
  - Reservation creation is additionally restricted via `StoreReservationRequest::authorize()` which calls `User::canManageReservations()` (`app/Http/Requests/Api/StoreReservationRequest.php`, `app/Models/User.php`).

## Main modules / features

- **Authentication + OTP activation**: `App\Http\Controllers\Api\AuthController`
  - `POST /api/login` (`login`)
  - `POST /api/otp/verify` (`verifyOtp`)
  - `POST /api/otp/resend` (`resendOtp`)
  - `GET /api/me` (`me`, auth required)
  - `POST /api/logout` (`logout`, auth required)
- **Spaces**: `App\Http\Controllers\Api\SpaceController::index` (public list of active spaces)
- **Availability**: `App\Http\Controllers\Api\AvailabilityController::index` (public; returns reserved slots for active spaces on a date)
- **Reservations (user)**: `App\Http\Controllers\Api\ReservationController`
  - `POST /api/reservations` creates reservation (auth required; restricted by role via request authorize)
  - `POST /api/reservations/confirm-email` confirms reservation email link (public)
  - `GET /api/reservations` and `GET /api/reservations/{reservation}` for the authenticated user (with admin exception on `show`)
- **Admin reservations workflow**: `App\Http\Controllers\Api\Admin\ReservationController`
  - approve/reject/cancel/override with logging and mail
- **Admin reporting**: `App\Http\Controllers\Api\Admin\ReportController` (JSON metrics + export endpoint)

## User roles in the system

Roles are stored in the `roles` table and related to users via `users.role_id` (`database/migrations/2025_02_21_000001_create_roles_table.php`, `database/migrations/2025_02_21_000002_add_role_and_activation_to_users_table.php`).

Seeded roles (`database/seeders/RoleSeeder.php`):

- `admin`
- `faculty`
- `staff`
- `librarian`
- `student`
- `student_assistant`

Role-related checks (code):

- **Admin check**: `User::isAdmin()` delegates to `Role::isAdmin()` (`app/Models/User.php`, `app/Models/Role.php`)
- **Reservation-creation eligibility**: `User::canManageReservations()` delegates to `Role::canManageReservations()` which currently returns `!$this->isStudentAssistant()` (`app/Models/User.php`, `app/Models/Role.php`)

## Key workflows

### Login & activation (OTP)

- `POST /api/login` (`App\Http\Controllers\Api\AuthController::login`)
  - Accepts `email`, `password`, optional `name` (`app/Http/Requests/Api/LoginRequest.php`)
  - Restricts domains using `User::isAllowedDomain()` / `User::getRoleSlugFromEmail()` (only `@xu.edu.ph` and `@my.xu.edu.ph`) (`app/Models/User.php`)
  - First-time users are auto-created with a role based on email domain (`faculty` for `@xu.edu.ph`, `student` for `@my.xu.edu.ph`) (`AuthController::login`, `User::getRoleSlugFromEmail`)
  - If `is_activated=false`, system generates a 6-digit OTP (`otp_expires_at` +10 minutes) and sends `App\Mail\OtpMail` (`app/Http/Controllers/Api/AuthController.php`, `app/Mail/OtpMail.php`, `resources/views/emails/otp.blade.php`)
  - If activated, it issues a Sanctum token with `createToken('auth')`
- `POST /api/otp/verify` (`AuthController::verifyOtp`)
  - Validates OTP, checks expiry, activates the account, issues token

### Reservation creation & confirmation

- `POST /api/reservations` (`App\Http\Controllers\Api\ReservationController::store`)
  - Validated by `StoreReservationRequest`:
    - `authorize()` requires `user()->canManageReservations()` (blocks `student_assistant`)
    - rule constraints: `start_at after:now`, `end_at after:start_at`, `space_id exists`, `purpose max:1000`
    - conflict detection: checks overlap against statuses `approved`, `pending_approval`, `email_verification_pending` (`StoreReservationRequest::withValidator`)
  - Creates `Reservation` with status `email_verification_pending`, sets `verification_token` (64 random chars) and `verification_expires_at` (+24 hours)
  - Sends `App\Mail\ReservationVerificationMail` with frontend link `/confirm-reservation?token=...` (`resources/views/emails/reservation-verify.blade.php`)
- `POST /api/reservations/confirm-email` (`ReservationController::confirmEmail`)
  - Validates token; if expired sets status to `rejected`; else moves to `pending_approval`, sets `verified_at`, clears token/expires fields
  - Comment says “Notify admin …” but actual admin notification is **not implemented** (comment only)

### Admin approval / rejection / cancel / override

Admin API group is protected by `auth:sanctum` and `role:admin` (`routes/api.php` + `EnsureUserHasRole`).

- Approve: `Admin\ReservationController::approve`
  - Only if status is `pending_approval`
  - Sets `reservation_number` like `RES-XXXXXXXX` (random 8 chars), status `approved`, `approved_by`, `approved_at`
  - Creates `ReservationLog` action `approve`
  - Sends `ReservationApprovedMail` (`resources/views/emails/reservation-approved.blade.php`)
- Reject: `Admin\ReservationController::reject`
  - Allowed for status `pending_approval` or `email_verification_pending`
  - Sets status `rejected`, `rejected_reason`
  - Creates `ReservationLog` action `reject`
  - Sends `ReservationRejectedMail` (`resources/views/emails/reservation-rejected.blade.php`)
- Cancel: `Admin\ReservationController::cancel`
  - Sets status `cancelled` unless already cancelled
  - Logs action `cancel`
  - Sends `ReservationRejectedMail` with notes / default cancellation message
- Override: `Admin\ReservationController::override`
  - Validates `notes`
  - If status is `pending_approval`, it force-approves like approve()
  - Logs action `override`
  - Sends `ReservationApprovedMail`

