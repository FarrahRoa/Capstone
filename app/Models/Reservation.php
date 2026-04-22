<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Reservation extends Model
{
    public const CLOUD_SYNC_ORIGIN_PRIMARY = 'primary';

    public const CLOUD_SYNC_ORIGIN_LOCAL_FALLBACK = 'local_fallback';

    public const STATUS_EMAIL_VERIFICATION_PENDING = 'email_verification_pending';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_LABELS = [
        self::STATUS_EMAIL_VERIFICATION_PENDING => 'Pending verification',
        self::STATUS_PENDING_APPROVAL => 'Pending approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /**
     * All statuses used in the reservation lifecycle (single source of truth).
     *
     * @return array<int, string>
     */
    public static function workflowStatuses(): array
    {
        return [
            self::STATUS_EMAIL_VERIFICATION_PENDING,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Alias for {@see workflowStatuses()} — explicit name for validation and policy checks.
     *
     * @return array<int, string>
     */
    public static function allowedStatuses(): array
    {
        return self::workflowStatuses();
    }

    public static function isValidStatus(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        return in_array($status, self::allowedStatuses(), true);
    }

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation) {
            if ($reservation->cloud_sync_uuid === null || $reservation->cloud_sync_uuid === '') {
                $reservation->cloud_sync_uuid = (string) Str::uuid();
            }
            if ($reservation->cloud_sync_origin === null || $reservation->cloud_sync_origin === '') {
                $reservation->cloud_sync_origin = config('cloud_sync.record_origin', self::CLOUD_SYNC_ORIGIN_PRIMARY) === self::CLOUD_SYNC_ORIGIN_LOCAL_FALLBACK
                    ? self::CLOUD_SYNC_ORIGIN_LOCAL_FALLBACK
                    : self::CLOUD_SYNC_ORIGIN_PRIMARY;
            }
        });

        static::saving(function (Reservation $reservation) {
            if ($reservation->status !== null && !self::isValidStatus($reservation->status)) {
                throw new InvalidArgumentException(
                    'Invalid reservation status: ' . $reservation->status
                );
            }
        });
    }

    /**
     * Status assigned when a reservation is first created (before email confirmation).
     */
    public static function initialCreateStatus(): string
    {
        return self::STATUS_EMAIL_VERIFICATION_PENDING;
    }

    /**
     * Allowed single-step transitions keyed by current status.
     *
     * Product truth (must match existing endpoints):
     * - confirm-email: email_verification_pending → pending_approval (or → rejected if link expired)
     * - approve / override: pending_approval → approved
     * - reject: pending_approval | email_verification_pending → rejected
     * - cancel: any status except cancelled → cancelled (including rejected and approved)
     *
     * @return array<string, array<int, string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_EMAIL_VERIFICATION_PENDING => [
                self::STATUS_PENDING_APPROVAL,
                self::STATUS_REJECTED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_PENDING_APPROVAL => [
                self::STATUS_APPROVED,
                self::STATUS_REJECTED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_APPROVED => [
                self::STATUS_CANCELLED,
            ],
            self::STATUS_REJECTED => [
                self::STATUS_CANCELLED,
            ],
            self::STATUS_CANCELLED => [],
        ];
    }

    /**
     * Whether the reservation may move to $targetStatus in one step under the lifecycle rules.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        $allowed = self::allowedTransitions()[$this->status] ?? [];

        return in_array($targetStatus, $allowed, true);
    }

    protected $fillable = [
        'user_id', 'space_id', 'start_at', 'end_at', 'status', 'reservation_number',
        'purpose', 'event_title', 'event_description', 'participant_count',
        'verification_token', 'verification_expires_at', 'verified_at',
            'approved_by', 'approved_at', 'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'verification_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'cloud_synced_at' => 'datetime',
        ];
    }

    /**
     * Statuses that block a time slot for conflict detection / availability.
     *
     * Central source of truth used by:
     * - StoreReservationRequest early validation
     * - ReservationController::store final transactional gate
     * - AvailabilityController reserved slot query
     *
     * @return array<int, string>
     */
    public static function blockingStatuses(): array
    {
        // Slots are held while the reservation is active in the queue or verified pipeline.
        // Must stay aligned with lifecycle: these are the non-terminal states that reserve time.
        return [
            self::STATUS_APPROVED,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_EMAIL_VERIFICATION_PENDING,
        ];
    }

    /**
     * Statuses that count toward the per-user active reservation limit.
     *
     * @return array<int, string>
     */
    public static function activeUserLimitStatuses(): array
    {
        return [
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
        ];
    }

    /**
     * True overlap rule (adjacent bookings allowed):
     * existing.start_at < requestedEnd AND existing.end_at > requestedStart
     */
    public function scopeOverlapping(Builder $query, Carbon|string $startAt, Carbon|string $endAt): Builder
    {
        return $query
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt);
    }

    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', self::blockingStatuses());
    }

    public static function conflictsExist(int $spaceId, Carbon|string $startAt, Carbon|string $endAt, ?int $exceptReservationId = null): bool
    {
        $q = self::query()
            ->where('space_id', $spaceId)
            ->blocking()
            ->overlapping($startAt, $endAt);
        if ($exceptReservationId !== null) {
            $q->where('id', '<>', $exceptReservationId);
        }

        return $q->exists();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ReservationLog::class);
    }

    public function cloudSyncEvents(): HasMany
    {
        return $this->hasMany(CloudSyncEvent::class);
    }

    public function isPendingVerification(): bool
    {
        return $this->status === self::STATUS_EMAIL_VERIFICATION_PENDING;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    public static function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    /**
     * API shape for students/faculty: nested space uses {@see Space::userFacingName()}.
     *
     * @return array<string, mixed>
     */
    public function toArrayForUserApi(): array
    {
        $this->loadMissing(['space', 'user', 'approver', 'logs.actor']);

        $data = $this->toArray();

        if ($this->space !== null) {
            $data['space'] = array_merge($this->space->toArray(), [
                'name' => $this->space->userFacingName(),
            ]);
        }

        return $data;
    }
}
