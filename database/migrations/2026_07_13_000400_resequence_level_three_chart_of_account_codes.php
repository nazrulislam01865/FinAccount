<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $accounts = DB::table('chart_of_accounts as child')
                ->join('chart_of_accounts as parent', 'parent.id', '=', 'child.parent_id')
                ->where('child.level', 3)
                ->where('parent.level', 2)
                ->select([
                    'child.id',
                    'child.company_id',
                    'child.parent_id',
                    'child.code',
                    'parent.code as parent_code',
                ])
                ->orderBy('child.company_id')
                ->orderBy('child.parent_id')
                ->orderBy('child.id')
                ->get()
                ->filter(fn ($account): bool => preg_match('/^\d+$/', (string) $account->parent_code) === 1)
                ->values();

            if ($accounts->isEmpty()) {
                return;
            }

            $groups = $accounts->groupBy(fn ($account): string => $account->company_id.'-'.$account->parent_id);
            $updates = [];
            $targetOwners = [];

            foreach ($groups as $group) {
                $orderedGroup = $group
                    ->sortBy(fn ($account): string => sprintf(
                        '%d-%020d-%s-%020d',
                        preg_match('/^\d+$/', (string) $account->code) === 1 ? 0 : 1,
                        preg_match('/^\d+$/', (string) $account->code) === 1 ? (int) $account->code : 0,
                        (string) $account->code,
                        (int) $account->id,
                    ))
                    ->values();

                $parentCode = (int) $orderedGroup->first()->parent_code;

                if ($orderedGroup->count() > 99) {
                    throw new RuntimeException('Cannot resequence Level 3 COA codes because parent '.$orderedGroup->first()->parent_code.' has more than 99 Level 3 child accounts.');
                }

                foreach ($orderedGroup as $index => $account) {
                    $newCode = (string) ($parentCode + $index + 1);
                    $key = $account->company_id.'|'.$newCode;

                    if (isset($targetOwners[$key]) && (int) $targetOwners[$key] !== (int) $account->id) {
                        throw new RuntimeException('Cannot resequence Level 3 COA codes because duplicate target code '.$newCode.' was calculated.');
                    }

                    $targetOwners[$key] = (int) $account->id;
                    $updates[(int) $account->id] = [
                        'company_id' => (int) $account->company_id,
                        'new_code' => $newCode,
                    ];
                }
            }

            $this->guardAgainstExternalCollisions($updates, 'resequence');

            $this->guardAgainstTemporaryCollisions($updates, 'up');

            foreach ($updates as $id => $update) {
                DB::table('chart_of_accounts')
                    ->where('id', $id)
                    ->update(['code' => $this->temporaryCode($id, $update['company_id'], 'UP')]);
            }

            foreach ($updates as $id => $update) {
                DB::table('chart_of_accounts')
                    ->where('id', $id)
                    ->update(['code' => $update['new_code']]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $accounts = DB::table('chart_of_accounts as child')
                ->join('chart_of_accounts as parent', 'parent.id', '=', 'child.parent_id')
                ->where('child.level', 3)
                ->where('parent.level', 2)
                ->select([
                    'child.id',
                    'child.company_id',
                    'child.parent_id',
                    'child.code',
                    'parent.code as parent_code',
                ])
                ->orderBy('child.company_id')
                ->orderBy('child.parent_id')
                ->orderBy('child.id')
                ->get()
                ->filter(fn ($account): bool => preg_match('/^\d+$/', (string) $account->parent_code) === 1)
                ->values();

            if ($accounts->isEmpty()) {
                return;
            }

            $groups = $accounts->groupBy(fn ($account): string => $account->company_id.'-'.$account->parent_id);
            $updates = [];

            foreach ($groups as $group) {
                $orderedGroup = $group
                    ->sortBy(fn ($account): string => sprintf(
                        '%d-%020d-%s-%020d',
                        preg_match('/^\d+$/', (string) $account->code) === 1 ? 0 : 1,
                        preg_match('/^\d+$/', (string) $account->code) === 1 ? (int) $account->code : 0,
                        (string) $account->code,
                        (int) $account->id,
                    ))
                    ->values();

                $parentCode = (int) $orderedGroup->first()->parent_code;

                if ($orderedGroup->count() > 9) {
                    throw new RuntimeException('Cannot roll back Level 3 COA codes to the old +10 format because parent '.$orderedGroup->first()->parent_code.' has more than 9 Level 3 child accounts.');
                }

                foreach ($orderedGroup as $index => $account) {
                    $updates[(int) $account->id] = [
                        'company_id' => (int) $account->company_id,
                        'new_code' => (string) ($parentCode + (($index + 1) * 10)),
                    ];
                }
            }

            $this->guardAgainstExternalCollisions($updates, 'roll back');

            $this->guardAgainstTemporaryCollisions($updates, 'down');

            foreach ($updates as $id => $update) {
                DB::table('chart_of_accounts')
                    ->where('id', $id)
                    ->update(['code' => $this->temporaryCode($id, $update['company_id'], 'DOWN')]);
            }

            foreach ($updates as $id => $update) {
                DB::table('chart_of_accounts')
                    ->where('id', $id)
                    ->update(['code' => $update['new_code']]);
            }
        });
    }

    /** @param array<int, array{company_id: int, new_code: string}> $updates */
    private function guardAgainstTemporaryCollisions(array $updates, string $direction): void
    {
        $temporaryCodesByCompany = [];

        foreach ($updates as $id => $update) {
            $temporaryCodesByCompany[$update['company_id']][] = $this->temporaryCode($id, $update['company_id'], strtoupper($direction));
        }

        foreach ($temporaryCodesByCompany as $companyId => $codes) {
            $collisions = DB::table('chart_of_accounts')
                ->where('company_id', $companyId)
                ->whereIn('code', array_values(array_unique($codes)))
                ->whereNotIn('id', array_keys($updates))
                ->pluck('code')
                ->all();

            if ($collisions !== []) {
                throw new RuntimeException('Cannot resequence Level 3 COA codes because temporary codes are already used: '.implode(', ', $collisions));
            }
        }
    }

    private function temporaryCode(int $id, int $companyId, string $direction): string
    {
        return '__COA_'.$direction.'_'.$companyId.'_'.$id.'__';
    }

    /** @param array<int, array{company_id: int, new_code: string}> $updates */
    private function guardAgainstExternalCollisions(array $updates, string $action): void
    {
        $targetsByCompany = [];

        foreach ($updates as $update) {
            $targetsByCompany[$update['company_id']][] = $update['new_code'];
        }

        foreach ($targetsByCompany as $companyId => $codes) {
            $collisions = DB::table('chart_of_accounts')
                ->where('company_id', $companyId)
                ->whereIn('code', array_values(array_unique($codes)))
                ->whereNotIn('id', array_keys($updates))
                ->pluck('code')
                ->all();

            if ($collisions !== []) {
                throw new RuntimeException('Cannot '.$action.' Level 3 COA codes because these target codes are already used: '.implode(', ', $collisions));
            }
        }
    }
};
