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

    protected $fillable = ['name', 'slug', 'type', 'capacity', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
