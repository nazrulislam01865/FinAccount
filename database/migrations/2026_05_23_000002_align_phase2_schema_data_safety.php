<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alignFinancialYears();
        $this->alignTransactionHeads();
        $this->alignVoucherHeaders();
        $this->alignVoucherDetails();
    }

    public function down(): void
    {
        Schema::table('voucher_details', function (Blueprint $table) {
            $this->dropIndexIfExists('voucher_details', 'vd_company_date_account_idx');
            $this->dropIndexIfExists('voucher_details', 'vd_company_date_party_idx');
            $this->dropIndexIfExists('voucher_details', 'vd_rule_line_idx');

            foreach (['amount_source', 'rule_line_id', 'transaction_date', 'branch_id', 'company_id'] as $column) {
                if (Schema::hasColumn('voucher_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('voucher_headers', function (Blueprint $table) {
            $this->dropIndexIfExists('voucher_headers', 'vh_company_number_unique');
            $this->dropIndexIfExists('voucher_headers', 'vh_company_year_status_idx');
            $this->dropIndexIfExists('voucher_headers', 'vh_company_date_idx');
            $this->dropIndexIfExists('voucher_headers', 'vh_lifecycle_status_idx');

            foreach ([
                'void_reason',
                'voided_by',
                'voided_at',
                'posted_by',
                'approved_by',
                'approved_at',
                'submitted_by',
                'submitted_at',
            ] as $column) {
                if (Schema::hasColumn('voucher_headers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('transaction_heads', function (Blueprint $table) {
            $this->dropIndexIfExists('transaction_heads', 'th_company_category_status_idx');
            $this->dropIndexIfExists('transaction_heads', 'th_company_screen_status_idx');

            if (Schema::hasColumn('transaction_heads', 'default_primary_ledger_id')) {
                $this->dropForeignIfExists('transaction_heads', 'transaction_heads_default_primary_ledger_id_foreign');
            }

            foreach ([
                'transaction_screen',
                'party_required_mode',
                'payment_method_required',
                'default_movement',
                'default_primary_ledger_id',
                'category',
            ] as $column) {
                if (Schema::hasColumn('transaction_heads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('financial_years', function (Blueprint $table) {
            $this->dropIndexIfExists('financial_years', 'fy_company_current_idx');
            $this->dropIndexIfExists('financial_years', 'fy_company_status_dates_idx');

            foreach (['is_current', 'lock_date'] as $column) {
                if (Schema::hasColumn('financial_years', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function alignFinancialYears(): void
    {
        if (! Schema::hasTable('financial_years')) {
            return;
        }

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('financial_years', 'status')) {
            DB::statement("ALTER TABLE financial_years MODIFY status VARCHAR(20) NOT NULL DEFAULT 'Open'");
        }

        Schema::table('financial_years', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_years', 'lock_date')) {
                $table->date('lock_date')->nullable()->after('end_date');
            }

            if (! Schema::hasColumn('financial_years', 'is_current')) {
                $table->boolean('is_current')->default(false)->after('is_active');
            }
        });

        DB::table('financial_years')
            ->where('status', 'Active')
            ->update(['status' => 'Open']);

        DB::table('financial_years')
            ->where('status', 'Inactive')
            ->update(['status' => 'Closed']);

        if (Schema::hasColumn('financial_years', 'is_current')) {
            DB::table('financial_years')
                ->where('is_active', true)
                ->update(['is_current' => true]);
        }

        $this->addIndexIfMissing('financial_years', 'fy_company_current_idx', ['company_id', 'is_current']);
        $this->addIndexIfMissing('financial_years', 'fy_company_status_dates_idx', ['company_id', 'status', 'start_date', 'end_date']);
    }

    private function alignTransactionHeads(): void
    {
        if (! Schema::hasTable('transaction_heads')) {
            return;
        }

        Schema::table('transaction_heads', function (Blueprint $table) {
            if (! Schema::hasColumn('transaction_heads', 'category')) {
                $table->string('category', 50)->nullable()->after('nature');
            }

            if (! Schema::hasColumn('transaction_heads', 'default_primary_ledger_id')) {
                $table->foreignId('default_primary_ledger_id')
                    ->nullable()
                    ->after('default_party_type_id')
                    ->constrained('chart_of_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('transaction_heads', 'default_movement')) {
                $table->string('default_movement', 20)->nullable()->after('default_primary_ledger_id');
            }

            if (! Schema::hasColumn('transaction_heads', 'payment_method_required')) {
                $table->boolean('payment_method_required')->default(false)->after('default_movement');
            }

            if (! Schema::hasColumn('transaction_heads', 'party_required_mode')) {
                $table->string('party_required_mode', 20)->default('No')->after('payment_method_required');
            }

            if (! Schema::hasColumn('transaction_heads', 'transaction_screen')) {
                $table->string('transaction_screen', 100)->nullable()->after('party_required_mode');
            }
        });

        DB::table('transaction_heads')->orderBy('id')->chunkById(100, function ($heads) {
            foreach ($heads as $head) {
                DB::table('transaction_heads')->where('id', $head->id)->update([
                    'category' => $head->category ?: $head->nature,
                    'default_movement' => $head->default_movement ?: $this->inferDefaultMovement((string) $head->nature),
                    'party_required_mode' => $head->party_required_mode ?: ((bool) $head->requires_party ? 'Required' : 'No'),
                    'payment_method_required' => (bool) ($head->payment_method_required ?? false),
                    'transaction_screen' => $head->transaction_screen ?: $this->inferTransactionScreen((string) $head->nature),
                ]);
            }
        });

        $this->addIndexIfMissing('transaction_heads', 'th_company_category_status_idx', ['company_id', 'category', 'status']);
        $this->addIndexIfMissing('transaction_heads', 'th_company_screen_status_idx', ['company_id', 'transaction_screen', 'status']);
    }

    private function alignVoucherHeaders(): void
    {
        if (! Schema::hasTable('voucher_headers')) {
            return;
        }

        Schema::table('voucher_headers', function (Blueprint $table) {
            if (! Schema::hasColumn('voucher_headers', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('voucher_headers', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('voucher_headers', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('submitted_by');
            }

            if (! Schema::hasColumn('voucher_headers', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('voucher_headers', 'posted_by')) {
                $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('voucher_headers', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('posted_by');
            }

            if (! Schema::hasColumn('voucher_headers', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('voucher_headers', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('voided_by');
            }
        });

        DB::table('voucher_headers')
            ->where('status', 'Posted')
            ->whereNull('posted_by')
            ->update(['posted_by' => DB::raw('created_by')]);

        $this->addUniqueIfMissing('voucher_headers', 'vh_company_number_unique', ['company_id', 'voucher_number']);
        $this->addIndexIfMissing('voucher_headers', 'vh_company_year_status_idx', ['company_id', 'financial_year_id', 'status']);
        $this->addIndexIfMissing('voucher_headers', 'vh_company_date_idx', ['company_id', 'voucher_date']);
        $this->addIndexIfMissing('voucher_headers', 'vh_lifecycle_status_idx', ['status', 'submitted_at', 'approved_at', 'posted_at', 'voided_at']);
    }

    private function alignVoucherDetails(): void
    {
        if (! Schema::hasTable('voucher_details')) {
            return;
        }

        Schema::table('voucher_details', function (Blueprint $table) {
            if (! Schema::hasColumn('voucher_details', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('voucher_header_id')->constrained('companies')->nullOnDelete();
            }

            if (! Schema::hasColumn('voucher_details', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('company_id');
            }

            if (! Schema::hasColumn('voucher_details', 'transaction_date')) {
                $table->date('transaction_date')->nullable()->after('branch_id');
            }

            if (! Schema::hasColumn('voucher_details', 'rule_line_id')) {
                $table->unsignedBigInteger('rule_line_id')->nullable()->after('party_id');
            }

            if (! Schema::hasColumn('voucher_details', 'amount_source')) {
                $table->string('amount_source', 50)->nullable()->after('rule_line_id');
            }
        });

        if (Schema::hasColumn('voucher_details', 'company_id') && Schema::hasColumn('voucher_details', 'transaction_date')) {
            DB::table('voucher_details as d')
                ->join('voucher_headers as v', 'v.id', '=', 'd.voucher_header_id')
                ->where(function ($query) {
                    $query->whereNull('d.company_id')
                        ->orWhereNull('d.transaction_date');
                })
                ->update([
                    'd.company_id' => DB::raw('v.company_id'),
                    'd.transaction_date' => DB::raw('v.voucher_date'),
                ]);
        }

        DB::table('voucher_details')
            ->whereNull('amount_source')
            ->update(['amount_source' => 'transaction_amount']);

        $this->addIndexIfMissing('voucher_details', 'vd_company_date_account_idx', ['company_id', 'transaction_date', 'account_id']);
        $this->addIndexIfMissing('voucher_details', 'vd_company_date_party_idx', ['company_id', 'transaction_date', 'party_id']);
        $this->addIndexIfMissing('voucher_details', 'vd_rule_line_idx', ['rule_line_id']);
    }

    private function inferDefaultMovement(string $nature): string
    {
        return in_array($nature, ['Payment', 'Expense'], true) ? 'Decrease' : 'Increase';
    }

    private function inferTransactionScreen(string $nature): string
    {
        return match ($nature) {
            'Payment' => 'Payment Entry',
            'Receipt' => 'Receipt Entry',
            'Due' => 'Due Entry',
            'Advance' => 'Advance Entry',
            'Adjustment' => 'Adjustment Entry',
            'Expense' => 'Expense Entry',
            'Journal' => 'Journal Entry',
            default => 'Transaction Entry',
        };
    }

    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $index));
    }

    private function addUniqueIfMissing(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $index));
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
    }

    private function dropForeignIfExists(string $table, string $foreign): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $exists = DB::table('information_schema.table_constraints')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('constraint_name', $foreign)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();

        if ($exists) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($foreign));
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->whereRaw('table_schema = DATABASE()')
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        }

        if ($driver === 'sqlite') {
            foreach (DB::select('PRAGMA index_list(' . str_replace('"', '""', $table) . ')') as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }
        }

        if ($driver === 'pgsql') {
            return DB::table('pg_indexes')
                ->where('schemaname', 'public')
                ->where('tablename', $table)
                ->where('indexname', $index)
                ->exists();
        }

        return false;
    }
};
