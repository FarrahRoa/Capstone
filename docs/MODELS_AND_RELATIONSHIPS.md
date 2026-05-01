## Models inventory

Eloquent models found in `app/Models/`:

- `App\Models\User`
- `App\Models\Role`
- `App\Models\Space`
- `App\Models\Reservation`
- `App\Models\ReservationLog`

No additional Eloquent models were found in `app/Models/` beyond the above.

## `App\Models\User`

**File**: `app/Models/User.php`  
**Extends**: `Illuminate\Foundation\Auth\User`  
**Traits**: `Laravel\Sanctum\HasApiTokens`, `HasFactory`, `Notifiable`

### Fillable

- `name`
- `email`
- `password`
- `role_id`
- `college_office`
- `year_level`
- `is_activated`
- `otp`
- `otp_expires_at`

### Hidden

- `password`
- `remember_token`
- `otp`

### Casts

- `email_verified_at` → `datetime`
- `password` → `hashed`
- `is_activated` → `boolean`
- `otp_expires_at` → `datetime`

### Relationships

- `role(): BelongsTo` → `App\Models\Role`
- `reservations(): HasMany` → `App\Models\Reservation`

### Custom methods / business logic

- `isAdmin(): bool` → true if `role` exists and `role->isAdmin()`
- `isStudentAssistant(): bool` → delegates to `Role::isStudentAssistant()`
- `canManageReservations(): bool` → delegates to `Role::canManageReservations()`
- `getRoleSlugFromEmail(string $email): ?string`
  - `@xu.edu.ph` → `'faculty'` (hardcoded default)
  - `@my.xu.edu.ph` → `'student'` (hardcoded default)
  - otherwise `null`
- `isAllowedDomain(string $email): bool` → allowed if `getRoleSlugFromEmail(...) !== null`

### Controllers/services that use `User`

- `App\Http\Controllers\Api\AuthController`
  - Finds/creates users, verifies password, manages OTP fields, issues Sanctum tokens
- `App\Http\Middleware\EnsureUserHasRole`
  - Uses `Request::user()` and reads `$user->role->slug`
- `App\Http\Controllers\Api\ReservationController::show`
  - Uses `$request->user()->isAdmin()`
- `App\Http\Controllers\Api\Admin\ReportController`
  - Reads `$reservation->user->college_office`, `$reservation->user->year_level`, `$reservation->user->role->slug`

## `App\Models\Role`

**File**: `app/Models/Role.php`  
**Extends**: `Illuminate\Database\Eloquent\Model`

### Fillable

- `name`
- `slug`
- `description`

### Relationships

- `users(): HasMany` → `App\Models\User`

### Custom methods

- `isAdmin(): bool` → `$this->slug === 'admin'`
- `isStudentAssistant(): bool` → `$this->slug === 'student_assistant'`
- `canManageReservations(): bool` → `!$this->isStudentAssistant()`

### Controllers/services that use `Role`

- `App\Http\Controllers\Api\AuthController`
  - Looks up role by slug and assigns `role_id` during first-time user creation
- Seed: `Database\Seeders\RoleSeeder`

## `App\Models\Space`

**File**: `app/Models/Space.php`  
**Extends**: `Illuminate\Database\Eloquent\Model`

### Fillable

- `name`
- `slug`
- `type`
- `capacity`
- `is_active`

### Casts

- `is_active` → `boolean`

### Relationships

- `reservations(): HasMany` → `App\Models\Reservation`

### Controllers/services that use `Space`

- `App\Http\Controllers\Api\SpaceController::index` (lists active spaces)
- `App\Http\Controllers\Api\AvailabilityController::index` (lists active spaces and their reserved slots)
- `App\Http\Controllers\Api\Admin\ReportController` (resolves space names for utilization stats via `Space::find`)
- Seed: `Database\Seeders\SpaceSeeder`

## `App\Models\Reservation`

**File**: `app/Models/Reservation.php`  
**Extends**: `Illuminate\Database\Eloquent\Model`

### Status constants

- `STATUS_EMAIL_VERIFICATION_PENDING = 'email_verification_pending'`
- `STATUS_PENDING_APPROVAL = 'pending_approval'`
- `STATUS_APPROVED = 'approved'`
- `STATUS_REJECTED = 'rejected'`
- `STATUS_CANCELLED = 'cancelled'`

### Fillable

- `user_id`
- `space_id`
- `start_at`
- `end_at`
- `status`
- `reservation_number`
- `purpose`
- `verification_token`
- `verification_expires_at`
- `verified_at`
- `approved_by`
- `approved_at`
- `rejected_reason`

### Casts

- `start_at` → `datetime`
- `end_at` → `datetime`
- `verification_expires_at` → `datetime`
- `verified_at` → `datetime`
- `approved_at` → `datetime`

### Relationships

- `user(): BelongsTo` → `App\Models\User`
- `space(): BelongsTo` → `App\Models\Space`
- `approver(): BelongsTo` → `App\Models\User` using FK `approved_by`
- `logs(): HasMany` → `App\Models\ReservationLog`

### Custom methods

- `isPendingVerification(): bool`
- `isPendingApproval(): bool`
- `isApproved(): bool`

### Controllers/services that use `Reservation`

- `App\Http\Controllers\Api\ReservationController`
  - Create, list, show, and email-confirm reservations
- `App\Http\Requests\Api\StoreReservationRequest`
  - Conflict/overlap detection query
- `App\Http\Controllers\Api\Admin\ReservationController`
  - Admin approval/reject/cancel/override, writes logs, sends mail
- `App\Http\Controllers\Api\AvailabilityController`
  - Availability listing based on reservation status and date
- `App\Http\Controllers\Api\Admin\ReportController`
  - Aggregation metrics and export
- Mail:
  - `App\Mail\ReservationVerificationMail`
  - `App\Mail\ReservationApprovedMail`
  - `App\Mail\ReservationRejectedMail`

## `App\Models\ReservationLog`

**File**: `app/Models/ReservationLog.php`  
**Extends**: `Illuminate\Database\Eloquent\Model`

### Fillable

- `reservation_id`
- `admin_id`
- `action`
- `notes`

### Relationships

- `reservation(): BelongsTo` → `App\Models\Reservation`
- `admin(): BelongsTo` → `App\Models\User` using FK `admin_id`

### Controllers/services that use `ReservationLog`

- `App\Http\Controllers\Api\Admin\ReservationController`
  - Creates logs for actions `approve`, `reject`, `cancel`, `override`

