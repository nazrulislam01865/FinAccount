<?php

namespace App\Models\Access;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['key', 'module', 'action', 'label', 'description', 'route_name', 'sort_order'])]
class AccountingPermission extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AccountingRole::class, 'accounting_role_permissions', 'permission_id', 'role_id')
            ->withPivot('allowed')
            ->withTimestamps();
    }
}
