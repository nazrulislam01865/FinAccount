<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_rule_lines')) {
            return;
        }

        Schema::create('accounting_rule_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accounting_rule_id')->constrained('accounting_rules')->cascadeOnDelete();
            $table->string('line_side', 10);
            $table->string('account_source', 40);
            $table->string('amount_basis', 20)->default('total');
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['accounting_rule_id', 'sort_order'], 'rule_lines_rule_sort_index');
        });

        $now = now();

        DB::table('accounting_rules')
            ->orderBy('id')
            ->select(['id', 'debit_source', 'credit_source'])
            ->chunkById(200, function ($rules) use ($now): void {
                $rows = [];

                foreach ($rules as $rule) {
                    $rows[] = [
                        'accounting_rule_id' => $rule->id,
                        'line_side' => 'debit',
                        'account_source' => $rule->debit_source,
                        'amount_basis' => 'total',
                        'sort_order' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $rows[] = [
                        'accounting_rule_id' => $rule->id,
                        'line_side' => 'credit',
                        'account_source' => $rule->credit_source,
                        'amount_basis' => 'total',
                        'sort_order' => 2,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('accounting_rule_lines')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_rule_lines');
    }
};
