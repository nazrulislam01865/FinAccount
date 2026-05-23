<?php

namespace App\AccountingEngine\Services;

use App\Models\AuditLog;
use App\Models\VoucherHeader;
use Illuminate\Database\Eloquent\Model;

class AuditTrailService
{
    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function record(Model|string $subject, ?int $subjectId, string $event, ?array $oldValues = null, ?array $newValues = null, ?int $userId = null): void
    {
        AuditLog::query()->create([
            'auditable_type' => is_string($subject) ? $subject : $subject::class,
            'auditable_id' => $subjectId ?: (is_string($subject) ? 0 : (int) $subject->getKey()),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    public function recordPostedVoucher(VoucherHeader $voucher, ?int $userId = null): void
    {
        $this->record(
            $voucher,
            (int) $voucher->id,
            $voucher->voucher_type === 'Opening Voucher' ? 'opening_balance_posted' : 'voucher_posted',
            null,
            $voucher->loadMissing(['details.account', 'details.party'])->toArray(),
            $userId
        );
    }
}
