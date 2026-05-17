<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Space extends Model
{
    /** Medical Confab rooms (Med Confab business rules). */
    public const TYPE_MEDICAL_CONFAB = 'medical_confab';

    /** Office of the President Boardroom. */
    public const TYPE_BOARDROOM = 'boardroom';

    /** Standard confab rooms (Confab 1…N). */
    public const TYPE_CONFAB = 'confab';

    public const TYPE_AVR = 'avr';

    public const TYPE_LOBBY = 'lobby';

    public const TYPE_LECTURE = 'lecture';

    protected $fillable = ['name', 'slug', 'type', 'capacity', 'is_active', 'is_confab_pool', 'guideline_details', 'image_path'];

    protected $appends = ['image_url'];

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
     * End-user label for students/faculty.
     *
     * Confab assignment pool stays generic ("Confab") so users book the pool,
     * but physical/assigned rooms (e.g. "Confab 1") must remain specific.
     */
    public function userFacingName(): string
    {
        if ($this->type === self::TYPE_CONFAB && $this->isConfabAssignmentPool()) {
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

        if ($this->type === self::TYPE_MEDICAL_CONFAB) {
            return (string) $this->name;
        }

        return $this->userFacingName();
    }

    /**
     * Public URL for the space photo, or the app placeholder when none is stored.
     */
    public function getImageUrlAttribute(): string
    {
        if ($this->image_path && Storage::disk('public')->exists($this->image_path)) {
            $url = Storage::disk('public')->url($this->image_path);
            $v = (int) ($this->updated_at?->timestamp ?? 0);
            if ($v > 0) {
                return $url.(str_contains($url, '?') ? '&' : '?').'v='.$v;
            }

            return $url;
        }

        return asset('images/library-space-placeholder.svg');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
