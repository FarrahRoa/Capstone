<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Space extends Model
{
    /** Medical Confab rooms (Med Confab business rules). */
    public const TYPE_MEDICAL_CONFAB = 'medical_confab';

    /** Office of the President Boardroom. */
    public const TYPE_BOARDROOM = 'boardroom';

    /** Standard confab rooms (Confab 1…N). */
    public const TYPE_CONFAB = 'confab';

    protected $fillable = ['name', 'slug', 'type', 'capacity', 'is_active', 'is_confab_pool', 'guideline_details'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_confab_pool' => 'boolean',
            'guideline_details' => 'array',
        ];
    }

    /**
     * Meta-space: users book this slot; admin assigns a specific {@see TYPE_CONFAB} room on approval.
     */
    public function isConfabAssignmentPool(): bool
    {
        return (bool) $this->is_confab_pool;
    }

    /**
     * Physical confab rooms (not the assignment pool).
     */
    public function isAssignableConfabRoom(): bool
    {
        return $this->type === self::TYPE_CONFAB && ! $this->isConfabAssignmentPool();
    }

    /**
     * End-user label for students/faculty (pool + assignable confab rooms read as "Confab").
     * Admin APIs continue to use the stored {@see $name} for specific room identity.
     */
    public function userFacingName(): string
    {
        if ($this->type === self::TYPE_CONFAB) {
            return 'Confab';
        }

        return (string) $this->name;
    }

    /**
     * Admin schedule / operations: show the real numbered Confab name when known; label the assignment pool clearly.
     * End-user APIs continue to use {@see userFacingName()} unless explicitly requesting operational labels.
     */
    public function scheduleOperationalDisplayName(): string
    {
        if ($this->type === self::TYPE_CONFAB) {
            return $this->isConfabAssignmentPool()
                ? 'Confab (pool — pending assignment)'
                : (string) $this->name;
        }

        return $this->userFacingName();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
