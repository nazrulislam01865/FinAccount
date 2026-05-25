<?php

namespace App\Jobs;

use App\AccountingReports\Services\AccountingReportService;
use App\Models\ReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(private readonly int $reportExportId)
    {
    }

    public function handle(AccountingReportService $reports): void
    {
        $export = ReportExport::query()->findOrFail($this->reportExportId);
        $export->forceFill(['status' => 'Processing', 'started_at' => now()])->save();

        try {
            $filters = $export->filters_json ?: [];
            $filters['company_id'] = $export->company_id;
            $rows = $reports->transactionBaseQuery($filters)
                ->orderByDesc('voucher_date')
                ->orderByDesc('voucher_id')
                ->cursor();

            $path = 'report-exports/' . $export->company_id . '/' . $export->report_name . '-' . now()->format('Ymd-His') . '-' . $export->id . '.csv';
            $stream = fopen('php://temp', 'w+');
            fputcsv($stream, ['Date', 'Voucher No', 'Transaction Head', 'Party', 'Settlement', 'Nature', 'Amount', 'Status', 'Reference']);

            foreach ($rows as $row) {
                fputcsv($stream, [
                    $row->voucher_date,
                    $row->voucher_no,
                    $row->purpose_name,
                    $row->party_name,
                    $row->settlement,
                    $row->nature,
                    number_format((float) $row->amount, 2, '.', ''),
                    $row->status,
                    $row->reference_no,
                ]);
            }

            rewind($stream);
            Storage::disk('local')->put($path, stream_get_contents($stream));
            fclose($stream);

            $export->forceFill([
                'status' => 'Completed',
                'file_path' => $path,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $export->forceFill([
                'status' => 'Failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        }
    }
}
