<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class TrustedDevice extends Model
{
    protected $table = 'trusted_devices';

    protected $fillable = [
        'user_id',
        'token_hash',
        'user_agent',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at && $this->expires_at->isFuture();
    }

    /**
     * Find an active trusted device for this user matching the raw browser token.
     */
    public static function findActiveForUserToken(User $user, string $plainToken): ?self
    {
        $candidates = static::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->get();

        foreach ($candidates as $device) {
            if (Hash::check($plainToken, $device->token_hash)) {
                return $device;
            }
        }

        return null;
    }

    public function revoke(): void
    {
        if ($this->revoked_at === null) {
            $this->forceFill(['revoked_at' => now()])->save();
        }
    }
}
