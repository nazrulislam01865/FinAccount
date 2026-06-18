<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\TimeZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeZoneService
{
    /** @return array{timeZones:Collection<int,TimeZone>,usage:array<int,int>} */
    public function pageData(int $companyId): array
    {
        $timeZones = TimeZone::query()->forCompany($companyId)->orderBy('sort_order')->orderBy('name')->get();
        $usage = $timeZones->mapWithKeys(fn (TimeZone $item): array => [
            $item->id => Company::query()->where('time_zone_id', $item->id)->count(),
        ])->all();

        return compact('timeZones', 'usage');
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, int $companyId): TimeZone
    {
        $this->assertDefaultIsActive($data);

        return DB::transaction(function () use ($data, $companyId): TimeZone {
            if ($data['is_default']) {
                TimeZone::query()->forCompany($companyId)->update(['is_default' => false]);
            }
            return TimeZone::query()->create(['company_id' => $companyId, ...$this->normalized($data)]);
        }, attempts: 5);
    }

    /** @param array<string,mixed> $data */
    public function update(TimeZone $timeZone, array $data): TimeZone
    {
        $this->assertDefaultIsActive($data);

        if (! $data['is_active'] && $timeZone->companies()->exists()) {
            throw ValidationException::withMessages([
                'is_active' => 'Select a replacement Time Zone in Company Setup before making this value inactive.',
            ]);
        }

        DB::transaction(function () use ($timeZone, $data): void {
            $company = Company::query()->lockForUpdate()->findOrFail($timeZone->company_id);
            $timeZone = TimeZone::query()->lockForUpdate()->findOrFail($timeZone->id);
            $isSelected = (int) $company->time_zone_id === (int) $timeZone->id;

            if ($data['is_default']) {
                TimeZone::query()->forCompany($timeZone->company_id)->whereKeyNot($timeZone->id)->update(['is_default' => false]);
            }

            $timeZone->update($this->normalized($data));

            if ($isSelected) {
                $company->update(['timezone' => $timeZone->php_timezone]);
            }
        }, attempts: 5);

        return $timeZone->refresh();
    }


    /** @param array<string,mixed> $data */
    private function assertDefaultIsActive(array $data): void
    {
        if ($data['is_default'] && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'A default Time Zone must remain active.',
            ]);
        }
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function normalized(array $data): array
    {
        return [
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
            'utc_offset' => trim((string) $data['utc_offset']),
            'php_timezone' => trim((string) $data['php_timezone']),
            'is_default' => (bool) $data['is_default'],
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) $data['sort_order'],
        ];
    }
}
