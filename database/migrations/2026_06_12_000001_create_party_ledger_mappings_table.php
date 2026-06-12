<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('party_types', 'default_ledger_nature')) {
            Schema::table('party_types', function (Blueprint $table) {
                $table->string('default_ledger_nature', 30)->nullable()->after('default_ledger_account_id');
            });

            $this->backfillPartyTypeNatures();
        }

        Schema::create('party_ledger_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();
            $table->foreignId('party_id')
                ->constrained('parties')
                ->cascadeOnDelete();
            $table->string('mapping_purpose', 50);
            $table->foreignId('chart_of_account_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['party_id', 'mapping_purpose'], 'party_ledger_mapping_party_purpose_uq');
            $table->index(['company_id', 'mapping_purpose', 'status'], 'party_ledger_mapping_lookup_idx');
            $table->index('chart_of_account_id', 'party_ledger_mapping_coa_idx');
        });

        $this->backfillExistingPartyLedgers();
    }

    public function down(): void
    {
        Schema::dropIfExists('party_ledger_mappings');

        if (Schema::hasColumn('party_types', 'default_ledger_nature')) {
            Schema::table('party_types', function (Blueprint $table) {
                $table->dropColumn('default_ledger_nature');
            });
        }
    }


    private function backfillPartyTypeNatures(): void
    {
        DB::table('party_types')->orderBy('id')->get(['id', 'name', 'code'])->each(function ($type): void {
            $value = strtoupper(trim((string) $type->code . ' ' . (string) $type->name));
            $nature = match (true) {
                str_contains($value, 'CUSTOMER'), str_contains($value, 'CUS'), str_contains($value, 'TENANT') => 'Receivable',
                str_contains($value, 'SUPPLIER'), str_contains($value, 'SUP'), str_contains($value, 'VENDOR'),
                str_contains($value, 'LANDLORD'), str_contains($value, 'EMPLOYEE'), str_contains($value, 'DRIVER'),
                str_contains($value, 'LENDER') => 'Payable',
                str_contains($value, 'OWNER'), str_contains($value, 'PARTNER'), str_contains($value, 'SHAREHOLDER') => 'Capital',
                default => 'No Effect',
            };

            DB::table('party_types')->where('id', $type->id)->update([
                'default_ledger_nature' => $nature,
            ]);
        });
    }

    private function backfillExistingPartyLedgers(): void
    {
        if (! Schema::hasTable('parties')) {
            return;
        }

        DB::table('parties')
            ->whereNotNull('linked_ledger_account_id')
            ->orderBy('id')
            ->chunkById(200, function ($parties): void {
                $now = now();

                foreach ($parties as $party) {
                    DB::table('party_ledger_mappings')->updateOrInsert(
                        [
                            'party_id' => $party->id,
                            'mapping_purpose' => $this->purposeFromNature($party->default_ledger_nature ?? null),
                        ],
                        [
                            'company_id' => $party->company_id,
                            'chart_of_account_id' => $party->linked_ledger_account_id,
                            'status' => $party->status === 'Inactive' ? 'Inactive' : 'Active',
                            'created_by' => $party->created_by,
                            'updated_by' => $party->updated_by,
                            'created_at' => $party->created_at ?? $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }, 'id');
    }

    private function purposeFromNature(?string $nature): string
    {
        return match ($nature) {
            'Receivable' => 'receivable',
            'Payable' => 'payable',
            'Advance Paid' => 'advance_paid',
            'Advance Received' => 'advance_received',
            'Capital' => 'capital',
            default => 'general',
        };
    }
};
