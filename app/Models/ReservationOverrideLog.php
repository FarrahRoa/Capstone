<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationOverrideLog extends Model
{
    protected $fillable = [
        'reservation_id',
        'admin_user_id',
        'previous_space_id',
        'previous_start_at',
        'previous_end_at',
        'new_space_id',
        'new_start_at',
        'new_end_at',
        'reason',
        'displaced_reservation_ids',
    ];

    protected function casts(): array
    {
        return [
            'previous_start_at' => 'datetime',
            'previous_end_at' => 'datetime',
            'new_start_at' => 'datetime',
            'new_end_at' => 'datetime',
            'displaced_reservation_ids' => 'array',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
