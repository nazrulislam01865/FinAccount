<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\LedgerMappingRule;
use App\Models\PartyType;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use Illuminate\Database\Seeder;

class AdvanceAccountingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->first();

        $cash = $this->findPostingAccount(['cash'], 'Asset', cashBank: true)
            ?: $this->ensurePostingAccount('1010', 'Cash in Hand', 'Asset', true);

        $bank = $this->findPostingAccount(['bank'], 'Asset', cashBank: true)
            ?: $this->findPostingAccount(['current account'], 'Asset', cashBank: true)
            ?: $cash;

        $advanceAsset = $this->findPostingAccount(['advance to', 'advance paid', 'supplier advance', 'employee advance'], 'Asset')
            ?: $this->ensurePostingAccount('1200', 'Advance to Supplier / Employee', 'Asset', false);

        $advanceLiability = $this->findPostingAccount(['advance from', 'advance received', 'customer advance'], 'Liability')
            ?: $this->ensurePostingAccount('2030', 'Advance from Customer', 'Liability', false);

        $accountsReceivable = $this->findPostingAccount(['accounts receivable', 'customer due', 'receivable'], 'Asset')
            ?: $this->ensurePostingAccount('1100', 'Accounts Receivable', 'Asset', false);

        $accountsPayable = $this->findPostingAccount(['accounts payable', 'supplier due', 'payable'], 'Liability')
            ?: $this->ensurePostingAccount('2010', 'Accounts Payable', 'Liability', false);

        $cashSettlement = $this->ensureSettlement('CASH', 'Cash', 1);
        $bankSettlement = $this->ensureSettlement('BANK', 'Bank', 2);
        $adjustmentSettlement = $this->ensureSettlement('ADJUSTMENT', 'Adjustment', 6);

        $supplierPartyTypeId = PartyType::query()->where('name', 'Supplier')->value('id');
        $customerPartyTypeId = PartyType::query()->where('name', 'Customer')->value('id');

        $advancePaidHead = $this->ensureHead($company?->id, 'TH-008', 'Advance Paid', 'Payment', $supplierPartyTypeId, [$cashSettlement->id, $bankSettlement->id]);
        $advanceReceivedHead = $this->ensureHead($company?->id, 'TH-009', 'Advance Received', 'Receipt', $customerPartyTypeId, [$cashSettlement->id, $bankSettlement->id]);
        $advancePaidAdjustmentHead = $this->ensureHead($company?->id, 'TH-010', 'Advance Paid Adjustment', 'Journal', $supplierPartyTypeId, [$adjustmentSettlement->id]);
        $advanceReceivedAdjustmentHead = $this->ensureHead($company?->id, 'TH-011', 'Advance Received Adjustment', 'Journal', $customerPartyTypeId, [$adjustmentSettlement->id]);

        $this->upsertRule($company?->id, 'LM-ADV-001', $advancePaidHead, $cashSettlement, $advanceAsset, $cash, 'Increase Advance Asset', 'Advance paid in cash: Dr Advance to Supplier / Employee, Cr Cash.');
        $this->upsertRule($company?->id, 'LM-ADV-002', $advancePaidHead, $bankSettlement, $advanceAsset, $bank, 'Increase Advance Asset', 'Advance paid by bank: Dr Advance to Supplier / Employee, Cr Bank.');
        $this->upsertRule($company?->id, 'LM-ADV-003', $advanceReceivedHead, $cashSettlement, $cash, $advanceLiability, 'Increase Advance Liability', 'Advance received in cash: Dr Cash, Cr Advance from Customer.');
        $this->upsertRule($company?->id, 'LM-ADV-004', $advanceReceivedHead, $bankSettlement, $bank, $advanceLiability, 'Increase Advance Liability', 'Advance received by bank: Dr Bank, Cr Advance from Customer.');
        $this->upsertRule($company?->id, 'LM-ADV-005', $advancePaidAdjustmentHead, $adjustmentSettlement, $accountsPayable, $advanceAsset, 'Decrease Advance Asset', 'Advance paid adjusted with payable: Dr Accounts Payable, Cr Advance to Supplier / Employee.');
        $this->upsertRule($company?->id, 'LM-ADV-006', $advanceReceivedAdjustmentHead, $adjustmentSettlement, $advanceLiability, $accountsReceivable, 'Decrease Advance Liability', 'Advance received adjusted with receivable: Dr Advance from Customer, Cr Accounts Receivable.');
    }

    private function ensurePostingAccount(string $code, string $name, string $typeName, bool $cashBank): ChartOfAccount
    {
        $typeId = AccountType::query()->where('name', $typeName)->value('id');
        $normalBalance = AccountType::query()->whereKey($typeId)->value('normal_balance') ?: ($typeName === 'Liability' ? 'Credit' : 'Debit');

        return ChartOfAccount::query()->updateOrCreate(
            ['account_code' => $code],
            [
                'account_name' => $name,
                'account_level' => 'Ledger',
                'account_type_id' => $typeId,
                'normal_balance' => $normalBalance,
                'is_cash_bank' => $cashBank,
                'posting_allowed' => true,
                'status' => 'Active',
            ]
        );
    }

    private function findPostingAccount(array $keywords, string $typeName, ?bool $cashBank = null): ?ChartOfAccount
    {
        return ChartOfAccount::query()
            ->where('status', 'Active')
            ->where('posting_allowed', true)
            ->whereHas('accountType', fn ($query) => $query->where('name', $typeName))
            ->when($cashBank !== null, fn ($query) => $query->where('is_cash_bank', $cashBank))
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('account_name', 'like', '%' . $keyword . '%');
                }
            })
            ->orderBy('account_code')
            ->first();
    }

    private function ensureSettlement(string $code, string $name, int $sortOrder): SettlementType
    {
        return SettlementType::query()->updateOrCreate(
            ['code' => $code],
            ['name' => $name, 'status' => 'Active', 'sort_order' => $sortOrder]
        );
    }

    private function ensureHead(?int $companyId, string $code, string $name, string $nature, ?int $partyTypeId, array $settlementTypeIds): TransactionHead
    {
        $head = TransactionHead::query()->updateOrCreate(
            ['head_code' => $code],
            [
                'company_id' => $companyId,
                'name' => $name,
                'nature' => $nature,
                'default_party_type_id' => $partyTypeId,
                'requires_party' => true,
                'requires_reference' => false,
                'status' => 'Active',
            ]
        );

        $head->settlementTypes()->syncWithoutDetaching($settlementTypeIds);

        return $head;
    }

    private function upsertRule(
        ?int $companyId,
        string $code,
        TransactionHead $head,
        SettlementType $settlement,
        ChartOfAccount $debit,
        ChartOfAccount $credit,
        string $effect,
        string $description
    ): void {
        LedgerMappingRule::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'transaction_head_id' => $head->id,
                'settlement_type_id' => $settlement->id,
            ],
            [
                'rule_code' => $code,
                'debit_account_id' => $debit->id,
                'credit_account_id' => $credit->id,
                'party_ledger_effect' => $effect,
                'auto_post' => true,
                'description' => $description,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );
    }
}
