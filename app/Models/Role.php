<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->slug === 'admin';
    }

    public function isStudentAssistant(): bool
    {
        return $this->slug === 'student_assistant';
    }

    public function canManageReservations(): bool
    {
        return $this->hasPermission('reservation.create');
    }

    public function getPermissions(): array
    {
        $permissions = config('permissions.roles.' . $this->slug, []);
        return is_array($permissions) ? array_values(array_unique($permissions)) : [];
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions(), true);
    }
}
