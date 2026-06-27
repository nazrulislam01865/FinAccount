<?php

use App\Models\FinancialYear;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('opening_balances')) {
            Schema::create('opening_balances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('financial_year_id')->nullable()->constrained('financial_years')->nullOnDelete();
                $table->date('balance_date')->nullable();
                $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->foreignId('money_account_id')->nullable()->constrained('money_accounts')->nullOnDelete();
                $table->decimal('debit', 20, 2)->default(0);
                $table->decimal('credit', 20, 2)->default(0);
                $table->string('status', 20)->default('posted');
                $table->string('reference', 100)->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'financial_year_id', 'status'], 'opening_company_year_status_idx');
                $table->index(['company_id', 'chart_of_account_id'], 'opening_company_account_idx');
                $table->index(['company_id', 'party_id'], 'opening_company_party_idx');
                $table->index(['company_id', 'money_account_id'], 'opening_company_money_idx');
            });
        }

        $this->migrateExistingSetupBalances();
        $this->dropLegacySetupOpeningColumns();
    }

    public function down(): void
    {
        $this->restoreLegacySetupOpeningColumns();
        Schema::dropIfExists('opening_balances');
    }

    private function migrateExistingSetupBalances(): void
    {
        if (! Schema::hasTable('opening_balances') || ! Schema::hasTable('money_accounts') || ! Schema::hasTable('parties')) {
            return;
        }

        if (! Schema::hasColumn('money_accounts', 'opening_balance') && ! Schema::hasColumn('parties', 'opening_balance')) {
            return;
        }

        $now = now();

        if (Schema::hasColumn('money_accounts', 'opening_balance')) {
            DB::table('money_accounts')
                ->where('opening_balance', '>', 0)
                ->orderBy('id')
                ->get()
                ->each(function (object $moneyAccount) use ($now): void {
                    if (! $moneyAccount->chart_of_account_id) {
                        return;
                    }

                    $context = $this->companyYearContext((int) $moneyAccount->company_id);

                    DB::table('opening_balances')->updateOrInsert(
                        [
                            'company_id' => (int) $moneyAccount->company_id,
                            'money_account_id' => (int) $moneyAccount->id,
                            'chart_of_account_id' => (int) $moneyAccount->chart_of_account_id,
                            'party_id' => null,
                            'reference' => 'Migrated from Money Account setup',
                        ],
                        [
                            'financial_year_id' => $context['financial_year_id'],
                            'balance_date' => $context['balance_date'],
                            'debit' => (float) $moneyAccount->opening_balance,
                            'credit' => 0,
                            'status' => 'posted',
                            'note' => 'Auto-migrated from old Money Account opening balance field.',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                });
        }

        if (Schema::hasColumn('parties', 'opening_balance')) {
            DB::table('parties')
                ->where('opening_balance', '>', 0)
                ->orderBy('id')
                ->get()
                ->each(function (object $party) use ($now): void {
                    $context = $this->companyYearContext((int) $party->company_id);
                    $accountId = $party->receivable_account_id ?: $party->payable_account_id;

                    if (! $accountId) {
                        return;
                    }

                    $isReceivable = (bool) $party->receivable_account_id;

                    DB::table('opening_balances')->updateOrInsert(
                        [
                            'company_id' => (int) $party->company_id,
                            'party_id' => (int) $party->id,
                            'chart_of_account_id' => (int) $accountId,
                            'money_account_id' => null,
                            'reference' => 'Migrated from Party setup',
                        ],
                        [
                            'financial_year_id' => $context['financial_year_id'],
                            'balance_date' => $context['balance_date'],
                            'debit' => $isReceivable ? (float) $party->opening_balance : 0,
                            'credit' => $isReceivable ? 0 : (float) $party->opening_balance,
                            'status' => 'posted',
                            'note' => 'Auto-migrated from old Party opening balance field.',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                });
        }

        if (Schema::hasColumn('money_accounts', 'opening_balance')) {
            DB::table('money_accounts')->where('opening_balance', '!=', 0)->update(['opening_balance' => 0, 'updated_at' => $now]);
        }

        if (Schema::hasColumn('parties', 'opening_balance')) {
            DB::table('parties')->where('opening_balance', '!=', 0)->update(['opening_balance' => 0, 'updated_at' => $now]);
        }
    }

    private function dropLegacySetupOpeningColumns(): void
    {
        if (Schema::hasTable('money_accounts') && Schema::hasColumn('money_accounts', 'opening_balance')) {
            Schema::table('money_accounts', function (Blueprint $table): void {
                $table->dropColumn('opening_balance');
            });
        }

        if (Schema::hasTable('parties') && Schema::hasColumn('parties', 'opening_balance')) {
            Schema::table('parties', function (Blueprint $table): void {
                $table->dropColumn('opening_balance');
            });
        }
    }

    private function restoreLegacySetupOpeningColumns(): void
    {
        if (Schema::hasTable('money_accounts') && ! Schema::hasColumn('money_accounts', 'opening_balance')) {
            Schema::table('money_accounts', function (Blueprint $table): void {
                $table->decimal('opening_balance', 20, 2)->default(0);
            });
        }

        if (Schema::hasTable('parties') && ! Schema::hasColumn('parties', 'opening_balance')) {
            Schema::table('parties', function (Blueprint $table): void {
                $table->decimal('opening_balance', 20, 2)->default(0);
            });
        }
    }

    /** @return array{financial_year_id:?int,balance_date:?string} */
    private function companyYearContext(int $companyId): array
    {
        $company = DB::table('companies')->where('id', $companyId)->first();
        $financialYear = null;

        if ($company?->default_financial_year_id) {
            $financialYear = DB::table('financial_years')->where('id', $company->default_financial_year_id)->first();
        }

        $financialYear ??= DB::table('financial_years')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('status', FinancialYear::STATUS_OPEN)
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->first();

        return [
            'financial_year_id' => $financialYear?->id ? (int) $financialYear->id : null,
            'balance_date' => $financialYear?->start_date ?: now()->toDateString(),
        ];
    }
};
