<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const FIXED_FULL_ACCESS_ROLES = [
        'Super Admin',
        'Admin',
        'Company Admin',
    ];

    protected $fillable = ['name', 'description', 'level', 'is_protected', 'status'];

    protected $casts = [
        'level' => 'integer',
        'is_protected' => 'boolean',
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === 'Super Admin' || (int) $this->level === 1;
    }

    public function isFixedFullAccessRole(): bool
    {
        return in_array($this->name, self::FIXED_FULL_ACCESS_ROLES, true);
    }
}
