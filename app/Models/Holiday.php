<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Holiday extends Model
{
    protected $fillable = ['name', 'date', 'is_recurring'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_recurring' => 'boolean',
        ];
    }

    public static function matchForDate(Carbon $date, string $tz): ?self
    {
        $d = $date->copy()->timezone($tz)->format('Y-m-d');
        $md = $date->copy()->timezone($tz)->format('m-d');

        return self::query()
            ->where(function ($q) use ($d, $md) {
                $q->where('date', $d)
                    ->orWhere(function ($q2) use ($md) {
                        $q2->where('is_recurring', true)
                            ->whereRaw("DATE_FORMAT(`date`, '%m-%d') = ?", [$md]);
                    });
            })
            ->orderByDesc('is_recurring')
            ->orderBy('date')
            ->first();
    }
}

