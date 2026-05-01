## Frontend stack used

From `package.json` and `vite.config.js`, the frontend is:

- **React** (`react`, `react-dom`)
- **React Router** (`react-router-dom`)
- **Vite** build/dev server (`vite`, `laravel-vite-plugin`, `@vitejs/plugin-react`)
- **Tailwind CSS** via Vite plugin (`@tailwindcss/vite`, `tailwindcss`)
- **Axios** for API calls (`axios`)

## Rendering / entry points

### Server-rendered shell

- `resources/views/app.blade.php` is the HTML shell served for all web paths (`routes/web.php` catch-all).
  - Loads Vite dev assets via `@vite([...])` in dev or loads built assets via `public/build/manifest.json` if present.
  - Contains `<div id="root">` where React mounts.

### React entry

- `resources/js/app.jsx`:
  - `createRoot(document.getElementById('root')).render(...)`
  - Wraps app with `AuthProvider` and `BrowserRouter`.

## Client-side routing (React Router)

Defined in `resources/js/app.jsx`:

- `/login` → `Login`
- `/otp` → `OTPVerify`
- `/confirm-reservation` → `ConfirmReservation` (public link target from email)
- `/` → `Calendar` (wrapped by `PrivateRoute` and `Layout`)
- `/reserve` → `ReservationForm` (private)
- `/my-reservations` → `MyReservations` (private)
- `/admin/reservations` → `AdminReservations` (private + adminOnly)
- `/admin/reports` → `AdminReports` (private + adminOnly)
- `*` → redirects to `/`

`PrivateRoute` enforces:

- Must have authenticated `user` loaded by `AuthContext`
- If `adminOnly`, requires `user.role?.slug === 'admin'`

## State handling

- Global auth state is handled by React Context in `resources/js/contexts/AuthContext.jsx`:
  - Loads token/user from `localStorage`
  - If token exists, calls `GET /api/me` to refresh user data
  - Exposes `login(token, userData)` and `logout()`
- Feature-specific state is local component state via `useState` / `useEffect`.

## API client and backend integration

- `resources/js/api.js` configures Axios:
  - `baseURL: '/api'`
  - Adds `Authorization: Bearer <token>` header from `localStorage`
  - On HTTP 401: clears token/user and redirects to `/login`

Key API calls by page:

- `Login.jsx`:
  - `POST /api/login`
  - If `requires_otp`, navigates to `/otp` with `{ email }` in route state.
- `OTPVerify.jsx`:
  - `POST /api/otp/verify`
  - `POST /api/otp/resend`
- `Calendar.jsx`:
  - `GET /api/spaces`
  - `GET /api/availability?date=...&space_id=...`
- `ReservationForm.jsx`:
  - `GET /api/spaces`
  - `POST /api/reservations`
- `ConfirmReservation.jsx`:
  - `POST /api/reservations/confirm-email`
- `MyReservations.jsx`:
  - `GET /api/reservations`
- `AdminReservations.jsx`:
  - `GET /api/admin/reservations`
  - `POST /api/admin/reservations/{id}/approve`
  - `POST /api/admin/reservations/{id}/reject`
  - `POST /api/admin/reservations/{id}/cancel`
- `AdminReports.jsx`:
  - `GET /api/admin/reports`
  - `GET /api/admin/reports/export` (PDF blob)

## Important components/pages

- Layout/navigation: `resources/js/components/Layout.jsx`
  - Shows/hides links based on `user.role.slug`:
    - Hides “New Reservation” if `student_assistant`
    - Shows admin links if admin

Pages:

- `resources/js/pages/Login.jsx`
- `resources/js/pages/OTPVerify.jsx`
- `resources/js/pages/Calendar.jsx`
- `resources/js/pages/ReservationForm.jsx`
- `resources/js/pages/ConfirmReservation.jsx`
- `resources/js/pages/MyReservations.jsx`
- `resources/js/pages/admin/AdminReservations.jsx`
- `resources/js/pages/admin/AdminReports.jsx`

## Views corresponding to routes/features

- `routes/web.php` always returns `resources/views/app.blade.php` for web paths (SPA).
- Email templates used by backend mailables:
  - `resources/views/emails/otp.blade.php`
  - `resources/views/emails/reservation-verify.blade.php`
  - `resources/views/emails/reservation-approved.blade.php`
  - `resources/views/emails/reservation-rejected.blade.php`
- Report export template:
  - `resources/views/reports/export.blade.php`

