<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeanEmailMapping extends Model
{
    public const TYPE_COLLEGE = 'college';

    public const TYPE_OFFICE_DEPARTMENT = 'office_department';

    /** Organization-event AVR/Lobby routing identifier (matches {@see ReservationDeanRouting::ORGANIZATION_DEAN_AFFILIATION_NAME}). */
    public const OFFICE_CODE_SACDEV = 'SACDEV';

    protected $fillable = [
        'affiliation_type',
        'affiliation_name',
        'office_code',
        'college_id',
        'office_id',
        'approver_name',
        'approver_email',
        'is_active',
    ];

    public static function normalizeOfficeCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $normalized = strtoupper(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Active office/department mapping for organization-event routing (e.g. SACDEV).
     */
    public static function findActiveForOfficeCode(string $officeCode): ?self
    {
        $code = self::normalizeOfficeCode($officeCode);
        if ($code === null) {
            return null;
        }

        $base = self::query()
            ->where('is_active', true)
            ->where('affiliation_type', self::TYPE_OFFICE_DEPARTMENT);

        $byCode = (clone $base)->where('office_code', $code)->first();
        if ($byCode !== null) {
            return $byCode;
        }

        $office = Office::query()->whereRaw('UPPER(TRIM(name)) = ?', [$code])->first();
        if ($office !== null) {
            $byOfficeId = (clone $base)->where('office_id', $office->id)->first();
            if ($byOfficeId !== null) {
                return $byOfficeId;
            }
        }

        return (clone $base)
            ->whereRaw('UPPER(TRIM(affiliation_name)) = ?', [$code])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareOfficeDepartmentAttributes(array $data): array
    {
        if (($data['affiliation_type'] ?? '') !== self::TYPE_OFFICE_DEPARTMENT) {
            return $data;
        }

        $name = trim((string) ($data['affiliation_name'] ?? ''));
        $data['affiliation_name'] = $name;
        $data['office_code'] = self::normalizeOfficeCode($name);

        if ($data['office_code'] !== null) {
            $office = Office::query()->whereRaw('UPPER(TRIM(name)) = ?', [$data['office_code']])->first();
            if ($office !== null) {
                $data['office_id'] = $office->id;
                $data['affiliation_name'] = (string) $office->name;
            }
        }

        if (! array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }

        return $data;
    }

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

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

