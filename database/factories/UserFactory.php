<?php

namespace Database\Factories;

use App\Models\Access\AccountingRole;
use App\Models\Company;
use App\Models\User;
use App\Services\Company\CompanySetupDefaultsService;
use App\Support\AccountingRbac;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if (! $user->company_id && Schema::hasTable('companies')) {
                $company = Company::query()->create([
                    'code' => 'TEST-'.Str::upper(Str::random(10)),
                    'name' => $user->name.' Company',
                    'currency_code' => 'BDT',
                    'timezone' => 'Asia/Dhaka',
                    'status' => 'active',
                ]);
                $user->forceFill(['company_id' => $company->id])->saveQuietly();
            }

            if ($user->company_id) {
                $company = Company::query()->find($user->company_id);
                if ($company) {
                    app(CompanySetupDefaultsService::class)->ensureForCompany($company);
                }
            }

            if (! $user->company_id || ! Schema::hasTable('accounting_roles')) {
                return;
            }

            AccountingRbac::syncCompany((int) $user->company_id);
            $slug = $user->role === User::ROLE_SYSTEM_ADMIN ? 'super_admin' : 'data_entry';
            $role = AccountingRole::query()
                ->where('company_id', $user->company_id)
                ->where('slug', $slug)
                ->first();

            if ($role) {
                $user->forceFill([
                    'accounting_role_id' => $role->id,
                    'account_status' => User::ACCOUNT_STATUS_ACTIVE,
                ])->saveQuietly();
                AccountingRbac::syncUserPermissionsFromRole($user);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => User::ROLE_SYSTEM_ADMIN,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Create a user who can enter transactions and view reports, but cannot manage setup.
     */
    public function accountingUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ACCOUNTING_USER,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
