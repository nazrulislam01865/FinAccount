<?php

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Services\Accounting\AccountingRuleService;
use App\Support\TransactionTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateTransactionTypeMetadata(true);

        if (! Schema::hasTable('companies')
            || ! Schema::hasTable('accounting_rules')
            || ! Schema::hasTable('accounting_rule_lines')) {
            return;
        }

        $service = app(AccountingRuleService::class);
        $settlementLabels = collect(TransactionTypes::settlementDefinitions())
            ->map(fn (array $definition): string => $definition['label'])
            ->all();

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach (TransactionTypes::definitions() as $type => $definition) {
                foreach (TransactionTypes::settlementCodes() as $settlement) {
                    $exists = AccountingRule::query()
                        ->where('company_id', $companyId)
                        ->where('category', $type)
                        ->where('settlement_type', $settlement)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $service->create([
                        'code' => $this->uniqueRuleCode((int) $companyId, $type, $settlement),
                        'name' => $definition['label'].' — '.($settlementLabels[$settlement] ?? $settlement),
                        'category' => $type,
                        'settlement_type' => $settlement,
                        'is_active' => true,
                    ], (int) $companyId);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('accounting_rules')) {
            AccountingRule::query()
                ->where('code', 'like', 'SYS-ALLPAY-%')
                ->whereDoesntHave('transactionHeads')
                ->delete();
        }

        $this->updateTransactionTypeMetadata(false);
    }

    private function updateTransactionTypeMetadata(bool $allowAll): void
    {
        if (! Schema::hasTable('accounting_options')) {
            return;
        }

        $legacyAllowed = [
            TransactionTypes::SALE => TransactionTypes::ALL_SETTLEMENTS,
            TransactionTypes::PURCHASE => TransactionTypes::ALL_SETTLEMENTS,
            TransactionTypes::CUSTOMER_COLLECTION => [TransactionTypes::CASH],
            TransactionTypes::SUPPLIER_PAYMENT => [TransactionTypes::CASH],
            TransactionTypes::EXPENSE => TransactionTypes::ALL_SETTLEMENTS,
            TransactionTypes::OWNER_INVESTMENT => [TransactionTypes::CASH],
            TransactionTypes::OWNER_WITHDRAWAL => [TransactionTypes::CASH],
            TransactionTypes::LOAN_RECEIVED => [TransactionTypes::CASH],
            TransactionTypes::LOAN_REPAYMENT => [TransactionTypes::CASH],
            TransactionTypes::LOAN_INTEREST_PAYMENT => [TransactionTypes::CASH],
            TransactionTypes::ASSET_PURCHASE => TransactionTypes::ALL_SETTLEMENTS,
        ];

        AccountingOption::query()
            ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->whereIn('value', array_keys(TransactionTypes::definitions()))
            ->get()
            ->each(function (AccountingOption $option) use ($allowAll, $legacyAllowed): void {
                $metadata = (array) ($option->metadata ?? []);
                $metadata['allowed_settlements'] = $allowAll
                    ? TransactionTypes::ALL_SETTLEMENTS
                    : ($legacyAllowed[$option->value] ?? [TransactionTypes::CASH]);
                $metadata['default_settlements'] = [TransactionTypes::CASH];

                $option->forceFill(['metadata' => $metadata])->save();
            });
    }

    private function uniqueRuleCode(int $companyId, string $type, string $settlement): string
    {
        $base = 'SYS-ALLPAY-'.str_replace('_', '-', $type).'-'.$settlement;
        $code = substr($base, 0, 50);
        $suffix = 2;

        while (AccountingRule::query()->where('company_id', $companyId)->where('code', $code)->exists()) {
            $tail = '-'.$suffix++;
            $code = substr($base, 0, 50 - strlen($tail)).$tail;
        }

        return $code;
    }
};
