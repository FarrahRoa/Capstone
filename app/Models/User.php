<?php

namespace App\Models;

use App\Support\RegistrationDisplayName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const DOMAIN_XU = 'xu.edu.ph';
    public const DOMAIN_MY_XU = 'my.xu.edu.ph';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'college_office',
        'year_level',
        'med_confab_eligible',
        'boardroom_eligible',
        'is_activated',
        'otp',
        'otp_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
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
        ];
    }

    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function reservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isAdmin(): bool
    {
        return $this->role && $this->role->isAdmin();
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
        $data = $this->load('role')->toArray();
        $data['permissions'] = $this->getPermissions();
        $data['needs_profile_enrichment'] = RegistrationDisplayName::needsEnrichment($this->name);

        return $data;
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
        if ($space->type === Space::TYPE_MEDICAL_CONFAB && !$this->med_confab_eligible) {
            return 'Only eligible med users can reserve Med Confab.';
        }
        if ($space->type === Space::TYPE_BOARDROOM && !$this->boardroom_eligible) {
            return 'Only authorized Office of the President users can reserve Boardroom.';
        }

        return null;
    }

    public static function getRoleSlugFromEmail(string $email): ?string
    {
        $domain = strtolower(substr($email, strrpos($email, '@') + 1));
        if ($domain === self::DOMAIN_XU) {
            return 'faculty'; // default for @xu.edu.ph; could be staff/librarian - assign faculty as default
        }
        if ($domain === self::DOMAIN_MY_XU) {
            return 'student'; // default for @my.xu.edu.ph; student_assistant assigned separately by admin
        }
        return null;
    }

    public static function isAllowedDomain(string $email): bool
    {
        return self::getRoleSlugFromEmail($email) !== null;
    }
}
