<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudSyncEvent extends Model
{
    public const TYPE_MANUAL_UPLOAD_START = 'manual_upload_start';

    public const TYPE_RESERVATION_PUSH_SUCCESS = 'reservation_push_success';

    public const TYPE_RESERVATION_PUSH_FAILED = 'reservation_push_failed';

    public const TYPE_RESERVATION_PUSH_SKIPPED = 'reservation_push_skipped';

    public const TYPE_REACHABILITY_CHECK = 'reachability_check';

    protected $fillable = [
        'reservation_id',
        'event_type',
        'status',
        'summary',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
