<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'status'];

    protected $hidden = ['password', 'remember_token'];

    private ?array $permissionNameCache = null;

    private ?bool $superAdminCache = null;

    private ?int $roleLevelCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function activeRoles()
    {
        return $this->roles()->where('status', 'Active');
    }

    public function hasRole(string $role): bool
    {
        return $this->activeRoleCollection()
            ->contains(fn (Role $activeRole) => $activeRole->name === $role);
    }

    public function hasAnyRole(array $roles): bool
    {
        $roles = array_values(array_unique(array_map('strval', $roles)));

        if ($roles === []) {
            return false;
        }

        return $this->activeRoleCollection()
            ->contains(fn (Role $activeRole) => in_array($activeRole->name, $roles, true));
    }

    public function isActive(): bool
    {
        return ($this->status ?? 'Active') === 'Active';
    }

    public function isSuperAdmin(): bool
    {
        if ($this->superAdminCache !== null) {
            return $this->superAdminCache;
        }

        $this->superAdminCache = $this->activeRoleCollection()
            ->contains(fn (Role $role) => $role->isSuperAdmin());

        return $this->superAdminCache;
    }

    public function roleLevel(): int
    {
        if ($this->roleLevelCache !== null) {
            return $this->roleLevelCache;
        }

        $this->roleLevelCache = (int) ($this->activeRoleCollection()->min('level') ?? 999);

        return $this->roleLevelCache;
    }

    public function hasPermission(?string $permission): bool
    {
        if (!$permission) {
            return true;
        }

        return $this->hasAnyPermission($permission);
    }

    public function hasAnyPermission(string|array|null $permissions): bool
    {
        $permissions = $this->normalizePermissions($permissions);

        if ($permissions === []) {
            return true;
        }

        // Super Admin is intentionally protected so the system owner cannot be locked out
        // while the editable role matrix remains the source of truth for every other role.
        if ($this->isSuperAdmin()) {
            return true;
        }

        $allowed = $this->permissionNames();

        foreach ($permissions as $permission) {
            if (isset($allowed[$permission])) {
                return true;
            }
        }

        return false;
    }

    private function normalizePermissions(string|array|null $permissions): array
    {
        if ($permissions === null) {
            return [];
        }

        $items = is_array($permissions) ? $permissions : [$permissions];
        $normalized = [];

        foreach ($items as $item) {
            if ($item === null) {
                continue;
            }

            foreach (preg_split('/[|,]/', (string) $item) ?: [] as $permission) {
                $permission = trim($permission);

                if ($permission !== '') {
                    $normalized[] = $permission;
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    private function permissionNames(): array
    {
        if ($this->permissionNameCache !== null) {
            return $this->permissionNameCache;
        }

        $this->loadMissing('roles.permissions');

        $this->permissionNameCache = $this->activeRoleCollection()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $permission) => [$permission => true])
            ->all();

        return $this->permissionNameCache;
    }

    private function activeRoleCollection()
    {
        $this->loadMissing('roles');

        return $this->roles
            ->filter(fn (Role $role) => ($role->status ?? 'Active') === 'Active')
            ->values();
    }

    public function flushAccessCache(): void
    {
        $this->permissionNameCache = null;
        $this->superAdminCache = null;
        $this->roleLevelCache = null;
        $this->unsetRelation('roles');
    }

    public function canViewRoute(?string $routeName): bool
    {
        $permissions = $this->viewPermissionsForRoute($routeName);

        return $permissions === [] || $this->hasAnyPermission($permissions);
    }

    public function viewPermissionForRoute(?string $routeName): ?string
    {
        return $this->viewPermissionsForRoute($routeName)[0] ?? null;
    }

    public function viewPermissionsForRoute(?string $routeName): array
    {
        if (!$routeName) {
            return [];
        }

        $routePermissions = config('access.route_permissions', []);

        if (isset($routePermissions[$routeName])) {
            return (array) $routePermissions[$routeName];
        }

        foreach ($routePermissions as $prefix => $permissions) {
            if (str_starts_with($routeName, $prefix . '.')) {
                return (array) $permissions;
            }
        }

        return [];
    }

    public function managePermissionForRoute(?string $routeName): string|array|null
    {
        if (!$routeName) {
            return null;
        }

        $managePermissions = config('access.manage_permissions', []);

        if (isset($managePermissions[$routeName])) {
            return $managePermissions[$routeName];
        }

        foreach ($managePermissions as $prefix => $permission) {
            if (str_starts_with($routeName, $prefix . '.')) {
                return $permission;
            }
        }

        return null;
    }

    public function canViewFeature(?string $permission): bool
    {
        return $this->hasPermission($permission);
    }

    public function canManageFeature(?string $permission): bool
    {
        return $this->hasPermission($permission);
    }

    public function canManageUser(User $target): bool
    {
        if ((int) $this->id === (int) $target->id) {
            return false;
        }

        if (!$this->hasPermission('users.manage')) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->roleLevel() < $target->roleLevel() && !$target->isSuperAdmin();
    }

    public function canAssignRole(Role $role): bool
    {
        if (!$this->hasPermission('users.manage')) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return !$role->isSuperAdmin()
            && !$role->is_protected
            && (int) $role->level > $this->roleLevel();
    }

    public function manageableRoleIds(?EloquentCollection $roles = null): array
    {
        $roles ??= Role::query()->where('status', 'Active')->get();

        return $roles
            ->filter(fn (Role $role) => $this->canAssignRole($role))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
