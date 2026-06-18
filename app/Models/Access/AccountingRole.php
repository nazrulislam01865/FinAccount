<?php

namespace App\Models\Access;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'slug', 'description', 'sort_order', 'is_system', 'is_active'])]
class AccountingRole extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AccountingPermission::class, 'accounting_role_permissions', 'role_id', 'permission_id')
            ->withPivot('allowed')
            ->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'accounting_role_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === 'super_admin';
    }
}
