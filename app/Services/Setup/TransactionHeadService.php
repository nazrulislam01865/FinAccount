<?php

namespace App\Services\Setup;

use App\Models\Company;
use App\Models\TransactionHead;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TransactionHeadService
{
    public function create(array $data, ?int $userId = null): TransactionHead
    {
        return DB::transaction(function () use ($data, $userId) {
            $head = TransactionHead::query()->create(
                $this->payload($data, $userId, true)
            );

            $head->settlementTypes()->sync($data['settlement_type_ids']);

            return $head->fresh(['defaultPartyType', 'defaultPrimaryLedger', 'settlementTypes']);
        });
    }

    public function update(TransactionHead $head, array $data, ?int $userId = null): TransactionHead
    {
        return DB::transaction(function () use ($head, $data, $userId) {
            $head->update(
                $this->payload($data, $userId, false, $head)
            );

            $head->settlementTypes()->sync($data['settlement_type_ids']);

            return $head->fresh(['defaultPartyType', 'defaultPrimaryLedger', 'settlementTypes']);
        });
    }

    private function payload(array $data, ?int $userId, bool $creating, ?TransactionHead $head = null): array
    {
        $payload = Arr::only($data, [
            'head_code',
            'name',
            'nature',
            'category',
            'default_party_type_id',
            'default_primary_ledger_id',
            'default_movement',
            'payment_method_required',
            'party_required_mode',
            'transaction_screen',
            'is_system_default',
            'is_user_selectable',
            'sort_order',
            'linked_accounting_rule_code',
            'description',
            'help_text',
            'developer_note',
            'status',
        ]);

        $company = Company::query()->first();

        $payload['company_id'] = $head?->company_id ?: $company?->id;

        if ($creating) {
            $payload['head_code'] = $payload['head_code'] ?? $this->nextHeadCode();
        } elseif (empty($payload['head_code'])) {
            unset($payload['head_code']);
        }

        $payload['category'] = $payload['category'] ?: $payload['nature'];
        $payload['payment_method_required'] = (bool) ($data['payment_method_required'] ?? false);
        $payload['party_required_mode'] = $data['party_required_mode'] ?? ((bool) ($data['requires_party'] ?? false) ? 'Required' : 'No');
        $payload['requires_party'] = $payload['party_required_mode'] !== 'No';
        $payload['requires_reference'] = (bool) ($data['requires_reference'] ?? false);
        $payload['is_system_default'] = (bool) ($data['is_system_default'] ?? false);
        $payload['is_user_selectable'] = (bool) ($data['is_user_selectable'] ?? true);
        $payload['sort_order'] = $data['sort_order'] ?? $head?->sort_order ?? $this->nextSortOrder();

        if ($creating) {
            $payload['created_by'] = $userId;
        }

        $payload['updated_by'] = $userId;

        return $payload;
    }

    private function nextHeadCode(): string
    {
        $lastCode = TransactionHead::query()
            ->withTrashed()
            ->where('head_code', 'like', 'TH-%')
            ->orderByDesc('id')
            ->value('head_code');

        $number = $lastCode ? (int) str_replace('TH-', '', $lastCode) : 0;

        return 'TH-' . str_pad((string) ($number + 1), 3, '0', STR_PAD_LEFT);
    }

    private function nextSortOrder(): int
    {
        return ((int) TransactionHead::query()->max('sort_order')) + 10;
    }
}
