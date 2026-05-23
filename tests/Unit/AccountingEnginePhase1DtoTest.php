<?php

namespace Tests\Unit;

use App\AccountingEngine\DTO\JournalLineData;
use App\AccountingEngine\DTO\PostingPreview;
use App\AccountingEngine\DTO\TransactionInput;
use App\Models\VoucherHeader;
use PHPUnit\Framework\TestCase;

class AccountingEnginePhase1DtoTest extends TestCase
{
    public function test_posting_preview_preserves_legacy_response_shape(): void
    {
        $preview = PostingPreview::fromLegacyPreview([
            'financial_year_id' => 1,
            'financial_year_name' => 'FY 2026',
            'voucher_type' => 'Payment Voucher',
            'voucher_number' => 'PV-0001',
            'voucher_date' => '2026-05-23',
            'transaction_head' => 'Rent Payment',
            'nature' => 'Expense',
            'settlement_type' => 'Cash',
            'party_ledger_effect' => 'No Effect',
            'cash_bank_effect' => 'Cash Out',
            'cash_bank_account_id' => 10,
            'entries' => [
                [
                    'account_id' => 100,
                    'account_code' => '5111',
                    'account_name' => 'Rent Expense',
                    'account_type' => 'Expense',
                    'entry_type' => 'Debit',
                    'debit' => 500,
                    'credit' => 0,
                    'source_label' => 'Primary Ledger',
                ],
                [
                    'account_id' => 200,
                    'account_code' => '1111',
                    'account_name' => 'Cash in Hand',
                    'account_type' => 'Asset',
                    'entry_type' => 'Credit',
                    'debit' => 0,
                    'credit' => 500,
                    'source_label' => 'Cash/Bank Ledger',
                ],
            ],
            'total_debit' => 500,
            'total_credit' => 500,
            'balanced' => true,
            'mapping_found' => true,
            'accounting_principle' => 'Debit must equal credit.',
        ]);

        $payload = $preview->toArray();

        $this->assertSame('Payment Voucher', $payload['voucher_type']);
        $this->assertTrue($payload['balanced']);
        $this->assertCount(2, $payload['entries']);
        $this->assertInstanceOf(JournalLineData::class, $preview->journalLines[0]);
    }


    public function test_transaction_input_legacy_payload_keeps_company_and_user_context(): void
    {
        $input = new TransactionInput(
            companyId: 7,
            userId: 99,
            transactionDate: '2026-05-23',
            transactionHeadId: 11,
            settlementTypeId: 3,
            partyId: 5,
            cashBankAccountId: 8,
            amount: 1500.50,
            referenceNo: 'REF-1',
            narration: 'Narration',
            status: VoucherHeader::STATUS_POSTED,
        );

        $payload = $input->toLegacyPayload();

        $this->assertSame(7, $payload['company_id']);
        $this->assertSame(99, $payload['user_id']);
        $this->assertSame('2026-05-23', $payload['voucher_date']);
        $this->assertSame(11, $payload['transaction_head_id']);
    }

    public function test_posting_status_constants_are_compatible(): void
    {
        $this->assertSame('Draft', VoucherHeader::STATUS_DRAFT);
        $this->assertSame('Posted', VoucherHeader::STATUS_POSTED);
    }
}
