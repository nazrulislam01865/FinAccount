<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_rules', 'transaction_head_id')) {
                $table->foreignId('transaction_head_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('transaction_heads')
                    ->nullOnDelete();

                $table->index(
                    ['company_id', 'transaction_head_id', 'category', 'settlement_type', 'is_active'],
                    'rule_company_head_type_settlement_idx',
                );
            }
        });

        $this->backfillExistingFeedRules();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('accounting_rules', 'transaction_head_id')) {
            return;
        }

        DB::table('accounting_rules')
            ->where('code', 'like', 'SYS-FEED-RULE-%')
            ->delete();

        Schema::table('accounting_rules', function (Blueprint $table): void {
            $table->dropIndex('rule_company_head_type_settlement_idx');
            $table->dropConstrainedForeignId('transaction_head_id');
        });
    }

    private function backfillExistingFeedRules(): void
    {
        if (! Schema::hasTable('feed_settings') || ! Schema::hasTable('accounting_rule_lines')) {
            return;
        }

        $settings = DB::table('feed_settings')->get();

        foreach ($settings as $setting) {
            $purchaseHead = DB::table('transaction_heads')->where('id', $setting->purchase_transaction_head_id)->first();
            $saleHead = DB::table('transaction_heads')->where('id', $setting->sale_transaction_head_id)->first();

            if ($purchaseHead) {
                $this->ensureFeedRules(
                    (int) $setting->company_id,
                    (int) $purchaseHead->id,
                    'PURCHASE',
                    'Supplier',
                    false,
                    'PUR',
                );
            }

            if ($saleHead) {
                $this->ensureFeedRules(
                    (int) $setting->company_id,
                    (int) $saleHead->id,
                    'SALE',
                    'Customer',
                    true,
                    'SAL',
                );
            }
        }
    }

    private function ensureFeedRules(
        int $companyId,
        int $headId,
        string $category,
        string $partyType,
        bool $generatesInvoice,
        string $shortCode,
    ): void {
        foreach (['CASH', 'CREDIT', 'PARTIAL'] as $settlement) {
            $exists = DB::table('accounting_rules')
                ->where('company_id', $companyId)
                ->where('transaction_head_id', $headId)
                ->where('category', $category)
                ->where('settlement_type', $settlement)
                ->exists();

            if ($exists) {
                continue;
            }

            $baseCode = 'SYS-FEED-RULE-'.$shortCode.'-'.$settlement;
            $code = $this->availableCode($companyId, $baseCode);
            $template = $this->template($category, $settlement);
            $firstDebit = collect($template)->firstWhere('line_side', 'debit');
            $firstCredit = collect($template)->firstWhere('line_side', 'credit');
            $now = now();

            $ruleId = DB::table('accounting_rules')->insertGetId([
                'company_id' => $companyId,
                'transaction_head_id' => $headId,
                'code' => $code,
                'name' => ($category === 'SALE' ? 'Feed Sale' : 'Feed Purchase').' — '.$this->settlementLabel($settlement),
                'category' => $category,
                'settlement_type' => $settlement,
                'debit_source' => $firstDebit['account_source'],
                'credit_source' => $firstCredit['account_source'],
                'party_required' => true,
                'party_type' => $partyType,
                'money_required' => in_array($settlement, ['CASH', 'PARTIAL'], true),
                'generates_invoice' => $generatesInvoice,
                'invoice_title' => $generatesInvoice ? 'Feed Sales Invoice' : null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (array_values($template) as $index => $line) {
                DB::table('accounting_rule_lines')->insert([
                    'accounting_rule_id' => $ruleId,
                    'line_side' => $line['line_side'],
                    'account_source' => $line['account_source'],
                    'amount_basis' => $line['amount_basis'],
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /** @return array<int, array{line_side:string,account_source:string,amount_basis:string}> */
    private function template(string $category, string $settlement): array
    {
        if ($category === 'SALE') {
            return match ($settlement) {
                'CASH' => [
                    ['line_side' => 'debit', 'account_source' => 'selected_money', 'amount_basis' => 'total'],
                    ['line_side' => 'credit', 'account_source' => 'head_account', 'amount_basis' => 'total'],
                ],
                'CREDIT' => [
                    ['line_side' => 'debit', 'account_source' => 'party_receivable', 'amount_basis' => 'total'],
                    ['line_side' => 'credit', 'account_source' => 'head_account', 'amount_basis' => 'total'],
                ],
                default => [
                    ['line_side' => 'debit', 'account_source' => 'selected_money', 'amount_basis' => 'paid'],
                    ['line_side' => 'debit', 'account_source' => 'party_receivable', 'amount_basis' => 'due'],
                    ['line_side' => 'credit', 'account_source' => 'head_account', 'amount_basis' => 'total'],
                ],
            };
        }

        return match ($settlement) {
            'CASH' => [
                ['line_side' => 'debit', 'account_source' => 'head_account', 'amount_basis' => 'total'],
                ['line_side' => 'credit', 'account_source' => 'selected_money', 'amount_basis' => 'total'],
            ],
            'CREDIT' => [
                ['line_side' => 'debit', 'account_source' => 'head_account', 'amount_basis' => 'total'],
                ['line_side' => 'credit', 'account_source' => 'party_payable', 'amount_basis' => 'total'],
            ],
            default => [
                ['line_side' => 'debit', 'account_source' => 'head_account', 'amount_basis' => 'total'],
                ['line_side' => 'credit', 'account_source' => 'selected_money', 'amount_basis' => 'paid'],
                ['line_side' => 'credit', 'account_source' => 'party_payable', 'amount_basis' => 'due'],
            ],
        };
    }

    private function settlementLabel(string $settlement): string
    {
        return match ($settlement) {
            'CASH' => 'Paid in Full',
            'CREDIT' => 'Fully Due',
            default => 'Part Paid',
        };
    }

    private function availableCode(int $companyId, string $baseCode): string
    {
        $candidate = $baseCode;
        $suffix = 2;

        while (DB::table('accounting_rules')->where('company_id', $companyId)->where('code', $candidate)->exists()) {
            $candidate = $baseCode.'-'.$suffix++;
        }

        return $candidate;
    }
};
