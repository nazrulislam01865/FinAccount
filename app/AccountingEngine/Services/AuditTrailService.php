<?php

namespace App\AccountingEngine\Services;

use App\Models\AuditLog;
use App\Models\VoucherHeader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AuditTrailService
{
    /**
     * Keys that must never be exposed in the audit report payload.
     *
     * @var array<int, string>
     */
    private array $sensitiveKeyFragments = [
        'password',
        'remember_token',
        'token',
        'secret',
        'private_key',
        'api_key',
        'authorization',
        'two_factor',
    ];

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     * @param array<string, mixed>|null $metadata
     */
    public function record(Model|string $subject, ?int $subjectId, string $event, ?array $oldValues = null, ?array $newValues = null, ?int $userId = null, ?array $metadata = null): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $subjectClass = is_string($subject) ? $subject : $subject::class;
        $event = $this->normalizeAction($event);

        $payload = [
            'auditable_type' => $subjectClass,
            'auditable_id' => $subjectId ?: (is_string($subject) ? 0 : (int) $subject->getKey()),
            'event' => $event,
            'old_values' => $this->sanitizeValues($oldValues),
            'new_values' => $this->sanitizeValues($newValues),
            'user_id' => $userId,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('audit_logs', 'company_id')) {
            $payload['company_id'] = $this->companyId($subject, $newValues, $oldValues, $metadata);
        }

        if (Schema::hasColumn('audit_logs', 'module')) {
            $payload['module'] = $metadata['module'] ?? $this->moduleName($subjectClass);
        }

        if (Schema::hasColumn('audit_logs', 'action')) {
            $payload['action'] = $event;
        }

        $this->addRequestContext($payload);

        if (Schema::hasColumn('audit_logs', 'metadata')) {
            $payload['metadata'] = $this->sanitizeValues($metadata);
        }

        $this->createAuditLog($payload);
    }

    /**
     * Record a non-Eloquent business event, such as role-matrix changes, voucher reversal, or approval workflow actions.
     *
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     * @param array<string, mixed>|null $metadata
     */
    public function recordAction(string $module, string $action, ?array $oldValues = null, ?array $newValues = null, ?int $userId = null, ?array $metadata = null, ?int $companyId = null, int|string|null $auditableId = 0): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $action = $this->normalizeAction($action);

        $payload = [
            'auditable_type' => $module,
            'auditable_id' => $auditableId ?: 0,
            'event' => $action,
            'old_values' => $this->sanitizeValues($oldValues),
            'new_values' => $this->sanitizeValues($newValues),
            'user_id' => $userId,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('audit_logs', 'company_id')) {
            $payload['company_id'] = $companyId;
        }

        if (Schema::hasColumn('audit_logs', 'module')) {
            $payload['module'] = $module;
        }

        if (Schema::hasColumn('audit_logs', 'action')) {
            $payload['action'] = $action;
        }

        $this->addRequestContext($payload);

        if (Schema::hasColumn('audit_logs', 'metadata')) {
            $payload['metadata'] = $this->sanitizeValues($metadata);
        }

        $this->createAuditLog($payload);
    }

    public function recordPostedVoucher(VoucherHeader $voucher, ?int $userId = null): void
    {
        $voucher->loadMissing(['details.account', 'details.party', 'transactionHead', 'settlementType', 'party', 'cashBankAccount']);

        $this->record(
            $voucher,
            (int) $voucher->id,
            $voucher->voucher_type === 'Opening Voucher' ? 'opening_balance_posted' : 'voucher_posted',
            null,
            $voucher->toArray(),
            $userId,
            [
                'module' => 'VoucherPosting',
                'voucher_number' => $voucher->voucher_number,
                'voucher_type' => $voucher->voucher_type,
                'status' => $voucher->status,
                'amount' => (float) $voucher->amount,
                'total_debit' => (float) $voucher->total_debit,
                'total_credit' => (float) $voucher->total_credit,
                'balanced' => round((float) $voucher->total_debit, 2) === round((float) $voucher->total_credit, 2),
                'transaction_head' => $voucher->transactionHead?->name,
                'settlement_type' => $voucher->settlementType?->name,
                'party' => $voucher->party?->party_name,
                'accounting_principle' => 'Posted vouchers are not edited directly; correction must be done by cancellation/reversal so the accounting trail remains complete.',
            ]
        );
    }

    public function recordVoucherReversal(VoucherHeader $sourceVoucher, VoucherHeader $reversalVoucher, ?int $userId = null, ?array $totals = null): void
    {
        $sourceVoucher->loadMissing(['details.account', 'details.party']);
        $reversalVoucher->loadMissing(['details.account', 'details.party']);

        $this->recordAction(
            'VoucherReversal',
            'voucher_reversed',
            $sourceVoucher->toArray(),
            $reversalVoucher->toArray(),
            $userId,
            [
                'source_voucher_id' => $sourceVoucher->id,
                'source_voucher_number' => $sourceVoucher->voucher_number,
                'reversal_voucher_id' => $reversalVoucher->id,
                'reversal_voucher_number' => $reversalVoucher->voucher_number,
                'debit_total' => $totals['debit_total'] ?? (float) $reversalVoucher->total_debit,
                'credit_total' => $totals['credit_total'] ?? (float) $reversalVoucher->total_credit,
                'balanced' => round((float) ($totals['debit_total'] ?? $reversalVoucher->total_debit), 2) === round((float) ($totals['credit_total'] ?? $reversalVoucher->total_credit), 2),
                'accounting_principle' => 'Reversal creates equal opposite journal lines and keeps the original voucher visible; voucher numbers are not reused.',
            ],
            (int) ($sourceVoucher->company_id ?: $reversalVoucher->company_id),
            (int) $sourceVoucher->id
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function addRequestContext(array &$payload): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        try {
            $request = request();
        } catch (Throwable) {
            return;
        }

        if (Schema::hasColumn('audit_logs', 'ip_address')) {
            $payload['ip_address'] = $request->ip();
        }

        if (Schema::hasColumn('audit_logs', 'user_agent')) {
            $payload['user_agent'] = Str::limit((string) $request->userAgent(), 1000, '');
        }

        if (Schema::hasColumn('audit_logs', 'route_name')) {
            $payload['route_name'] = $request->route()?->getName();
        }

        if (Schema::hasColumn('audit_logs', 'request_method')) {
            $payload['request_method'] = $request->method();
        }

        if (Schema::hasColumn('audit_logs', 'request_url')) {
            $payload['request_url'] = Str::limit((string) $request->fullUrl(), 2000, '');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createAuditLog(array $payload): void
    {
        try {
            AuditLog::query()->create($payload);
        } catch (Throwable) {
            // Audit writes must never block production accounting, setup, approval, or reversal work.
        }
    }

    /**
     * @param array<string, mixed>|null $values
     * @return array<string, mixed>|null
     */
    private function sanitizeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sanitized = [];
        foreach ($values as $key => $value) {
            $keyString = is_string($key) ? $key : (string) $key;

            if ($this->isSensitiveKey($keyString)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if ($value instanceof Model) {
                $value = $value->toArray();
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeValues($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::lower($key);

        foreach ($this->sensitiveKeyFragments as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAction(string $action): string
    {
        return Str::of($action)
            ->replace([' ', '-', '.'], '_')
            ->lower()
            ->toString();
    }

    private function moduleName(string $subjectClass): string
    {
        return class_basename($subjectClass) ?: $subjectClass;
    }

    /**
     * @param array<string, mixed>|null $newValues
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $metadata
     */
    private function companyId(Model|string $subject, ?array $newValues, ?array $oldValues, ?array $metadata): ?int
    {
        if ($subject instanceof Model && isset($subject->company_id)) {
            return (int) $subject->company_id ?: null;
        }

        $value = $metadata['company_id'] ?? $newValues['company_id'] ?? $oldValues['company_id'] ?? null;

        return $value ? (int) $value : null;
    }
}
