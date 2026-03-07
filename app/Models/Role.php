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
        return !$this->isStudentAssistant();
    }
}
