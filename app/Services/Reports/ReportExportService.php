<?php

namespace App\Services\Reports;

use App\Jobs\GenerateReportExportJob;
use App\Models\Company;
use App\Models\ReportExport;
use App\Models\User;

class ReportExportService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function request(string $reportName, array $filters, ?User $user): ReportExport
    {
        $companyId = (int) ($user?->company_id ?? 0);
        $companyId = $companyId > 0 ? $companyId : (int) Company::query()->orderBy('id')->value('id');

        $export = ReportExport::query()->create([
            'company_id' => $companyId ?: null,
            'user_id' => $user?->id,
            'report_name' => $reportName,
            'filters_json' => $filters,
            'status' => 'Pending',
            'requested_at' => now(),
        ]);

        GenerateReportExportJob::dispatch($export->id);

        return $export;
    }
}
