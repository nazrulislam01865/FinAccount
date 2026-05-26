<?php

namespace App\Services\Setup;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\LedgerMappingRule;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Models\VoucherNumberingRule;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EntityDeleteService
{
    public function deleteChartOfAccount(ChartOfAccount $account): void
    {
        if ($account->is_system_ledger) {
            throw new RuntimeException('System ledgers are protected and cannot be deleted. Deactivate the ledger instead if it should not appear in new setup.');
        }

        $accountId = (int) $account->id;

        $this->blockIfExists('chart_of_accounts', 'parent_id', $accountId, 'This account has child accounts. Deactivate it or move child accounts before deleting.');
        $this->blockIfExists('cash_bank_accounts', 'linked_ledger_account_id', $accountId, 'This account is linked to Cash/Bank Setup and cannot be deleted.');
        $this->blockIfExists('parties', 'linked_ledger_account_id', $accountId, 'This account is linked to Party/Person Setup and cannot be deleted.');
        $this->blockIfExists('opening_balances', 'account_id', $accountId, 'This account is used in Opening Balance and cannot be deleted.');
        $this->blockIfExists('voucher_details', 'account_id', $accountId, 'This account is used in voucher details and cannot be deleted.');
        $this->blockIfExists('due_register', 'account_id', $accountId, 'This account is used in Due Management and cannot be deleted.');
        $this->blockIfExists('advance_register', 'account_id', $accountId, 'This account is used in Advance Management and cannot be deleted.');
        $this->blockIfExists('party_types', 'default_ledger_account_id', $accountId, 'This account is used as a party default ledger and cannot be deleted.');

        if (DB::table('ledger_mapping_rules')
            ->where('debit_account_id', $accountId)
            ->orWhere('credit_account_id', $accountId)
            ->orWhere('primary_ledger_id', $accountId)
            ->orWhere('fixed_counter_ledger_id', $accountId)
            ->exists()) {
            throw new RuntimeException('This account is used in Accounting Rules Setup and cannot be deleted. Deactivate it after replacing the rule mapping.');
        }

        $account->delete();
    }

    public function deleteCashBankAccount(CashBankAccount $account): void
    {
        $this->blockIfExists('voucher_headers', 'cash_bank_account_id', (int) $account->id, 'This cash/bank account is used by vouchers and cannot be deleted. Deactivate it instead.');

        DB::transaction(fn () => $this->deleteCashBankAccountById($account->id));
    }

    public function deleteParty(Party $party): void
    {
        DB::transaction(fn () => $this->deletePartyById($party->id));
    }

    public function deleteTransactionHead(TransactionHead $head): void
    {
        $headId = (int) $head->id;

        $this->blockIfExists('voucher_headers', 'transaction_head_id', $headId, 'This transaction head is used by vouchers and cannot be deleted. Deactivate it instead.');
        $this->blockIfExists('ledger_mapping_rules', 'transaction_head_id', $headId, 'This transaction head has accounting rules. Delete or deactivate the rules before deleting the head.');

        DB::transaction(function () use ($headId) {
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
        if ((int) $rule->last_number > 0) {
            throw new RuntimeException('This voucher numbering rule has already generated voucher numbers and cannot be deleted. Deactivate it instead.');
        }

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

    private function blockIfExists(string $table, string $column, int $value, string $message): void
    {
        if (DB::table($table)->where($column, $value)->exists()) {
            throw new RuntimeException($message);
        }
    }
}
