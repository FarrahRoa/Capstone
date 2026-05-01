# XU Library Space Reservation – Setup

This document describes how to run the **API-based** library reservation system (Laravel API + React frontend) in the existing capstone project.

## I don’t see the frontend (blank or “Loading…” only)

The React app is built by Vite. You must either **build** the assets or run the **Vite dev server**:

1. **Option A – Build once (simplest)**  
   In the project folder (`capstone`), run:
   ```bash
   npm install
   npm run build
   ```
   Then start Laravel (`php artisan serve`) and open the site. The built files will be in `public/build/`.

2. **Option B – Development with live reload**  
   Use two terminals:
   - Terminal 1: `npm run dev` (starts Vite)
   - Terminal 2: `php artisan serve` (starts Laravel)  
   Open the URL from Laravel (e.g. `http://127.0.0.1:8000`). The page loads the app from the Vite dev server.

If you see “Loading the app…” and it never changes, the browser is not loading the JS bundle — run the commands above and refresh.

## Requirements

- PHP 8.2+ with extensions: `zip` (for Composer), `openssl`, `pdo_mysql`, `mbstring`, `xml`, `ctype`, `json`
- Composer, Node.js 18+, npm
- MySQL (or SQLite; adjust `.env`)

## 1. Backend (Laravel API)

### Install PHP dependencies

```bash
composer install
```

If you see zip/unzip errors, enable the `zip` extension in `php.ini` and run `composer install` again.

### Publish Sanctum migration (if not already present)

If the `personal_access_tokens` table is not created by the included migration:

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --tag="sanctum-migrations"
```

### Environment

Copy `.env.example` to `.env` if needed, then:

- Set `APP_URL` (and optionally `FRONTEND_URL` if the React app is on a different origin).
- Configure `DB_*` for your database.
- Configure `MAIL_*` for OTP and reservation emails (OTP, verification link, approval/rejection).

### Migrations and seed

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
```

This seeds **roles** (admin, faculty, staff, librarian, student, student_assistant) and **spaces** (AVR, Lobby, Boardroom, 2 Medical Confab, 6 Confab). It also creates a default admin user:

- **Email:** `admin@xu.edu.ph`  
- **Password:** `password`  

(Change the password after first login.)

### Optional: PDF reports

For PDF export of reports, install:

```bash
composer require barryvdh/laravel-dompdf
```

If the package is not installed, the reports API still returns JSON; only the “Export PDF” option will fall back to JSON.

## 2. Frontend (React SPA)

The React app is served by the same Laravel app (Vite build). No separate dev server for the API is required for same-origin use.

### Install and build

```bash
npm install
npm run build
```

Development (with hot reload):

```bash
npm run dev
```

In another terminal, start Laravel:

```bash
php artisan serve
```

Open the app at the URL shown (e.g. `http://localhost:8000`). The SPA is served for all routes; the React app handles `/login`, `/`, `/reserve`, `/admin/*`, etc.

## 3. API overview

- **Base URL:** `/api` (same origin as the Laravel app).
- **Auth:** Laravel Sanctum (Bearer token). Login returns a token; the frontend stores it and sends it in `Authorization: Bearer <token>`.

### Main endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Login (email/password). Returns token or `requires_otp: true`. |
| POST | `/api/otp/verify` | First-time: verify OTP, activate account, return token. |
| POST | `/api/otp/resend` | Resend OTP. |
| GET | `/api/me` | Current user (auth required). |
| GET | `/api/spaces` | List spaces. |
| GET | `/api/availability?date=&space_id=` | Availability for a date (optional space). |
| POST | `/api/reservations` | Create reservation (auth; status: email verification pending). |
| POST | `/api/reservations/confirm-email` | Confirm reservation (body: `token` from email link). |
| GET | `/api/reservations` | My reservations (auth). |
| GET/POST | `/api/admin/reservations` | Admin list reservations. |
| POST | `/api/admin/reservations/{id}/approve` | Approve. |
| POST | `/api/admin/reservations/{id}/reject` | Reject (body: `reason`). |
| POST | `/api/admin/reservations/{id}/cancel` | Cancel. |
| POST | `/api/admin/reservations/{id}/override` | Override. |
| GET | `/api/admin/reports?period=monthly\|quarterly\|annual\|custom&from=&to=` | Report data. |
| GET | `/api/admin/reports/export?format=pdf\|json&period=&from=&to=` | Export report (PDF or JSON). |

## 4. Roles and email domains

- **@xu.edu.ph** → Faculty, Staff, Librarians (full reservation access). Default role for new users from this domain: faculty.
- **@my.xu.edu.ph** → Students (full access). Default role: student. **Student Assistants** (view-only) use the same domain; assign role `student_assistant` in the database for those accounts.
- **Admin** → Role `admin`; can access `/admin/*`, approve/reject/cancel/override, and view reports. Assign `admin` to librarians/staff as needed (e.g. `admin@xu.edu.ph` from seeder).

## 5. Quick test

1. Run migrations and seed, then start Laravel and (if needed) `npm run dev`.
2. Open the app, go to Login, sign in with `admin@xu.edu.ph` / `password` (or register with an allowed domain and complete OTP if first-time).
3. Open Calendar, pick a date and optionally a room, then create a reservation from “Reserve this room” or “New Reservation”.
4. Confirm the reservation via the link in the verification email (use same `APP_URL` or `FRONTEND_URL` so the link points to your app).
5. As admin, go to Admin → Reservations and approve or reject; then check Reports and export (JSON or PDF if dompdf is installed).

All features are driven by the REST API; the React frontend only consumes these endpoints.
