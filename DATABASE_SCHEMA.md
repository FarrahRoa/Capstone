## Overview

This schema is derived from migrations in `database/migrations/`.

- **Default DB connection**: SQLite (`.env.example`, `config/database.php`)
- **Foreign key constraints**: enabled by default in SQLite connection (`config/database.php` `foreign_key_constraints`).

## Migrations list (in repository)

- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/0001_01_01_000001_create_cache_table.php`
- `database/migrations/0001_01_01_000002_create_jobs_table.php`
- `database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php`
- `database/migrations/2025_02_21_000001_create_roles_table.php`
- `database/migrations/2025_02_21_000002_add_role_and_activation_to_users_table.php`
- `database/migrations/2025_02_21_000003_create_spaces_table.php`
- `database/migrations/2025_02_21_000004_create_reservations_table.php`
- `database/migrations/2025_02_21_000005_create_reservation_logs_table.php`

## Framework/support tables (from migrations in repo)

### `users`

**Migration**: `database/migrations/0001_01_01_000000_create_users_table.php` plus custom additions from `database/migrations/2025_02_21_000002_add_role_and_activation_to_users_table.php`.

Base columns:

- `id` (PK)
- `name` (string)
- `email` (string, **unique**)
- `email_verified_at` (timestamp, nullable)
- `password` (string)
- `remember_token` (string, nullable) via `rememberToken()`
- `created_at`, `updated_at` (timestamps)

Custom-added columns:

- `role_id` (FK → `roles.id`, nullable, nullOnDelete)
- `college_office` (string, nullable)
- `year_level` (string, nullable)
- `is_activated` (boolean, default false)
- `otp` (string length 6, nullable)
- `otp_expires_at` (timestamp, nullable)

Indexes/constraints:

- Unique index on `email`

Used by:

- Model: `App\Models\User`

### `password_reset_tokens`

**Migration**: `database/migrations/0001_01_01_000000_create_users_table.php`

Columns:

- `email` (string, **primary**)
- `token` (string)
- `created_at` (timestamp, nullable)

Used by:

- Password reset flow is **not found in code** (no controllers/routes for password reset were identified in this audit pass).

### `sessions`

**Migration**: `database/migrations/0001_01_01_000000_create_users_table.php`

Columns:

- `id` (string, **primary**)
- `user_id` (foreignId nullable, **indexed**; not declared as a FK in this migration)
- `ip_address` (string length 45, nullable)
- `user_agent` (text, nullable)
- `payload` (longText)
- `last_activity` (integer, **indexed**)

Used by:

- Session driver is configured to `database` by default (`.env.example`).

### `cache`

**Migration**: `database/migrations/0001_01_01_000001_create_cache_table.php`

Columns:

- `key` (string, **primary**)
- `value` (mediumText)
- `expiration` (integer, **indexed**)

### `cache_locks`

**Migration**: `database/migrations/0001_01_01_000001_create_cache_table.php`

Columns:

- `key` (string, **primary**)
- `owner` (string)
- `expiration` (integer, **indexed**)

### `jobs`

**Migration**: `database/migrations/0001_01_01_000002_create_jobs_table.php`

Columns:

- `id` (PK)
- `queue` (string, **indexed**)
- `payload` (longText)
- `attempts` (unsignedTinyInteger)
- `reserved_at` (unsignedInteger, nullable)
- `available_at` (unsignedInteger)
- `created_at` (unsignedInteger)

### `job_batches`

**Migration**: `database/migrations/0001_01_01_000002_create_jobs_table.php`

Columns:

- `id` (string, **primary**)
- `name` (string)
- `total_jobs` (integer)
- `pending_jobs` (integer)
- `failed_jobs` (integer)
- `failed_job_ids` (longText)
- `options` (mediumText, nullable)
- `cancelled_at` (integer, nullable)
- `created_at` (integer)
- `finished_at` (integer, nullable)

### `failed_jobs`

**Migration**: `database/migrations/0001_01_01_000002_create_jobs_table.php`

Columns:

- `id` (PK)
- `uuid` (string, **unique**)
- `connection` (text)
- `queue` (text)
- `payload` (longText)
- `exception` (longText)
- `failed_at` (timestamp, default current)

### `personal_access_tokens` (Sanctum)

**Migration**: `database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php`

Columns:

- `id` (PK)
- `tokenable_type`, `tokenable_id` (morphs)
- `name` (string)
- `token` (string length 64, **unique**)
- `abilities` (text, nullable)
- `last_used_at` (timestamp, nullable)
- `expires_at` (timestamp, nullable)
- `created_at`, `updated_at` (timestamps)

Used by:

- `App\Models\User` uses `Laravel\Sanctum\HasApiTokens` and issues tokens in `App\Http\Controllers\Api\AuthController`

## Application tables (project-specific)

### `roles`

**Migration**: `database/migrations/2025_02_21_000001_create_roles_table.php`

Columns:

- `id` (PK)
- `name` (string)
- `slug` (string, **unique**)
- `description` (text, nullable)
- `created_at`, `updated_at` (timestamps)

Indexes/constraints:

- Unique index on `slug`

Used by:

- Model: `App\Models\Role` → table `roles` (conventional)
- Relationship: `Role::users()` (hasMany)

### `spaces`

**Migration**: `database/migrations/2025_02_21_000003_create_spaces_table.php`

Columns:

- `id` (PK)
- `name` (string)
- `slug` (string, **unique**)
- `type` (string, default `'confab'`) comment suggests allowed values: `avr`, `lobby`, `boardroom`, `medical_confab`, `confab`
- `capacity` (unsignedSmallInteger, nullable)
- `is_active` (boolean, default true)
- `created_at`, `updated_at` (timestamps)

Indexes/constraints:

- Unique index on `slug`

Used by:

- Model: `App\Models\Space` (`Space::$fillable`)
- Relationship: `Space::reservations()` (hasMany)

### `reservations`

**Migration**: `database/migrations/2025_02_21_000004_create_reservations_table.php`

Columns:

- `id` (PK)
- `user_id` (FK → `users.id`, **cascadeOnDelete**)
- `space_id` (FK → `spaces.id`, **cascadeOnDelete**)
- `start_at` (dateTime)
- `end_at` (dateTime)
- `status` (string length 40, default `'email_verification_pending'`)
- `reservation_number` (string length 20, nullable, **unique**)
- `purpose` (text, nullable)
- `verification_token` (string length 64, nullable)
- `verification_expires_at` (timestamp, nullable)
- `verified_at` (timestamp, nullable)
- `approved_by` (FK → `users.id`, nullable, **nullOnDelete**)
- `approved_at` (timestamp, nullable)
- `rejected_reason` (text, nullable)
- `created_at`, `updated_at` (timestamps)

Indexes/constraints:

- Index on `space_id, start_at, end_at`
- Index on `status`
- Unique index on `reservation_number`

Used by:

- Model: `App\Models\Reservation` (fillable + casts)
- Controllers:
  - `App\Http\Controllers\Api\ReservationController`
  - `App\Http\Controllers\Api\Admin\ReservationController`
  - `App\Http\Controllers\Api\AvailabilityController`
  - `App\Http\Controllers\Api\Admin\ReportController`

Suspicious/missing constraints:

- No database-level check constraint to enforce `end_at > start_at` (enforced via validation in `StoreReservationRequest`).
- No database-level constraint enforcing valid `status` values (status is freeform string; valid values are constants in `Reservation`).
- No unique constraint preventing overlapping reservations per `space_id`/time range; overlaps are prevented in code via `StoreReservationRequest::withValidator`.

### `reservation_logs`

**Migration**: `database/migrations/2025_02_21_000005_create_reservation_logs_table.php`

Columns:

- `id` (PK)
- `reservation_id` (FK → `reservations.id`, **cascadeOnDelete**)
- `admin_id` (FK → `users.id`, **cascadeOnDelete**)
- `action` (string length 40) comment indicates values: `approve`, `reject`, `cancel`, `override`
- `notes` (text, nullable)
- `created_at`, `updated_at` (timestamps)

Indexes/constraints:

- Index on `reservation_id` (explicit)

Used by:

- Model: `App\Models\ReservationLog` (fillable)
- Admin controller: logs are created in `Admin\ReservationController::{approve,reject,cancel,override}`
- Relationship: `Reservation::logs()`; `ReservationLog::admin()` and `ReservationLog::reservation()`

Suspicious/missing constraints:

- No constraint enforcing `action` enumerations.

## Users table modifications (custom columns)

**Migration**: `database/migrations/2025_02_21_000002_add_role_and_activation_to_users_table.php`

Added columns to `users`:

- `role_id` (foreignId, nullable, FK → `roles.id`, **nullOnDelete**)
- `college_office` (string, nullable)
- `year_level` (string, nullable)
- `is_activated` (boolean default false)
- `otp` (string length 6, nullable)
- `otp_expires_at` (timestamp nullable)

Used by:

- `App\Models\User` fillable/casts/hidden
- `App\Http\Controllers\Api\AuthController` for OTP flow and activation
- `App\Http\Controllers\Api\Admin\ReportController` metrics groupings

Suspicious/missing constraints:

- `otp` is stored in plaintext (string) and not hashed.
- No index on `users.email` shown here (likely exists in default users migration, but **unclear from repository** without reading `0001_01_01_000000_create_users_table.php`).

## Relationships (ER summary)

- `roles (1) ──< users (many)` via `users.role_id`
- `users (1) ──< reservations (many)` via `reservations.user_id`
- `spaces (1) ──< reservations (many)` via `reservations.space_id`
- `users (1) ──< reservations approved_by (many)` via `reservations.approved_by` (approver relationship)
- `reservations (1) ──< reservation_logs (many)` via `reservation_logs.reservation_id`
- `users (1) ──< reservation_logs (many)` via `reservation_logs.admin_id`

