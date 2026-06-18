<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Company;
use App\Models\User;
use App\Services\Company\CompanySetupDefaultsService;
use App\Support\AccountingRbac;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly CompanySetupDefaultsService $companySetupDefaults) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param array<string, string> $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $company = Company::query()->create([
                'code' => 'HG-'.Str::upper(Str::random(8)),
                'name' => $input['name'].' Business',
                'currency_code' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'status' => 'active',
            ]);

            $company = $this->companySetupDefaults->ensureForCompany($company);

            AccountingRbac::syncCompany((int) $company->id, true);
            $roleId = DB::table('accounting_roles')
                ->where('company_id', $company->id)
                ->where('slug', 'super_admin')
                ->value('id');

            $user = User::query()->create([
                'company_id' => $company->id,
                'accounting_role_id' => $roleId,
                'role' => User::ROLE_SYSTEM_ADMIN,
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            AccountingRbac::syncUserPermissionsFromRole($user);

            return $user;
        });
    }
}
