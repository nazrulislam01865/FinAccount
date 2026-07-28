<?php

use App\Support\BackdatedTransactionWindow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('financial_years')) {
            return;
        }

        $now = now();
        $currentYear = (int) $now->format('Y');

        DB::table('companies')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $company) use ($now, $currentYear): void {
                foreach (array_reverse(BackdatedTransactionWindow::years()) as $year) {
                    $yearStart = $year.'-01-01';
                    $yearEnd = $year.'-12-31';

                    $overlappingYearExists = DB::table('financial_years')
                        ->where('company_id', $company->id)
                        ->whereDate('start_date', '<=', $yearEnd)
                        ->whereDate('end_date', '>=', $yearStart)
                        ->exists();

                    if ($overlappingYearExists) {
                        continue;
                    }

                    $baseName = 'FY '.$year;
                    $name = DB::table('financial_years')
                        ->where('company_id', $company->id)
                        ->where('name', $baseName)
                        ->exists()
                            ? $baseName.' Backdated'
                            : $baseName;

                    DB::table('financial_years')->insert([
                        'company_id' => $company->id,
                        'name' => $name,
                        'start_date' => $yearStart,
                        'end_date' => $yearEnd,
                        'lock_date' => null,
                        'is_active' => true,
                        'is_current' => $year === $currentYear
                            && ! DB::table('financial_years')
                                ->where('company_id', $company->id)
                                ->where('is_current', true)
                                ->exists(),
                        'status' => 'open',
                        'created_by' => null,
                        'updated_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally left unchanged so a rollback cannot remove user accounting periods.
    }
};
