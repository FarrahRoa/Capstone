<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeanEmailMapping extends Model
{
    public const TYPE_COLLEGE = 'college';
    public const TYPE_OFFICE_DEPARTMENT = 'office_department';

    protected $fillable = [
        'affiliation_type',
        'affiliation_name',
        'college_id',
        'office_id',
        'approver_name',
        'approver_email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}

