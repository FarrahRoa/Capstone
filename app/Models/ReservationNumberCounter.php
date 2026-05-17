<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationNumberCounter extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'category';

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['category', 'last_sequence'];

    protected function casts(): array
    {
        return [
            'last_sequence' => 'integer',
        ];
    }
}
