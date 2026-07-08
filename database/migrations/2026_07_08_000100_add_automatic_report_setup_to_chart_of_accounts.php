<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('chart_of_accounts', 'report_section')) {
                $table->string('report_section', 80)->nullable()->after('normal_balance');
            }

            if (! Schema::hasColumn('chart_of_accounts', 'cash_flow_section')) {
                $table->string('cash_flow_section', 40)->nullable()->after('report_section');
            }

            if (! Schema::hasColumn('chart_of_accounts', 'is_cash_bank')) {
                $table->boolean('is_cash_bank')->default(false)->after('cash_flow_section');
            }

            if (! Schema::hasColumn('chart_of_accounts', 'is_party_control')) {
                $table->boolean('is_party_control')->default(false)->after('is_cash_bank');
            }

            if (! Schema::hasColumn('chart_of_accounts', 'is_posting')) {
                $table->boolean('is_posting')->default(true)->after('is_party_control');
            }

            if (! Schema::hasColumn('chart_of_accounts', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_posting');
            }
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->index(['company_id', 'report_section', 'sort_order'], 'coa_company_report_sort_idx');
        });

        $this->backfillAutomaticReportSetup();
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropIndex('coa_company_report_sort_idx');
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            foreach (['report_section', 'cash_flow_section', 'is_cash_bank', 'is_party_control', 'is_posting', 'sort_order'] as $column) {
                if (Schema::hasColumn('chart_of_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillAutomaticReportSetup(): void
    {
        $accounts = DB::table('chart_of_accounts')
            ->orderBy('company_id')
            ->orderBy('level')
            ->orderBy('id')
            ->get();

        $byId = [];

        foreach ($accounts as $account) {
            $parent = isset($account->parent_id) && $account->parent_id ? ($byId[(int) $account->parent_id] ?? null) : null;
            $type = (string) $account->type;
            $name = (string) $account->name;
            $level = (int) ($account->level ?? 3);
            $reportSection = $this->guessReportSection($type, $name, $parent);
            $cashFlowSection = $this->guessCashFlowSection($type, $name, $reportSection);
            $isCashBank = $this->looksLikeCashBankAccount($type, $name);
            $isPartyControl = $this->looksLikePartyControlAccount($type, $name, $reportSection);
            $isPosting = $level >= 3;
            $sortOrder = $this->reportSortOrder($type, $reportSection);

            DB::table('chart_of_accounts')
                ->where('id', $account->id)
                ->update([
                    'report_section' => $reportSection,
                    'cash_flow_section' => $cashFlowSection,
                    'is_cash_bank' => $isCashBank,
                    'is_party_control' => $isPartyControl,
                    'is_posting' => $isPosting,
                    'sort_order' => $sortOrder,
                    'updated_at' => now(),
                ]);

            $account->report_section = $reportSection;
            $account->cash_flow_section = $cashFlowSection;
            $account->is_cash_bank = $isCashBank;
            $account->is_party_control = $isPartyControl;
            $account->is_posting = $isPosting;
            $account->sort_order = $sortOrder;
            $byId[(int) $account->id] = $account;
        }
    }

    private function guessReportSection(string $type, string $name, ?object $parent = null): string
    {
        $text = $this->normaliseText($name);

        if ($parent && (int) ($parent->level ?? 0) >= 2 && trim((string) ($parent->report_section ?? '')) !== '') {
            return (string) $parent->report_section;
        }

        return match ($type) {
            'Income' => $this->matchesAny($text, [
                'interest', 'discount received', 'gain', 'commission received', 'other income', 'misc income', 'non operating',
            ]) ? 'Other Income' : 'Revenue',

            'Expense' => match (true) {
                $this->matchesAny($text, [
                    'purchase', 'cost of sale', 'cost of sales', 'cogs', 'product cost', 'service cost', 'direct cost',
                    'production', 'raw material', 'factory', 'manufacturing', 'inventory cost',
                ]) => 'Cost of Sales',
                $this->matchesAny($text, [
                    'bank charge', 'bank fee', 'finance cost', 'interest', 'loan', 'processing fee', 'card charge',
                ]) => 'Financial Expense',
                $this->matchesAny($text, ['tax', 'vat', 'ait', 'income tax']) => 'Tax Expense',
                $this->matchesAny($text, [
                    'advertisement', 'advertising', 'marketing', 'delivery', 'sales commission', 'promotion', 'courier',
                ]) => 'Selling Expense',
                $this->matchesAny($text, ['office', 'admin', 'stationery', 'audit', 'legal', 'professional']) => 'Administrative Expense',
                default => 'Operating Expense',
            },

            'Asset' => match (true) {
                $this->matchesAny($text, [
                    'fixed', 'equipment', 'furniture', 'vehicle', 'building', 'land', 'machinery', 'computer',
                    'depreciation', 'non current', 'non-current',
                ]) => 'Fixed Asset',
                default => 'Current Asset',
            },

            'Liability' => $this->matchesAny($text, ['long term', 'long-term', 'non current', 'non-current'])
                ? 'Non Current Liability'
                : 'Current Liability',

            'Equity' => match (true) {
                $this->matchesAny($text, ['capital', 'owner']) => 'Owner Capital',
                $this->matchesAny($text, ['retained']) => 'Retained Earnings',
                default => 'Equity',
            },

            default => 'General',
        };
    }

    private function guessCashFlowSection(string $type, string $name, string $reportSection): ?string
    {
        if ($this->looksLikeCashBankAccount($type, $name)) {
            return 'Cash Bank';
        }

        return match ($type) {
            'Income', 'Expense' => 'Operating',
            'Asset' => $reportSection === 'Current Asset' ? 'Operating' : 'Investing',
            'Liability', 'Equity' => 'Financing',
            default => null,
        };
    }

    private function looksLikeCashBankAccount(string $type, string $name): bool
    {
        return $type === 'Asset' && $this->matchesAny($this->normaliseText($name), [
            'cash', 'bank', 'petty cash', 'bkash', 'b-kash', 'nagad', 'rocket', 'wallet', 'mobile banking', 'card',
        ]);
    }

    private function looksLikePartyControlAccount(string $type, string $name, string $reportSection): bool
    {
        $text = $this->normaliseText($name);

        if (in_array($type, ['Asset', 'Liability'], true) && $this->matchesAny($text, [
            'receivable', 'payable', 'customer', 'supplier', 'vendor', 'party', 'due', 'advance',
        ])) {
            return true;
        }

        return in_array($reportSection, ['Current Asset', 'Current Liability'], true)
            && $this->matchesAny($text, ['receivable', 'payable']);
    }

    private function reportSortOrder(string $type, string $reportSection): int
    {
        return [
            'Current Asset' => 100,
            'Fixed Asset' => 120,
            'Non Current Asset' => 130,
            'Current Liability' => 200,
            'Non Current Liability' => 220,
            'Equity' => 300,
            'Owner Capital' => 310,
            'Retained Earnings' => 320,
            'Revenue' => 400,
            'Cost of Sales' => 500,
            'Operating Expense' => 600,
            'Administrative Expense' => 610,
            'Selling Expense' => 620,
            'Financial Expense' => 700,
            'Other Income' => 800,
            'Tax Expense' => 900,
        ][$reportSection] ?? match ($type) {
            'Asset' => 100,
            'Liability' => 200,
            'Equity' => 300,
            'Income' => 400,
            'Expense' => 600,
            default => 999,
        };
    }

    /** @param array<int, string> $needles */
    private function matchesAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normaliseText(string $value): string
    {
        return str_replace(['_', '-', '/', '.', ','], ' ', mb_strtolower(trim($value)));
    }
};
