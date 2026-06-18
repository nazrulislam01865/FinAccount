<?php

namespace App\Services\Company;

use App\Models\BusinessType;
use App\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessTypeService
{
    /** @return array{businessTypes:Collection<int,BusinessType>,usage:array<int,int>} */
    public function pageData(int $companyId): array
    {
        $businessTypes = BusinessType::query()->forCompany($companyId)->orderBy('sort_order')->orderBy('name')->get();
        $usage = $businessTypes->mapWithKeys(fn (BusinessType $item): array => [
            $item->id => Company::query()->where('business_type_id', $item->id)->count(),
        ])->all();

        return compact('businessTypes', 'usage');
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, int $companyId): BusinessType
    {
        $this->assertDefaultIsActive($data);

        return DB::transaction(function () use ($data, $companyId): BusinessType {
            if ($data['is_default']) {
                BusinessType::query()->forCompany($companyId)->update(['is_default' => false]);
            }

            return BusinessType::query()->create(['company_id' => $companyId, ...$this->normalized($data)]);
        }, attempts: 5);
    }

    /** @param array<string,mixed> $data */
    public function update(BusinessType $businessType, array $data): BusinessType
    {
        $this->assertDefaultIsActive($data);

        if (! $data['is_active'] && $businessType->companies()->exists()) {
            throw ValidationException::withMessages([
                'is_active' => 'Select a replacement Business Type in Company Setup before making this value inactive.',
            ]);
        }

        DB::transaction(function () use ($businessType, $data): void {
            if ($data['is_default']) {
                BusinessType::query()->forCompany($businessType->company_id)->whereKeyNot($businessType->id)->update(['is_default' => false]);
            }
            $businessType->update($this->normalized($data));
        }, attempts: 5);

        return $businessType->refresh();
    }


    /** @param array<string,mixed> $data */
    private function assertDefaultIsActive(array $data): void
    {
        if ($data['is_default'] && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'A default Business Type must remain active.',
            ]);
        }
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function normalized(array $data): array
    {
        return [
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'is_default' => (bool) $data['is_default'],
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) $data['sort_order'],
        ];
    }
}
