<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class ReservationLog extends Model
{
    public const ACTOR_USER = 'user';
    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_SYSTEM = 'system';

    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_OVERRIDE = 'override';
    public const ACTION_LABELS = [
        self::ACTION_CREATE => 'Created',
        self::ACTION_UPDATE => 'Edited',
        self::ACTION_APPROVE => 'Approved',
        self::ACTION_REJECT => 'Rejected',
        self::ACTION_CANCEL => 'Cancelled',
        self::ACTION_OVERRIDE => 'Override approved',
    ];

    /**
     * Actions written by active reservation workflow code (audit vocabulary).
     *
     * @return array<int, string>
     */
    public static function allowedActions(): array
    {
        return [
            self::ACTION_CREATE,
            self::ACTION_UPDATE,
            self::ACTION_APPROVE,
            self::ACTION_REJECT,
            self::ACTION_CANCEL,
            self::ACTION_OVERRIDE,
        ];
    }

    public static function isValidAction(?string $action): bool
    {
        if ($action === null) {
            return false;
        }

        return in_array($action, self::allowedActions(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function allowedActorTypes(): array
    {
        return [
            self::ACTOR_USER,
            self::ACTOR_ADMIN,
            self::ACTOR_SYSTEM,
        ];
    }

    public static function isValidActorType(?string $actorType): bool
    {
        if ($actorType === null) {
            return true;
        }

        return in_array($actorType, self::allowedActorTypes(), true);
    }

    protected static function booted(): void
    {
        static::saving(function (ReservationLog $log) {
            if ($log->action !== null && !self::isValidAction($log->action)) {
                throw new InvalidArgumentException(
                    'Invalid reservation log action: ' . $log->action
                );
            }
            if (!self::isValidActorType($log->actor_type)) {
                throw new InvalidArgumentException(
                    'Invalid reservation log actor_type: ' . ($log->actor_type ?? 'null')
                );
            }
        });
    }

    protected $fillable = ['reservation_id', 'actor_user_id', 'actor_type', 'action', 'notes'];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @deprecated Use actor() instead.
     *
     * This exists only for temporary compatibility with older clients that may
     * still expect `logs.admin` in JSON. Active code should load/serialize `actor`.
     */
    public function admin(): BelongsTo
    {
        return $this->actor();
    }

    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? $action;
    }

    public static function actionLabels(): array
    {
        return self::ACTION_LABELS;
    }
}
