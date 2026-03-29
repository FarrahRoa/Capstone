<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationLog extends Model
{
    public const ACTION_CREATE = 'create';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_OVERRIDE = 'override';
    public const ACTION_LABELS = [
        self::ACTION_CREATE => 'Created',
        self::ACTION_APPROVE => 'Approved',
        self::ACTION_REJECT => 'Rejected',
        self::ACTION_CANCEL => 'Cancelled',
        self::ACTION_OVERRIDE => 'Override approved',
    ];

    protected $fillable = ['reservation_id', 'admin_id', 'action', 'notes'];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
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
