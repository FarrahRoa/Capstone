<?php

namespace App\Models;

use App\Support\AuthEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * TESTING ONLY: hard-coded faculty bypass for one external email.
     *
     * Keep the exception centralized and easy to remove:
     * - delete this constant + `isTestingFacultyBypassEmail()` and the few call sites below
     * - do NOT generalize to other gmail accounts
     */
    public const TESTING_FACULTY_BYPASS_EMAIL = 'sanguinesenior18@gmail.com';

    public const DOMAIN_XU = 'xu.edu.ph';
    public const DOMAIN_MY_XU = 'my.xu.edu.ph';
    public const USER_TYPE_STUDENT = 'student';
    public const USER_TYPE_FACULTY_STAFF = 'faculty_staff';

    /**
     * @see resources/js/constants/affiliationOptions.js (frontend must match these strings exactly)
     */
    public static function allowedStudentColleges(): array
    {
        // Source of truth: `colleges` table (falls back to legacy static list if empty).
        $rows = College::query()->orderBy('name')->pluck('name')->all();
        if ($rows !== []) {
            return $rows;
        }
        return [
            'College of Computer Studies',
            'College of Arts and Sciences',
            'School of Business and Management',
            'College of Agriculture',
            'College of Nursing',
            'College of Engineering',
            'School of Education',
            'School of Law',
            'School of Medicine',
        ];
    }

    /**
     * @see resources/js/constants/affiliationOptions.js (frontend must match these strings exactly)
     */
    public static function allowedFacultyOffices(): array
    {
        // Source of truth: `offices` table (falls back to legacy static list if empty).
        $rows = Office::query()->orderBy('name')->pluck('name')->all();
        if ($rows !== []) {
            return $rows;
        }
        return [
            'Office of the President',
            'Office of the Vice-President Higher Education',
            'Office of the Vice President',
            'Office of the Scholarship Guild',
            'Office of Student and Affairs',
            'SACDEV',
            "Treasurer's Office",
            'Finance',
            'Guidance Counselor',
            'Research Ethics Office',
            'Computer Studies Employee and Admin',
            'Nursing Employee and Admin',
            'Arts and Sciences Admin Office',
            'OMM',
            'OVPMM',
            'Agriculture Office',
            'PPO',
            'CISO Office',
            'School of Law Office',
            'School of Medicine Office',
            'Engineering Admin Office',
            'Sociology Department',
            'IDE Department',
        ];
    }

    /**
     * Boardroom reservation is restricted to these office affiliations only.
     *
     * @return array<int, string>
     */
    public static function allowedBoardroomOffices(): array
    {
        return [
            'Office of the President',
            'Office of the Vice-President Higher Education',
        ];
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'user_type',
        'profile_completed_at',
        'college_office',
        'mobile_number',
        'year_level',
        'med_confab_eligible',
        'boardroom_eligible',
        'college_id',
        'office_id',
        'is_activated',
        'otp',
        'otp_hash',
        'otp_expires_at',
        'admin_invite_token_hash',
        'admin_invite_expires_at',
        'admin_invited_at',
        'admin_password_set_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
        'otp_hash',
        'admin_invite_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_activated' => 'boolean',
            'med_confab_eligible' => 'boolean',
            'boardroom_eligible' => 'boolean',
            'otp_expires_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'admin_invite_expires_at' => 'datetime',
            'admin_invited_at' => 'datetime',
            'admin_password_set_at' => 'datetime',
        ];
    }

    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function reservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function trustedDevices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    /**
     * Case-insensitive lookup so auth works even if legacy rows differ in casing.
     */
    public static function findByNormalizedEmail(string $email): ?self
    {
        $normalized = AuthEmail::normalize($email);

        return static::whereRaw('LOWER(TRIM(email)) = ?', [$normalized])->first();
    }

    public static function isTestingFacultyBypassEmail(string $email): bool
    {
        return AuthEmail::normalize($email) === self::TESTING_FACULTY_BYPASS_EMAIL;
    }

    public function isAdmin(): bool
    {
        return $this->role && $this->role->isAdmin();
    }

    /**
     * Admin-portal accounts sign in via /admin/login with an app password (not OTP).
     */
    public function isAdminPortalAccount(): bool
    {
        if (!$this->role) {
            return false;
        }

        return in_array($this->role->slug, ['admin', 'librarian', 'student_assistant'], true);
    }

    public function isStudentAssistant(): bool
    {
        return $this->role && $this->role->isStudentAssistant();
    }

    public function canManageReservations(): bool
    {
        return $this->canDo('reservation.create');
    }

    public function getPermissions(): array
    {
        if (!$this->role) {
            return [];
        }

        return $this->role->getPermissions();
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        // Keep the API payload intentionally small: `toArray()` includes many columns the SPA never reads
        // and increases JSON encode/decode cost on the critical `/me` path.
        $this->loadMissing('role', 'college', 'office');

        $userType = $this->user_type ?? static::getUserTypeFromEmail($this->email);
        $profileComplete = $this->isProfileComplete();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role_id' => $this->role_id,
            'role' => $this->role
                ? [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                    'slug' => $this->role->slug,
                    'description' => $this->role->description,
                ]
                : null,
            'permissions' => $this->getPermissions(),
            'user_type' => $userType,
            'college_office' => $this->college_office,
            'college_id' => $this->college_id,
            'office_id' => $this->office_id,
            'college' => $this->college ? ['id' => $this->college->id, 'name' => $this->college->name] : null,
            'office' => $this->office ? ['id' => $this->office->id, 'name' => $this->office->name] : null,
            'mobile_number' => $this->mobile_number,
            'is_activated' => (bool) $this->is_activated,
            'profile_completed_at' => $this->profile_completed_at,
            'med_confab_eligible' => (bool) $this->med_confab_eligible,
            'boardroom_eligible' => (bool) $this->boardroom_eligible,
            'profile_complete' => $profileComplete,
            'requires_profile_completion' => (bool) $this->is_activated && !$profileComplete,
        ];
    }

    public function isProfileComplete(): bool
    {
        $name = trim((string) $this->name);
        $unit = trim((string) ($this->college_office ?? ''));
        $type = $this->user_type ?? static::getUserTypeFromEmail($this->email);

        if (!$this->is_activated) {
            return false;
        }

        if ($type === null) {
            return false;
        }

        return $name !== '' && $unit !== '';
    }

    public function canDo(string $permission): bool
    {
        return $this->role && $this->role->hasPermission($permission);
    }

    /**
     * Room-level business rules (not RBAC). Returns a validation message if this user cannot reserve the space.
     */
    public function roomReservationBlockedMessage(Space $space): ?string
    {
        // Staff/admin accounts are not restricted by these user-facing rules.
        if ($this->isAdmin()) {
            return null;
        }

        $userType = $this->user_type ?? self::getUserTypeFromEmail((string) $this->email);
        $spaceType = (string) ($space->type ?? '');

        // Students: Confab only; Med Confab allowed for eligible medical students.
        if ($userType === self::USER_TYPE_STUDENT) {
            if ($spaceType === Space::TYPE_CONFAB) {
                return null;
            }
            if ($spaceType === Space::TYPE_MEDICAL_CONFAB) {
                return $this->med_confab_eligible
                    ? null
                    : 'Your account type or affiliation does not have permission to reserve this specific space.';
            }

            return 'Your account type or affiliation does not have permission to reserve this specific space.';
        }

        // Faculty/Employees: all spaces, but Boardroom restricted by office affiliation.
        if ($spaceType === Space::TYPE_BOARDROOM) {
            // Preferred: ID-based check (central `offices` table).
            if ($this->office_id) {
                $allowedOfficeIds = Office::query()
                    ->whereIn('name', [
                        'Office of the President',
                        'Office of the Vice-President Higher Education',
                        'Office of the Vice President for Higher Education (OVPHE)',
                        'OVPHE',
                    ])
                    ->pluck('id')
                    ->all();
                if (in_array((int) $this->office_id, array_map('intval', $allowedOfficeIds), true)) {
                    return null;
                }

                return 'Your account type or affiliation does not have permission to reserve this specific space.';
            }

            // Legacy fallback: string-based unit on older profiles.
            $office = trim((string) ($this->college_office ?? ''));
            if (!in_array($office, self::allowedBoardroomOffices(), true)) {
                return 'Your account type or affiliation does not have permission to reserve this specific space.';
            }
        }

        // Medical Confab is allowed for staff/faculty as well.
        return null;
    }

    public static function getRoleSlugFromEmail(string $email): ?string
    {
        if (self::isTestingFacultyBypassEmail($email)) {
            return 'faculty';
        }

        $domain = strtolower(substr($email, strrpos($email, '@') + 1));
        if ($domain === self::DOMAIN_XU) {
            return 'faculty'; // default for @xu.edu.ph; could be staff/librarian - assign faculty as default
        }
        if ($domain === self::DOMAIN_MY_XU) {
            return 'student'; // default for @my.xu.edu.ph; student_assistant assigned separately by admin
        }
        return null;
    }

    public static function getUserTypeFromEmail(string $email): ?string
    {
        if (self::isTestingFacultyBypassEmail($email)) {
            return self::USER_TYPE_FACULTY_STAFF;
        }

        $domain = strtolower(substr($email, strrpos($email, '@') + 1));
        if ($domain === self::DOMAIN_MY_XU) {
            return self::USER_TYPE_STUDENT;
        }
        if ($domain === self::DOMAIN_XU) {
            return self::USER_TYPE_FACULTY_STAFF;
        }
        return null;
    }

    public static function isAllowedDomain(string $email): bool
    {
        return self::getRoleSlugFromEmail($email) !== null;
    }

    /** Normal user login: Student vs Employee/Staff (maps to email domain). */
    public const PUBLIC_ACCOUNT_STUDENT = 'student';

    public const PUBLIC_ACCOUNT_EMPLOYEE = 'employee';

    /**
     * @param  self::PUBLIC_ACCOUNT_*  $accountType
     */
    public static function emailMatchesPublicAccountType(string $accountType, string $normalizedEmail): bool
    {
        // TESTING ONLY: allow this single external email to pass the public login UI checks.
        if (self::isTestingFacultyBypassEmail($normalizedEmail)) {
            return true;
        }

        $domain = strtolower(substr($normalizedEmail, strrpos($normalizedEmail, '@') + 1));

        return match ($accountType) {
            self::PUBLIC_ACCOUNT_STUDENT => $domain === self::DOMAIN_MY_XU,
            self::PUBLIC_ACCOUNT_EMPLOYEE => $domain === self::DOMAIN_XU,
            default => false,
        };
    }

    public static function isAllowedAdminInviteDomain(string $email): bool
    {
        $domain = strtolower(substr($email, strrpos($email, '@') + 1));
        return $domain === self::DOMAIN_XU;
    }
}
