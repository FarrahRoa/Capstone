## Required software and versions (from repository)

- **PHP**: `^8.2` (`composer.json`)
- **Composer**: required to install PHP dependencies (implied by `composer.json` and composer scripts)
- **Node.js + npm**: required to install/build frontend (`package.json`; `composer.json` scripts run `npm install`)
- **Database**: SQLite by default (`.env.example`, `config/database.php`)

## Local setup (exact steps derived from repo)

### One-command setup (composer script)

From the project root, run:

```bash
composer run setup
```

This script (defined in `composer.json`) performs:

- `composer install`
- Copy `.env.example` → `.env` if `.env` does not exist
- `php artisan key:generate`
- `php artisan migrate --force`
- `php artisan db:seed --force` (roles, spaces, policy doc, demo users — **required** or the room list and admin Spaces table stay empty)
- `npm install`
- `npm run build`

### Manual setup (equivalent)

From the project root:

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
```

Notes:

- On Windows, the copy command is `copy` (PowerShell / cmd). The composer script uses PHP to copy, so it’s OS-neutral.

## Running locally

### Recommended dev command (runs backend + frontend + queue + logs)

From the project root:

```bash
composer run dev
```

This runs (via `npx concurrently` in `composer.json`):

- `php artisan serve`
- `php artisan queue:listen --tries=1 --timeout=0`
- `php artisan pail --timeout=0`
- `npm run dev`

### Alternative: run in separate terminals

Backend:

```bash
php artisan serve
```

Frontend:

```bash
npm run dev
```

Queue (if you rely on queued jobs; unclear from repository whether any mail is queued):

```bash
php artisan queue:listen --tries=1 --timeout=0
```

## Environment variables (.env)

The repository provides `.env.example` with the following variables:

- **App**: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`
- **Locale**: `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`
- **Maintenance**: `APP_MAINTENANCE_DRIVER` (and commented store)
- **Security**: `BCRYPT_ROUNDS`
- **Logging**: `LOG_CHANNEL`, `LOG_STACK`, `LOG_DEPRECATIONS_CHANNEL`, `LOG_LEVEL`
- **Database**: `DB_CONNECTION` (defaults to `sqlite`), optional `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- **Sessions**: `SESSION_DRIVER` (defaults to `database`), `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN`
- **Broadcasting**: `BROADCAST_CONNECTION`
- **Filesystem**: `FILESYSTEM_DISK`
- **Queue**: `QUEUE_CONNECTION` (defaults to `database`)
- **Cache**: `CACHE_STORE` (defaults to `database`)
- **Redis/Memcached**: `MEMCACHED_HOST`, `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`
- **Mail**: `MAIL_MAILER` (defaults to `log`), `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- **AWS**: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT`
- **Vite**: `VITE_APP_NAME`

Additional env vars used in code/config but **not present** in `.env.example`:

- **`config('app.frontend_url')`**: referenced by `resources/views/emails/reservation-verify.blade.php` to build the confirm URL.
  - **Env var**: `FRONTEND_URL` (now present in `.env.example`). Set it when the SPA is deployed on a different origin than the API.

## Database migration / seed steps

Migrations present in `database/migrations/` include:

- `0001_01_01_000000_create_users_table.php`
- `0001_01_01_000001_create_cache_table.php`
- `0001_01_01_000002_create_jobs_table.php`
- `2019_12_14_000001_create_personal_access_tokens_table.php` (Sanctum)
- `2025_02_21_000001_create_roles_table.php`
- `2025_02_21_000002_add_role_and_activation_to_users_table.php`
- `2025_02_21_000003_create_spaces_table.php`
- `2025_02_21_000004_create_reservations_table.php`
- `2025_02_21_000005_create_reservation_logs_table.php`

Run migrations:

```bash
php artisan migrate
```

Seeders present in `database/seeders/`:

- `RoleSeeder` (creates roles)
- `SpaceSeeder` (creates spaces)
- `DatabaseSeeder` calls both, and creates a default admin user if not present:
  - email: `admin@xu.edu.ph`
  - password: `password`

Run seeders:

```bash
php artisan db:seed
```

Or:

```bash
php artisan migrate:fresh --seed
```

## Common setup/runtime errors and fixes (from repository evidence)

### SPA shows “Loading the app…” and never loads

- **Cause**: frontend assets not built.
- **Evidence**: `resources/views/app.blade.php` includes a message instructing `npm install` and `npm run build`.
- **Fix**:

```bash
npm install
npm run build
```

### Emails not delivered

- **Cause**: default mailer is `log` (`.env.example` `MAIL_MAILER=log`, `config/mail.php`).
- **Fix**: set `MAIL_MAILER=smtp` and configure SMTP variables (host, port, username, password).

### Reservation confirmation link points to wrong host

- **Cause**: verification email uses `config('app.frontend_url', config('app.url'))` (`resources/views/emails/reservation-verify.blade.php`).
- **Fix**: set `FRONTEND_URL` (used by `config/app.php` key `frontend_url`) to the SPA host, or ensure `APP_URL` matches the desired host.

