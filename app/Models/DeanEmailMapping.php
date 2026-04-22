<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeanEmailMapping extends Model
{
    public const TYPE_COLLEGE = 'college';
    public const TYPE_OFFICE_DEPARTMENT = 'office_department';

    protected $fillable = [
        'affiliation_type',
        'affiliation_name',
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
}

