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

    /** AVR/Lobby: user verified email; awaiting dean/office decision via email link (before librarian queue). */
    public const STATUS_PENDING_DEAN_APPROVAL = 'pending_dean_approval';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';

    /** Admin global override applied; holds the post-override slot like approved. */
    public const STATUS_OVERRIDDEN = 'overridden';

    /**
     * Another booking lost its slot to a higher-priority admin override; user must pick a new time/space.
     */
    public const STATUS_RESCHEDULE_REQUIRED = 'reschedule_required';

    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    /** AVR/Lobby: routed to SACDEV dean mapping (organization) or requester affiliation mapping (employee). */
    public const EVENT_REQUEST_ORGANIZATION = 'organization';

    public const EVENT_REQUEST_EMPLOYEE = 'employee';

    public const STATUS_LABELS = [
        self::STATUS_EMAIL_VERIFICATION_PENDING => 'Pending verification',
        self::STATUS_PENDING_DEAN_APPROVAL => 'Pending dean/office approval',
        self::STATUS_PENDING_APPROVAL => 'Pending approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_OVERRIDDEN => 'Approved (admin override)',
        self::STATUS_RESCHEDULE_REQUIRED => 'Reschedule required',
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
            self::STATUS_PENDING_DEAN_APPROVAL,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_OVERRIDDEN,
            self::STATUS_RESCHEDULE_REQUIRED,
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
     * - confirm-email: email_verification_pending → pending_dean_approval (AVR/Lobby w/ mapping) or pending_approval
     * - dean email POST: pending_dean_approval → pending_approval | rejected
     * - approve / override: pending_approval → approved
     * - reject (librarian): pending_approval | email_verification_pending → rejected
     * - reject (dean email): pending_dean_approval → rejected
     * - cancel: any status except cancelled → cancelled (including rejected and approved)
     *
     * @return array<string, array<int, string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_EMAIL_VERIFICATION_PENDING => [
                self::STATUS_PENDING_APPROVAL,
                self::STATUS_PENDING_DEAN_APPROVAL,
                self::STATUS_REJECTED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_PENDING_DEAN_APPROVAL => [
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
                self::STATUS_OVERRIDDEN,
            ],
            self::STATUS_OVERRIDDEN => [
                self::STATUS_CANCELLED,
            ],
            self::STATUS_RESCHEDULE_REQUIRED => [
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
        'reservation_sequence', 'reservation_category',
        'purpose', 'event_title', 'event_description', 'participant_count', 'event_request_type',
        'verification_token', 'verification_expires_at', 'verified_at',
        'approved_by', 'approved_at', 'rejected_reason',
        'override_reason', 'overridden_by', 'overridden_at',
        'override_previous_space_id', 'override_previous_start_at', 'override_previous_end_at',
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
            'overridden_at' => 'datetime',
            'override_previous_start_at' => 'datetime',
            'override_previous_end_at' => 'datetime',
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
            self::STATUS_OVERRIDDEN,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_PENDING_DEAN_APPROVAL,
            self::STATUS_EMAIL_VERIFICATION_PENDING,
        ];
    }

    /**
     * Statuses shown as committed bookings on public/operational calendars (not pending pipeline).
     *
     * @return array<int, string>
     */
    public static function calendarCommittedStatuses(): array
    {
        return [
            self::STATUS_APPROVED,
            self::STATUS_OVERRIDDEN,
        ];
    }

    /**
     * Reservations an admin may move with the global override tool.
     *
     * @return array<int, string>
     */
    public static function globallyOverridableStatuses(): array
    {
        return [
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_PENDING_DEAN_APPROVAL,
            self::STATUS_EMAIL_VERIFICATION_PENDING,
            self::STATUS_APPROVED,
        ];
    }

    public function canBeGloballyOverriddenByAdmin(): bool
    {
        return in_array($this->status, self::globallyOverridableStatuses(), true);
    }

    /**
     * Statuses that count toward the per-user active reservation limit.
     *
     * @return array<int, string>
     */
    public static function activeUserLimitStatuses(): array
    {
        return [
            self::STATUS_PENDING_DEAN_APPROVAL,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_OVERRIDDEN,
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

    public function overrideLogs(): HasMany
    {
        return $this->hasMany(ReservationOverrideLog::class);
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

    public function isConfirmedBooking(): bool
    {
        return $this->status === self::STATUS_APPROVED || $this->status === self::STATUS_OVERRIDDEN;
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
     * API shape for the reservation owner: nested space shows the real assigned room name (e.g. "Confab 3")
     * so My Reservations stays specific, while public calendar APIs continue to use {@see Space::userFacingName()}.
     *
     * @return array<string, mixed>
     */
    public function toArrayForUserApi(): array
    {
        $this->loadMissing(['space', 'user', 'approver', 'logs.actor']);

        $data = $this->toArray();

        if ($this->space !== null) {
            $data['space'] = array_merge($this->space->toArray(), [
                'name' => (string) $this->space->name,
            ]);
        }

        return $data;
    }
}
