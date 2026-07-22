<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Access\AccountingPermission;
use App\Models\Access\AccountingRole;
use App\Support\AccountingRbac;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $accounting_role_id
 * @property string $role
 * @property string $account_status
 * @property string $name
 * @property string|null $position
 * @property string $email
 * @property string|null $profile_photo_path
 * @property Carbon|null $email_verified_at
 * @property string $password
 */
#[Fillable(['company_id', 'accounting_role_id', 'role', 'account_status', 'name', 'position', 'email', 'password', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'active_session_id'])]
class User extends Authenticatable implements PasskeyUser
{
    public const ROLE_SYSTEM_ADMIN = 'system_admin';
    public const ROLE_ACCOUNTING_USER = 'accounting_user';

    public const ACCOUNT_STATUS_ACTIVE = 'active';
    public const ACCOUNT_STATUS_INACTIVE = 'inactive';
    public const ACCOUNT_STATUS_STANDBY = 'standby';
    public const ACCOUNT_STATUS_DISABLED = 'disabled';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @var array<string, bool>|null */
    private ?array $accountingPermissionMapCache = null;

    private static ?bool $accountingPermissionSchemaReadyCache = null;
    private static ?bool $accountingUserPermissionTableReadyCache = null;
    private static ?bool $accountStatusColumnReadyCache = null;

    public static function accountStatusOptions(): array
    {
        return [
            self::ACCOUNT_STATUS_ACTIVE => 'Active',
            self::ACCOUNT_STATUS_INACTIVE => 'Inactive',
            self::ACCOUNT_STATUS_STANDBY => 'Stand By',
            self::ACCOUNT_STATUS_DISABLED => 'Disabled',
        ];
    }

    public function accountingRole(): BelongsTo
    {
        return $this->belongsTo(AccountingRole::class, 'accounting_role_id');
    }

    public function accountingPermissions()
    {
        return $this->belongsToMany(AccountingPermission::class, 'accounting_user_permissions', 'user_id', 'permission_id')
            ->withPivot('allowed');
    }

    public function accountStatusValue(): string
    {
        if (! self::accountStatusColumnReady()) {
            return self::ACCOUNT_STATUS_ACTIVE;
        }
        $status = strtolower(trim((string) ($this->account_status ?: self::ACCOUNT_STATUS_ACTIVE)));
        return array_key_exists($status, self::accountStatusOptions()) ? $status : self::ACCOUNT_STATUS_ACTIVE;
    }

    public function accountStatusLabel(): string
    {
        return self::accountStatusOptions()[$this->accountStatusValue()] ?? 'Active';
    }

    public function isAccountActive(): bool
    {
        return $this->accountStatusValue() === self::ACCOUNT_STATUS_ACTIVE;
    }

    public function isSystemAdmin(): bool
    {
        if (! self::accountingPermissionSchemaReady()) {
            return $this->role === self::ROLE_SYSTEM_ADMIN;
        }

        $role = $this->relationLoaded('accountingRole')
            ? $this->getRelation('accountingRole')
            : $this->accountingRole()->first();

        if (! $this->relationLoaded('accountingRole')) {
            $this->setRelation('accountingRole', $role);
        }

        return $this->isAccountActive()
            && $role?->slug === 'super_admin'
            && (bool) $role?->is_active
            && (int) $role?->company_id === (int) $this->company_id;
    }

    public function canManageAccountingConfiguration(): bool
    {
        return $this->canAnyAccounting([
            'company_setup.manage', 'business_types.manage', 'currencies.manage',
            'time_zones.manage', 'financial_years.manage',
            'chart_of_accounts.manage', 'opening_balances.manage', 'accounting_rules.manage', 'transaction_heads.manage',
            'transaction_categories.manage', 'voucher_numbering.manage', 'party_types.manage',
            'parties.manage', 'money_account_types.manage', 'money_accounts.manage',
        ]);
    }

    public function canDeleteAccountingRecords(): bool
    {
        return $this->canAccounting(AccountingRbac::DELETE_PERMISSION_KEY);
    }

    /** @return array<string, bool> */
    public function accountingPermissionMap(): array
    {
        if ($this->accountingPermissionMapCache !== null) {
            return $this->accountingPermissionMapCache;
        }

        if (! self::accountingPermissionSchemaReady()) {
            if ($this->role === self::ROLE_SYSTEM_ADMIN) {
                return $this->accountingPermissionMapCache = collect(AccountingRbac::permissions())
                    ->mapWithKeys(fn (array $permission): array => [(string) $permission['key'] => true])
                    ->all();
            }

            return $this->accountingPermissionMapCache = collect(AccountingRbac::defaultAllowedPermissions()['accountant'] ?? [])
                ->mapWithKeys(fn (string $key): array => [$key => true])
                ->all();
        }

        if (! $this->isAccountActive()) {
            return $this->accountingPermissionMapCache = [];
        }

        $role = $this->relationLoaded('accountingRole')
            ? $this->getRelation('accountingRole')
            : $this->accountingRole()->first();

        if (! $this->relationLoaded('accountingRole')) {
            $this->setRelation('accountingRole', $role);
        }

        if (! $role || ! $role->is_active || (int) $role->company_id !== (int) $this->company_id) {
            return $this->accountingPermissionMapCache = [];
        }

        if ($role->isSuperAdmin()) {
            return $this->accountingPermissionMapCache = DB::table('accounting_permissions')
                ->pluck('key')
                ->mapWithKeys(fn ($key): array => [(string) $key => true])
                ->all();
        }

        $query = DB::table('accounting_permissions as permissions')
            ->leftJoin('accounting_role_permissions as role_permissions', function ($join) use ($role): void {
                $join->on('role_permissions.permission_id', '=', 'permissions.id')
                    ->where('role_permissions.role_id', '=', (int) $role->id);
            });

        if (self::accountingUserPermissionTableReady()) {
            $query->leftJoin('accounting_user_permissions as user_permissions', function ($join): void {
                $join->on('user_permissions.permission_id', '=', 'permissions.id')
                    ->where('user_permissions.user_id', '=', (int) $this->id);
            });
        }

        $select = ['permissions.key', 'role_permissions.allowed as role_allowed'];
        $select[] = self::accountingUserPermissionTableReady()
            ? 'user_permissions.allowed as user_allowed'
            : DB::raw('NULL as user_allowed');

        return $this->accountingPermissionMapCache = $query
            ->select($select)
            ->get()
            ->mapWithKeys(function ($row): array {
                $allowed = $row->user_allowed !== null ? (bool) $row->user_allowed : (bool) $row->role_allowed;
                return [(string) $row->key => $allowed];
            })->all();
    }

    public function canAccounting(string $permissionKey): bool
    {
        return $this->isAccountActive() && ($this->accountingPermissionMap()[$permissionKey] ?? false);
    }

    public function canAnyAccounting(array $permissionKeys): bool
    {
        $map = $this->accountingPermissionMap();
        foreach ($permissionKeys as $permissionKey) {
            if ($map[(string) $permissionKey] ?? false) {
                return true;
            }
        }
        return false;
    }

    public function forgetAccountingPermissionMap(): void
    {
        $this->accountingPermissionMapCache = null;
    }

    public function roleLabel(): string
    {
        if (self::accountingPermissionSchemaReady()) {
            return $this->accountingRole?->name ?? 'No Role';
        }
        return $this->isSystemAdmin() ? 'System Admin' : 'Accounting User';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'accounting_role_id' => 'integer',
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->take(2)->map(fn ($word) => Str::substr($word, 0, 1))->implode('');
    }

    private static function accountingPermissionSchemaReady(): bool
    {
        return self::$accountingPermissionSchemaReadyCache ??= Schema::hasTable('users')
            && Schema::hasColumn('users', 'accounting_role_id')
            && Schema::hasTable('accounting_roles')
            && Schema::hasTable('accounting_permissions')
            && Schema::hasTable('accounting_role_permissions');
    }

    private static function accountingUserPermissionTableReady(): bool
    {
        return self::$accountingUserPermissionTableReadyCache ??= Schema::hasTable('accounting_user_permissions');
    }

    private static function accountStatusColumnReady(): bool
    {
        return self::$accountStatusColumnReadyCache ??= Schema::hasTable('users') && Schema::hasColumn('users', 'account_status');
    }
}
