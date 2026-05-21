<?php

namespace App\AccountingReports\Support;

use Illuminate\Support\Facades\Schema;

class AccountingSchema
{
    private array $columnCache = [];
    private array $tableCache = [];

    public function table(string $key): string
    {
        return (string) config("accounting_reports.tables.$key");
    }

    public function column(string $group, string $key): string
    {
        return (string) config("accounting_reports.columns.$group.$key");
    }

    public function optionalColumn(string $group, string $key): ?string
    {
        $column = config("accounting_reports.columns.$group.$key");
        return $column ? (string) $column : null;
    }

    public function q(string $alias, string $group, string $key): string
    {
        return $alias . '.' . $this->column($group, $key);
    }

    public function hasTable(string $tableKey): bool
    {
        $table = $this->table($tableKey);

        if (! array_key_exists($table, $this->tableCache)) {
            try {
                $this->tableCache[$table] = Schema::hasTable($table);
            } catch (\Throwable) {
                $this->tableCache[$table] = false;
            }
        }

        return $this->tableCache[$table];
    }

    public function hasColumn(string $tableKey, string $columnKey): bool
    {
        $cacheKey = $tableKey . '.' . $columnKey;

        if (! array_key_exists($cacheKey, $this->columnCache)) {
            $table = $this->table($tableKey);
            $column = $this->optionalColumn($tableKey, $columnKey);

            if (! $column || ! $this->hasTable($tableKey)) {
                $this->columnCache[$cacheKey] = false;
            } else {
                try {
                    $this->columnCache[$cacheKey] = Schema::hasColumn($table, $column);
                } catch (\Throwable) {
                    $this->columnCache[$cacheKey] = false;
                }
            }
        }

        return $this->columnCache[$cacheKey];
    }

    public function rawNull(string $alias): string
    {
        return 'NULL AS ' . $alias;
    }

    public function postedStatuses(): array
    {
        return (array) config('accounting_reports.statuses.posted', ['Posted']);
    }

    public function reversedStatuses(): array
    {
        return (array) config('accounting_reports.statuses.reversed', ['Reversed']);
    }

    public function cancelledStatuses(): array
    {
        return (array) config('accounting_reports.statuses.cancelled', ['Cancelled']);
    }

    public function moneyFormat(float|int|string|null $amount): string
    {
        return config('accounting_reports.currency', 'BDT') . ' ' . number_format((float) ($amount ?? 0), 2);
    }
}
