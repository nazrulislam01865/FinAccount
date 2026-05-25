<?php

namespace App\AccountingEngine\Services;

use App\Models\AuditLog;
use App\Models\VoucherHeader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditTrailService
{
    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function record(Model|string $subject, ?int $subjectId, string $event, ?array $oldValues = null, ?array $newValues = null, ?int $userId = null): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $subjectClass = is_string($subject) ? $subject : $subject::class;
        $payload = [
            'auditable_type' => $subjectClass,
            'auditable_id' => $subjectId ?: (is_string($subject) ? 0 : (int) $subject->getKey()),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => $userId,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('audit_logs', 'company_id')) {
            $payload['company_id'] = $this->companyId($subject, $newValues, $oldValues);
        }

        if (Schema::hasColumn('audit_logs', 'module')) {
            $payload['module'] = class_basename($subjectClass);
        }

        if (Schema::hasColumn('audit_logs', 'action')) {
            $payload['action'] = $event;
        }

        if (Schema::hasColumn('audit_logs', 'ip_address')) {
            $payload['ip_address'] = request()?->ip();
        }

        if (Schema::hasColumn('audit_logs', 'user_agent')) {
            $payload['user_agent'] = substr((string) request()?->userAgent(), 0, 1000);
        }

        try {
            AuditLog::query()->create($payload);
        } catch (Throwable) {
            // Never break accounting posting or setup saves because the audit trail is unavailable.
        }
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

    /**
     * @param array<string, mixed>|null $newValues
     * @param array<string, mixed>|null $oldValues
     */
    private function companyId(Model|string $subject, ?array $newValues, ?array $oldValues): ?int
    {
        if ($subject instanceof Model && isset($subject->company_id)) {
            return (int) $subject->company_id ?: null;
        }

        $value = $newValues['company_id'] ?? $oldValues['company_id'] ?? null;

        return $value ? (int) $value : null;
    }
}
