<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('journal_headers')) {
            Schema::create('journal_headers', function (Blueprint $table) {
                $table->id();

                $table->foreignId('company_id')
                    ->nullable()
                    ->constrained('companies')
                    ->nullOnDelete();

                $table->foreignId('financial_year_id')
                    ->constrained('financial_years')
                    ->restrictOnDelete();

                $table->foreignId('voucher_header_id')
                    ->unique()
                    ->constrained('voucher_headers')
                    ->cascadeOnDelete();

                $table->string('journal_no', 80)->unique();
                $table->string('voucher_number', 80)->nullable()->index();
                $table->string('voucher_type', 100)->nullable()->index();
                $table->string('source_type', 60)->default('Voucher');
                $table->date('journal_date');

                $table->foreignId('transaction_head_id')
                    ->nullable()
                    ->constrained('transaction_heads')
                    ->nullOnDelete();

                $table->foreignId('party_id')
                    ->nullable()
                    ->constrained('parties')
                    ->nullOnDelete();

                $table->decimal('amount', 18, 2)->default(0);
                $table->decimal('total_debit', 18, 2)->default(0);
                $table->decimal('total_credit', 18, 2)->default(0);
                $table->string('status', 40)->default('Draft')->index();
                $table->text('narration')->nullable();

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignId('submitted_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignId('approved_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignId('posted_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'journal_date', 'status'], 'jh_company_date_status_idx');
                $table->index(['financial_year_id', 'status'], 'jh_year_status_idx');
            });
        }

        if (! Schema::hasTable('journal_lines')) {
            Schema::create('journal_lines', function (Blueprint $table) {
                $table->id();

                $table->foreignId('journal_header_id')
                    ->constrained('journal_headers')
                    ->cascadeOnDelete();

                $table->foreignId('voucher_detail_id')
                    ->nullable()
                    ->unique()
                    ->constrained('voucher_details')
                    ->nullOnDelete();

                $table->unsignedInteger('line_no')->default(1);

                $table->foreignId('ledger_id')
                    ->constrained('chart_of_accounts')
                    ->restrictOnDelete();

                $table->foreignId('party_id')
                    ->nullable()
                    ->constrained('parties')
                    ->nullOnDelete();

                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('rule_line_id')->nullable();
                $table->string('amount_source', 80)->default('transaction_amount');
                $table->string('entry_type', 20);
                $table->decimal('debit_amount', 18, 2)->default(0);
                $table->decimal('credit_amount', 18, 2)->default(0);
                $table->string('line_narration')->nullable();
                $table->timestamps();

                $table->index(['ledger_id', 'entry_type'], 'jl_ledger_type_idx');
                $table->index(['party_id', 'ledger_id'], 'jl_party_ledger_idx');
                $table->index(['journal_header_id', 'ledger_id'], 'jl_header_ledger_idx');
            });
        }

        $this->backfillFromExistingVouchers();
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_headers');
    }

    private function backfillFromExistingVouchers(): void
    {
        if (! Schema::hasTable('voucher_headers') || ! Schema::hasTable('voucher_details')) {
            return;
        }

        DB::table('voucher_headers')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($vouchers): void {
                foreach ($vouchers as $voucher) {
                    $existingJournalId = DB::table('journal_headers')
                        ->where('voucher_header_id', $voucher->id)
                        ->value('id');

                    $journalId = $existingJournalId ?: DB::table('journal_headers')->insertGetId([
                        'company_id' => $voucher->company_id,
                        'financial_year_id' => $voucher->financial_year_id,
                        'voucher_header_id' => $voucher->id,
                        'journal_no' => $this->journalNoFor($voucher),
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'source_type' => 'Backfill',
                        'journal_date' => $voucher->voucher_date,
                        'transaction_head_id' => $voucher->transaction_head_id,
                        'party_id' => $voucher->party_id,
                        'amount' => $voucher->amount ?? 0,
                        'total_debit' => $voucher->total_debit ?? 0,
                        'total_credit' => $voucher->total_credit ?? 0,
                        'status' => $this->journalStatusFromVoucher((string) $voucher->status),
                        'narration' => $voucher->notes,
                        'created_by' => $voucher->created_by,
                        'submitted_by' => $voucher->submitted_by ?? null,
                        'approved_by' => $voucher->approved_by ?? null,
                        'posted_by' => $voucher->posted_by ?? null,
                        'submitted_at' => $voucher->submitted_at ?? null,
                        'approved_at' => $voucher->approved_at ?? null,
                        'posted_at' => $voucher->posted_at ?? null,
                        'created_at' => $voucher->created_at,
                        'updated_at' => $voucher->updated_at,
                    ]);

                    DB::table('voucher_details')
                        ->where('voucher_header_id', $voucher->id)
                        ->orderBy('line_no')
                        ->orderBy('id')
                        ->get()
                        ->each(function ($detail) use ($journalId): void {
                            $exists = DB::table('journal_lines')
                                ->where('voucher_detail_id', $detail->id)
                                ->exists();

                            if ($exists) {
                                return;
                            }

                            DB::table('journal_lines')->insert([
                                'journal_header_id' => $journalId,
                                'voucher_detail_id' => $detail->id,
                                'line_no' => $detail->line_no,
                                'ledger_id' => $detail->account_id,
                                'party_id' => $detail->party_id,
                                'branch_id' => $detail->branch_id ?? null,
                                'rule_line_id' => $detail->rule_line_id ?? null,
                                'amount_source' => $detail->amount_source ?? 'transaction_amount',
                                'entry_type' => $detail->entry_type,
                                'debit_amount' => $detail->debit ?? 0,
                                'credit_amount' => $detail->credit ?? 0,
                                'line_narration' => $detail->narration,
                                'created_at' => $detail->created_at,
                                'updated_at' => $detail->updated_at,
                            ]);
                        });
                }
            });
    }

    private function journalNoFor(object $voucher): string
    {
        $base = 'JE-' . (string) $voucher->voucher_number;

        if (! DB::table('journal_headers')->where('journal_no', $base)->exists()) {
            return $base;
        }

        return $base . '-' . $voucher->id;
    }

    private function journalStatusFromVoucher(string $voucherStatus): string
    {
        return match ($voucherStatus) {
            'Posted' => 'Posted',
            'Pending Review' => 'Submitted',
            'Cancelled' => 'Cancelled',
            'Reversed' => 'Reversed',
            default => 'Draft',
        };
    }
};
