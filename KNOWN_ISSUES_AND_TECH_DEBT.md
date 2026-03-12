## Summary (ranked recommendations)

Priority is based on security risk and likelihood of runtime issues, as evidenced in code.

## P0 — Security / data integrity risks

- **Plaintext OTP storage**
  - **Evidence**: `users.otp` is set to the OTP string in `App\Http\Controllers\Api\AuthController::login` and `resendOtp`; verified by direct string comparison in `verifyOtp`.
  - **Risk**: Anyone with DB read access can read valid OTPs. Also increases blast radius of DB leakage.
  - **Recommendation**: Store a hashed OTP (e.g., `Hash::make`) and compare with `Hash::check`, or use signed one-time links.

- **Race condition risk in reservation conflict detection**
  - **Evidence**: overlap check exists only in `StoreReservationRequest::withValidator()`; there is no DB constraint preventing overlapping time ranges.
  - **Risk**: concurrent submissions could both pass validation and create overlapping reservations.
  - **Recommendation**: Use DB locking/transactions and/or create a server-side “availability check + create” atomic operation; consider unique constraints by time slot if slots are discrete.

- **`role:admin` is the only admin gate; no additional policy checks**
  - **Evidence**: admin routes rely on `EnsureUserHasRole` which checks `user->role->slug`.
  - **Risk**: If role assignment is ever compromised, admin actions have full power with no other authorization layers.
  - **Recommendation**: Add policies/gates or more granular permissions if needed.

## P1 — Likely bugs / inconsistent behavior

- **`AvailabilityController` validation has redundant/incorrect rule**
  - **Evidence**: `space_id` uses `required_without:date`, but `date` is required.
  - **Impact**: Confusing validation; doesn’t break functionality but indicates rule mis-specification.
  - **Recommendation**: Change `space_id` to `nullable|exists:spaces,id` only.

- **Admin `override` can send approval mail without approving**
  - **Evidence**: `Admin\ReservationController::override` only changes status if status is `pending_approval`, but always logs `override` and always sends `ReservationApprovedMail`.
  - **Impact**: Users may receive “approved” email even when reservation remains rejected/cancelled/email_verification_pending.
  - **Recommendation**: Restrict override usage to appropriate statuses or enforce state change before sending approval mail.

- **Admin cancel uses rejection email template/class**
  - **Evidence**: `cancel()` sends `ReservationRejectedMail` with cancellation text.
  - **Impact**: Semantics/subject line may confuse recipients; harder to audit.
  - **Recommendation**: Add a dedicated `ReservationCancelledMail` or rename template/subject.

- **No validation on admin `notes` / `reason` fields (except override notes)**
  - **Evidence**:
    - `approve()` stores `$request->input('notes')` to logs with no validation.
    - `reject()` stores `$request->input('reason')` to `rejected_reason` and logs.
    - `cancel()` stores `$request->input('notes')`.
  - **Impact**: Unbounded input size could cause large DB rows or unexpected errors.
  - **Recommendation**: Add request validation with max length constraints.

## P2 — Missing features or incomplete implementation

- **No admin notification after reservation is email-confirmed**
  - **Evidence**: `ReservationController::confirmEmail` contains comment “Notify admin …” but no notification implementation exists.
  - **Impact**: Admins may not be aware of pending approvals unless they regularly check.
  - **Recommendation**: Send email to admins, create dashboard badge, or queue notification job.

- **User management endpoints not present**
  - **Evidence**: No routes/controllers for user CRUD or role changes in `routes/api.php` or controller inventory.
  - **Impact**: Managing roles like `student_assistant` appears to require direct DB changes or future feature work.
  - **Recommendation**: Add admin endpoints/UI for user role management.

- **Space management endpoints not present**
  - **Evidence**: Only `GET /api/spaces` exists; no create/update/delete.
  - **Impact**: Changes to spaces likely require seeding/manual DB edits.
  - **Recommendation**: Add admin CRUD for spaces and `is_active` toggling.

## P3 — Maintainability / architecture

- **Business logic spread across controllers + requests**
  - **Evidence**: overlap detection in `StoreReservationRequest`, state transitions in controllers, metrics in `ReportController`.
  - **Impact**: Harder to test and reuse; likely to produce duplication as features grow.
  - **Recommendation**: Introduce service classes (e.g., `ReservationService`, `ReportingService`) and keep controllers thin.

- **Hardcoded domain→role mapping**
  - **Evidence**: `User::getRoleSlugFromEmail` returns `'faculty'` for `@xu.edu.ph` and `'student'` for `@my.xu.edu.ph`.
  - **Impact**: Role policy changes require code changes and redeploy.
  - **Recommendation**: Move mapping to config or database table.

## Dead code / unused endpoints (as evidenced)

- **Admin override endpoint appears unused by frontend**
  - **Evidence**: frontend `AdminReservations.jsx` calls approve/reject/cancel but has no call site for `/override`.
  - **Impact**: Extra code paths to maintain; potential security surface.
  - **Recommendation**: Add UI for override if needed, or remove/lock down if not.

