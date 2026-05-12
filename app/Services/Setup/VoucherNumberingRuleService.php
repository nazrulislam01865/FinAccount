<?php

namespace App\Services\Setup;

use App\Models\Company;
use App\Models\VoucherNumberingRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class VoucherNumberingRuleService
{
    public function create(array $data, ?int $userId = null): VoucherNumberingRule
    {
        return DB::transaction(function () use ($data, $userId) {
            $payload = $this->payload($data, $userId, true);
            $payload['last_number'] = max(0, (int) $payload['starting_number'] - 1);

            return VoucherNumberingRule::query()
                ->create($payload)
                ->fresh(['financialYear']);
        });
    }

    public function update(
        VoucherNumberingRule $rule,
        array $data,
        ?int $userId = null
    ): VoucherNumberingRule {
        return DB::transaction(function () use ($rule, $data, $userId) {
            $payload = $this->payload($data, $userId, false);

            if ($rule->last_number < ((int) $payload['starting_number'] - 1)) {
                $payload['last_number'] = (int) $payload['starting_number'] - 1;
            }

            $rule->update($payload);

            return $rule->fresh(['financialYear']);
        });
    }

    private function payload(array $data, ?int $userId, bool $creating): array
    {
        $payload = Arr::only($data, [
            'financial_year_id',
            'voucher_type',
            'prefix',
            'format_template',
            'starting_number',
            'number_length',
            'reset_every_year',
            'used_for',
            'status',
        ]);

        $payload['company_id'] = Company::query()->first()?->id;
        $payload['prefix'] = strtoupper($payload['prefix']);
        $payload['reset_every_year'] = (bool) ($payload['reset_every_year'] ?? true);

        if ($creating) {
            $payload['created_by'] = $userId;
        }

        $payload['updated_by'] = $userId;

        return $payload;
    }
}
