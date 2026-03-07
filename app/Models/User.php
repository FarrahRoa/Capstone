<?php

namespace App\Models;

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
        return $this->role && $this->role->canManageReservations();
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
