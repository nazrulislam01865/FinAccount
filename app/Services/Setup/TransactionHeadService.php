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

            return $head->fresh(['defaultPartyType', 'settlementTypes']);
        });
    }

    public function update(TransactionHead $head, array $data, ?int $userId = null): TransactionHead
    {
        return DB::transaction(function () use ($head, $data, $userId) {
            $head->update(
                $this->payload($data, $userId, false)
            );

            $head->settlementTypes()->sync($data['settlement_type_ids']);

            return $head->fresh(['defaultPartyType', 'settlementTypes']);
        });
    }

    private function payload(array $data, ?int $userId, bool $creating): array
    {
        $payload = Arr::only($data, [
            'head_code',
            'name',
            'nature',
            'default_party_type_id',
            'description',
            'status',
        ]);

        $company = Company::query()->first();

        $payload['company_id'] = $company?->id;

        if ($creating) {
            $payload['head_code'] = $payload['head_code'] ?? $this->nextHeadCode();
        } elseif (empty($payload['head_code'])) {
            unset($payload['head_code']);
        }

        $payload['requires_party'] = (bool) ($data['requires_party'] ?? false);
        $payload['requires_reference'] = (bool) ($data['requires_reference'] ?? false);

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
}
