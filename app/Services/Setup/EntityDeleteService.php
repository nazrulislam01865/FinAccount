<?php

namespace App\Services\Setup;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\LedgerMappingRule;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Models\VoucherNumberingRule;
use Illuminate\Support\Facades\DB;

class EntityDeleteService
{
    public function deleteChartOfAccount(ChartOfAccount $account): void
    {
        DB::transaction(function () use ($account) {
            $accountId = $account->id;

            DB::table('cash_bank_accounts')
                ->where('linked_ledger_account_id', $accountId)
                ->pluck('id')
                ->each(fn ($id) => $this->deleteCashBankAccountById((int) $id));

            DB::table('parties')
                ->where('linked_ledger_account_id', $accountId)
                ->pluck('id')
                ->each(fn ($id) => $this->deletePartyById((int) $id));

            $this->deleteVoucherHeadersForAccount($accountId);

            DB::table('opening_balances')
                ->where('account_id', $accountId)
                ->delete();

            DB::table('ledger_mapping_rules')
                ->where('debit_account_id', $accountId)
                ->orWhere('credit_account_id', $accountId)
                ->delete();

            DB::table('party_types')
                ->where('default_ledger_account_id', $accountId)
                ->update(['default_ledger_account_id' => null]);

            DB::table('chart_of_accounts')
                ->where('parent_id', $accountId)
                ->update(['parent_id' => null]);

            DB::table('chart_of_accounts')
                ->where('id', $accountId)
                ->delete();
        });
    }

    public function deleteCashBankAccount(CashBankAccount $account): void
    {
        DB::transaction(fn () => $this->deleteCashBankAccountById($account->id));
    }

    public function deleteParty(Party $party): void
    {
        DB::transaction(fn () => $this->deletePartyById($party->id));
    }

    public function deleteTransactionHead(TransactionHead $head): void
    {
        DB::transaction(function () use ($head) {
            $headId = $head->id;

            $this->deleteVoucherHeadersForTransactionHead($headId);

            DB::table('ledger_mapping_rules')
                ->where('transaction_head_id', $headId)
                ->delete();

            DB::table('settlement_type_transaction_head')
                ->where('transaction_head_id', $headId)
                ->delete();

            DB::table('transaction_heads')
                ->where('id', $headId)
                ->delete();
        });
    }

    public function deleteLedgerMappingRule(LedgerMappingRule $rule): void
    {
        DB::table('ledger_mapping_rules')
            ->where('id', $rule->id)
            ->delete();
    }

    public function deleteVoucherNumberingRule(VoucherNumberingRule $rule): void
    {
        DB::table('voucher_numbering_rules')
            ->where('id', $rule->id)
            ->delete();
    }

    public function deleteUser(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            DB::table('role_user')
                ->where('user_id', $userId)
                ->delete();

            DB::table('users')
                ->where('id', $userId)
                ->delete();
        });
    }

    private function deleteCashBankAccountById(int $accountId): void
    {
        $this->deleteVoucherHeadersByIds(
            DB::table('voucher_headers')
                ->where('cash_bank_account_id', $accountId)
                ->pluck('id')
                ->all()
        );

        DB::table('cash_bank_accounts')
            ->where('id', $accountId)
            ->delete();
    }

    private function deletePartyById(int $partyId): void
    {
        $voucherHeaderIds = collect()
            ->merge(DB::table('voucher_headers')->where('party_id', $partyId)->pluck('id'))
            ->merge(DB::table('voucher_details')->where('party_id', $partyId)->pluck('voucher_header_id'))
            ->merge(DB::table('due_register')->where('party_id', $partyId)->pluck('voucher_header_id'))
            ->merge(DB::table('advance_register')->where('party_id', $partyId)->pluck('voucher_header_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->deleteVoucherHeadersByIds($voucherHeaderIds);

        DB::table('opening_balances')
            ->where('party_id', $partyId)
            ->delete();

        DB::table('parties')
            ->where('id', $partyId)
            ->delete();
    }

    private function deleteVoucherHeadersForTransactionHead(int $transactionHeadId): void
    {
        $this->deleteVoucherHeadersByIds(
            DB::table('voucher_headers')
                ->where('transaction_head_id', $transactionHeadId)
                ->pluck('id')
                ->all()
        );
    }

    private function deleteVoucherHeadersForAccount(int $accountId): void
    {
        $voucherHeaderIds = collect()
            ->merge(DB::table('voucher_details')->where('account_id', $accountId)->pluck('voucher_header_id'))
            ->merge(DB::table('due_register')->where('account_id', $accountId)->pluck('voucher_header_id'))
            ->merge(DB::table('advance_register')->where('account_id', $accountId)->pluck('voucher_header_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->deleteVoucherHeadersByIds($voucherHeaderIds);
    }

    private function deleteVoucherHeadersByIds(array $ids): void
    {
        $ids = collect($ids)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return;
        }

        DB::table('voucher_headers')
            ->whereIn('id', $ids)
            ->delete();
    }
}
