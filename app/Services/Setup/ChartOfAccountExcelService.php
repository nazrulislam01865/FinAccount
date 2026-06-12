<?php

namespace App\Services\Setup;

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\LedgerType;
use App\Models\PartyType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class ChartOfAccountExcelService
{
    public const HEADERS = [
        'account_code',
        'account_name',
        'coa_level',
        'parent_code',
        'account_class',
        'normal_balance',
        'ledger_type',
        'party_type',
        'user_selectable',
        'system_ledger',
        'status',
        'description',
        'example_usage',
    ];

    public function exportPath(): string
    {
        $rows = ChartOfAccount::query()
            ->with(['accountType', 'parent', 'partyType'])
            ->orderBy('account_code')
            ->get()
            ->map(function (ChartOfAccount $account): array {
                return [
                    $account->account_code,
                    $account->account_name,
                    (string) ($account->coa_level ?: ($account->account_level === 'Ledger' ? 4 : 1)),
                    $account->parent?->account_code ?? '',
                    $account->accountType?->name ?? $account->account_nature ?? '',
                    $account->normal_balance ?? '',
                    $account->ledger_type ?? '',
                    $account->partyType?->name ?? '',
                    $account->is_user_selectable ? 'Yes' : 'No',
                    $account->is_system_ledger ? 'Yes' : 'No',
                    $account->status ?? 'Active',
                    $account->description ?? '',
                    $account->example_usage ?? '',
                ];
            })
            ->prepend(self::HEADERS)
            ->values()
            ->all();

        if (! class_exists(ZipArchive::class)) {
            return $this->exportCsvPath($rows);
        }

        $path = storage_path('app/exports/chart-of-accounts-' . now()->format('Ymd-His') . '.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $this->writeXlsx($path, $rows);

        return $path;
    }

    public function import(UploadedFile $file, ChartOfAccountService $accountService, ?int $userId = null): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $rawRows = in_array($extension, ['xlsx', 'xlsm'], true)
            ? $this->readXlsx($file->getRealPath())
            : $this->readCsv($file->getRealPath());

        [$headers, $rows, $firstDataLineNumber] = $this->prepareImportRows($rawRows);

        if (count($rows) < 1) {
            throw new RuntimeException('The import file does not contain any CoA rows after the header row.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $conflicts = [];
        $violations = [];
        $hierarchy = $this->existingHierarchy();

        $pendingRows = collect($rows)
            ->map(function (array $row, int $index) use ($headers, $firstDataLineNumber): array {
                return [
                    'line_number' => $firstDataLineNumber + $index,
                    'data' => $this->mapRow($headers, $row),
                ];
            })
            ->reject(fn (array $item): bool => $this->blankRow($item['data']))
            ->values()
            ->all();

        DB::transaction(function () use ($pendingRows, $accountService, $userId, &$created, &$skipped, &$errors, &$conflicts, &$violations, &$hierarchy) {
            $remaining = $pendingRows;
            $maximumPasses = max(1, count($remaining) + 1);
            $pass = 0;

            while ($remaining !== [] && $pass < $maximumPasses) {
                $pass++;
                $createdThisPass = 0;
                $deferred = [];

                foreach ($remaining as $item) {
                    $lineNumber = (int) $item['line_number'];
                    $data = $this->normalizeImportedCoaData($item['data'], $hierarchy);

                    try {
                        $payload = $this->toAccountPayload($data, $lineNumber);
                    } catch (\Throwable $exception) {
                        if ($this->shouldDeferMissingParent($exception)) {
                            $deferred[] = $item;
                            continue;
                        }

                        $this->recordImportViolation(
                            $lineNumber,
                            $data,
                            [$exception->getMessage()],
                            $skipped,
                            $violations,
                            $errors
                        );
                        continue;
                    }

                    $duplicateAccounts = $this->findDuplicateAccounts($payload);
                    if ($duplicateAccounts !== []) {
                        $skipped++;
                        $reasons = collect($duplicateAccounts)
                            ->map(fn (array $account) => $account['reason'])
                            ->values()
                            ->all();
                        $conflicts[] = $this->makeImportIssue('conflict', $lineNumber, $payload, $reasons, $duplicateAccounts);
                        $errors[] = 'Row ' . $lineNumber . ': Existing account conflict. The existing CoA was not changed; resolve this row manually from the import review popup.';
                        continue;
                    }

                    $ruleErrors = $this->validateAccountPayload($payload);
                    if ($ruleErrors !== []) {
                        $this->recordImportViolation(
                            $lineNumber,
                            $data,
                            $ruleErrors,
                            $skipped,
                            $violations,
                            $errors,
                            $payload
                        );
                        continue;
                    }

                    try {
                        $accountService->create($payload, $userId);
                        $created++;
                        $createdThisPass++;
                        $this->rememberHierarchy($hierarchy, $payload, $data);
                    } catch (\Throwable $exception) {
                        $this->recordImportViolation(
                            $lineNumber,
                            $data,
                            [$exception->getMessage()],
                            $skipped,
                            $violations,
                            $errors,
                            $payload
                        );
                    }
                }

                if ($deferred === []) {
                    break;
                }

                if ($createdThisPass === 0) {
                    foreach ($deferred as $item) {
                        $lineNumber = (int) $item['line_number'];
                        $data = $this->normalizeImportedCoaData($item['data'], $hierarchy);

                        try {
                            $payload = $this->toAccountPayload($data, $lineNumber);
                            $messages = ['Parent account could not be resolved from the existing CoA or the valid rows in this import file.'];
                        } catch (\Throwable $exception) {
                            $payload = null;
                            $messages = [$exception->getMessage()];
                        }

                        $this->recordImportViolation(
                            $lineNumber,
                            $data,
                            $messages,
                            $skipped,
                            $violations,
                            $errors,
                            $payload
                        );
                    }
                    break;
                }

                $remaining = $deferred;
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'conflicts' => $conflicts,
            'violations' => $violations,
            'issues' => array_values(array_merge($conflicts, $violations)),
        ];
    }

    private function shouldDeferMissingParent(\Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), 'Parent account not found:');
    }

    private function recordImportViolation(
        int $lineNumber,
        array $data,
        array $messages,
        int &$skipped,
        array &$violations,
        array &$errors,
        ?array $payload = null
    ): void {
        $skipped++;
        $payload ??= $this->draftPayloadFromImportData($data);
        $messages = array_values(array_unique(array_filter($messages)));
        $violations[] = $this->makeImportIssue('rule_violation', $lineNumber, $payload, $messages);
        $errors[] = 'Row ' . $lineNumber . ': ' . implode(' ', $messages);
    }

    private function prepareImportRows(array $rows): array
    {
        $headerIndex = $this->findHeaderRowIndex($rows);

        if ($headerIndex === null) {
            throw new RuntimeException('Could not find the CoA header row. The file must include Account Code plus Account Name or Level 4 Ledger / Control Head.');
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader($value), $rows[$headerIndex]);
        $dataRows = array_slice($rows, $headerIndex + 1);
        $firstDataLineNumber = $headerIndex + 2;

        return [$headers, $dataRows, $firstDataLineNumber];
    }

    private function findHeaderRowIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $headers = array_values(array_filter(array_map(fn ($value) => $this->normalizeHeader($value), $row)));

            if (! in_array('account_code', $headers, true)) {
                continue;
            }

            if (in_array('account_name', $headers, true) || in_array('level_4', $headers, true)) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeImportedCoaData(array $data, array $hierarchy): array
    {
        $level1 = $this->cleanValue($data['level_1'] ?? null);
        $level2 = $this->cleanValue($data['level_2'] ?? null);
        $level3 = $this->cleanValue($data['level_3'] ?? null);
        $level4 = $this->cleanValue($data['level_4'] ?? null);
        $accountName = $this->firstFilled([
            $data['account_name'] ?? null,
            $level4,
            $level3,
            $level2,
            $level1,
        ]);
        $postingAllowed = $this->truthy($data['posting_allowed'] ?? null);
        $coaLevel = $this->cleanValue($data['coa_level'] ?? null);

        if ($coaLevel === '') {
            $coaLevel = (string) $this->inferCoaLevel($level1, $level2, $level3, $level4, $postingAllowed);
        }

        $accountClass = $this->firstFilled([
            $data['account_class'] ?? null,
            $data['account_nature'] ?? null,
            $data['type'] ?? null,
        ]);

        $description = $this->firstFilled([
            $data['description'] ?? null,
            $data['module_relevance'] ?? null,
        ]);

        $exampleUsage = $this->firstFilled([
            $data['example_usage'] ?? null,
            $data['example_notes'] ?? null,
        ]);

        $status = $this->normalizeStatus($this->firstFilled([
            $data['status'] ?? null,
            $data['default_active'] ?? null,
        ]));

        $ledgerType = $this->firstFilled([
            $data['ledger_type'] ?? null,
            $this->inferLedgerType($accountName, $accountClass, (int) $coaLevel, $postingAllowed),
        ]);

        $parentCode = $this->cleanValue($data['parent_code'] ?? null);
        if ($parentCode === '') {
            $parentCode = $this->resolveParentCode((int) $coaLevel, $level1, $level2, $level3, $hierarchy);
        }

        $data['account_name'] = $accountName;
        $data['coa_level'] = $coaLevel;
        $data['parent_code'] = $parentCode;
        $data['account_class'] = $accountClass;
        $data['ledger_type'] = $ledgerType;
        $data['status'] = $status;
        $data['description'] = $description;
        $data['example_usage'] = $exampleUsage;
        $data['level_1'] = $level1;
        $data['level_2'] = $level2;
        $data['level_3'] = $level3;
        $data['level_4'] = $level4;

        if (! array_key_exists('user_selectable', $data) || $this->cleanValue($data['user_selectable']) === '') {
            $data['user_selectable'] = ((int) $coaLevel === 4 && $ledgerType !== 'Party Control') ? 'Yes' : 'No';
        }

        if (! array_key_exists('system_ledger', $data) || $this->cleanValue($data['system_ledger']) === '') {
            $data['system_ledger'] = 'No';
        }

        return $data;
    }

    private function inferCoaLevel(string $level1, string $level2, string $level3, string $level4, bool $postingAllowed): int
    {
        if ($postingAllowed) {
            return 4;
        }

        if ($level3 !== '') {
            return 3;
        }

        if ($level2 !== '') {
            return 2;
        }

        return 1;
    }

    private function inferLedgerType(string $accountName, string $accountClass, int $coaLevel, bool $postingAllowed): string
    {
        if ($coaLevel !== 4 || ! $postingAllowed) {
            return 'Group';
        }

        $name = strtolower($accountName);
        $class = strtolower($accountClass);

        if (str_contains($name, 'petty cash') || preg_match('/\bcash\b/i', $accountName)) {
            return 'Cash';
        }

        if (str_contains($name, 'bank') || str_contains($name, 'mobile financial') || str_contains($name, 'bkash') || str_contains($name, 'nagad') || str_contains($name, 'rocket')) {
            return 'Bank';
        }

        if (str_contains($name, 'receivable') || str_contains($name, 'payable') || str_contains($name, 'customer due') || str_contains($name, 'supplier due')) {
            return 'Party Control';
        }

        if (str_contains($name, 'inventory') || str_contains($name, 'stock')) {
            return 'Inventory';
        }

        if (str_contains($name, 'loan')) {
            return 'Loan';
        }

        if ($class === 'asset') {
            return 'Asset';
        }

        if ($class === 'liability') {
            return 'Liability';
        }

        if ($class === 'equity') {
            return str_contains($name, 'drawing') ? 'Equity Contra' : 'Equity';
        }

        if ($class === 'income') {
            return 'Income';
        }

        if ($class === 'expense') {
            return 'Expense';
        }

        return 'Other';
    }

    private function normalizeStatus(mixed $value): string
    {
        $value = strtolower($this->cleanValue($value));

        if (in_array($value, ['inactive', 'no', 'false', '0', 'disabled'], true)) {
            return 'Inactive';
        }

        return 'Active';
    }

    private function resolveParentCode(int $coaLevel, string $level1, string $level2, string $level3, array $hierarchy): string
    {
        if ($coaLevel <= 1) {
            return '';
        }

        if ($coaLevel === 2) {
            return $hierarchy['level1'][$this->key($level1)] ?? $hierarchy['last'][1] ?? '';
        }

        if ($coaLevel === 3) {
            return $hierarchy['level2'][$this->key($level1, $level2)] ?? $hierarchy['last'][2] ?? '';
        }

        return $hierarchy['level3'][$this->key($level1, $level2, $level3)] ?? $hierarchy['last'][3] ?? '';
    }

    private function existingHierarchy(): array
    {
        $hierarchy = [
            'level1' => [],
            'level2' => [],
            'level3' => [],
            'last' => [],
        ];

        ChartOfAccount::query()
            ->orderBy('account_code')
            ->get(['account_code', 'account_name', 'coa_level', 'account_level', 'account_group', 'account_sub_group'])
            ->each(function (ChartOfAccount $account) use (&$hierarchy) {
                $level = (int) ($account->coa_level ?: ($account->account_level === 'Ledger' ? 4 : 1));
                $name = $this->cleanValue($account->account_name);

                if ($level === 1) {
                    $hierarchy['level1'][$this->key($name)] = $account->account_code;
                }

                if ($level === 2) {
                    $hierarchy['level2'][$this->key('', $name)] = $account->account_code;
                }

                if ($level === 3) {
                    $hierarchy['level3'][$this->key('', '', $name)] = $account->account_code;
                }

                $hierarchy['last'][$level] = $account->account_code;
            });

        return $hierarchy;
    }

    private function rememberHierarchy(array &$hierarchy, array $payload, array $data): void
    {
        $level = (int) ($payload['coa_level'] ?? 4);
        $code = (string) ($payload['account_code'] ?? '');
        $level1 = $this->cleanValue($data['level_1'] ?? null);
        $level2 = $this->cleanValue($data['level_2'] ?? null);
        $level3 = $this->cleanValue($data['level_3'] ?? null);
        $accountName = $this->cleanValue($payload['account_name'] ?? null);

        if ($code === '') {
            return;
        }

        if ($level === 1) {
            $hierarchy['level1'][$this->key($level1 ?: $accountName)] = $code;
        }

        if ($level === 2) {
            $hierarchy['level2'][$this->key($level1, $level2 ?: $accountName)] = $code;
            $hierarchy['level2'][$this->key('', $level2 ?: $accountName)] = $code;
        }

        if ($level === 3) {
            $hierarchy['level3'][$this->key($level1, $level2, $level3 ?: $accountName)] = $code;
            $hierarchy['level3'][$this->key('', '', $level3 ?: $accountName)] = $code;
        }

        $hierarchy['last'][$level] = $code;
    }

    private function toAccountPayload(array $data, int $lineNumber): array
    {
        $accountCode = trim((string) ($data['account_code'] ?? ''));
        $accountName = trim((string) ($data['account_name'] ?? ''));
        $coaLevel = (int) ($data['coa_level'] ?? 4);
        $accountClass = trim((string) ($data['account_class'] ?? ''));
        $normalBalance = $this->normalizeNormalBalance($data['normal_balance'] ?? '');
        $ledgerType = trim((string) ($data['ledger_type'] ?? ($coaLevel === 4 ? 'Asset' : 'Group')));

        if ($accountCode === '' || $accountName === '') {
            throw new RuntimeException('Account Code and Account Name are required.');
        }

        if (! in_array($coaLevel, [1, 2, 3, 4], true)) {
            throw new RuntimeException('CoA Level must be 1, 2, 3, or 4.');
        }

        $accountType = AccountType::query()
            ->where('name', $accountClass)
            ->orWhere('code', $accountClass)
            ->first();

        if (! $accountType) {
            throw new RuntimeException('Account Class not found: ' . ($accountClass ?: '(blank)'));
        }

        $parentId = null;
        if ($coaLevel > 1) {
            $parentCode = trim((string) ($data['parent_code'] ?? ''));
            if ($parentCode === '') {
                throw new RuntimeException('Parent Code is required for Level ' . $coaLevel . '.');
            }

            $parent = ChartOfAccount::query()->where('account_code', $parentCode)->first();
            if (! $parent) {
                throw new RuntimeException('Parent account not found: ' . $parentCode . '. Import parent rows first.');
            }

            $parentId = $parent->id;
        }

        $partyTypeId = null;
        $partyTypeName = trim((string) ($data['party_type'] ?? ''));
        if ($partyTypeName !== '') {
            $partyTypeId = PartyType::query()
                ->where('name', $partyTypeName)
                ->orWhere('code', $partyTypeName)
                ->value('id');
        }

        return [
            'account_code' => $accountCode,
            'account_name' => $accountName,
            'coa_level' => $coaLevel,
            'parent_id' => $parentId,
            'account_type_id' => $accountType->id,
            'normal_balance' => in_array($normalBalance, ['Debit', 'Credit'], true) ? $normalBalance : $accountType->normal_balance,
            'ledger_type' => $coaLevel === 4 ? ($ledgerType ?: 'Asset') : 'Group',
            'party_type_id' => $partyTypeId,
            'is_user_selectable' => $this->truthy($data['user_selectable'] ?? true),
            'is_system_ledger' => $this->truthy($data['system_ledger'] ?? false),
            'description' => $data['description'] ?? null,
            'example_usage' => $data['example_usage'] ?? null,
            'status' => in_array(($data['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $data['status'] : 'Active',
        ];
    }

    public function normalizeResolvedPayload(array $input): array
    {
        $coaLevel = (int) ($input['coa_level'] ?? 4);
        $coaLevel = in_array($coaLevel, [1, 2, 3, 4], true) ? $coaLevel : 4;

        $ledgerType = trim((string) ($input['ledger_type'] ?? ($coaLevel === 4 ? 'Asset' : 'Group')));
        if ($coaLevel < 4) {
            $ledgerType = 'Group';
        }

        $normalBalance = $this->normalizeNormalBalance($input['normal_balance'] ?? '');

        return [
            'account_code' => trim((string) ($input['account_code'] ?? '')),
            'account_name' => trim((string) ($input['account_name'] ?? '')),
            'coa_level' => $coaLevel,
            'parent_id' => $coaLevel === 1 ? null : ($this->nullableInt($input['parent_id'] ?? null)),
            'account_type_id' => $this->nullableInt($input['account_type_id'] ?? null),
            'normal_balance' => in_array($normalBalance, ['Debit', 'Credit'], true) ? $normalBalance : trim((string) ($input['normal_balance'] ?? '')),
            'ledger_type' => $ledgerType,
            'party_type_id' => $this->nullableInt($input['party_type_id'] ?? null),
            'is_user_selectable' => $this->truthy($input['is_user_selectable'] ?? false),
            'is_system_ledger' => $this->truthy($input['is_system_ledger'] ?? false),
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'example_usage' => trim((string) ($input['example_usage'] ?? '')) ?: null,
            'status' => in_array(($input['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $input['status'] : 'Active',
        ];
    }

    public function validateAccountPayload(array $payload, ?int $ignoreAccountId = null): array
    {
        $errors = [];
        $accountCode = trim((string) ($payload['account_code'] ?? ''));
        $accountName = trim((string) ($payload['account_name'] ?? ''));
        $coaLevel = (int) ($payload['coa_level'] ?? 4);
        $ledgerType = trim((string) ($payload['ledger_type'] ?? ''));
        $parentId = $payload['parent_id'] ?? null;
        $accountTypeId = $payload['account_type_id'] ?? null;
        $normalBalance = $this->normalizeNormalBalance($payload['normal_balance'] ?? '');
        $activeLedgerTypes = LedgerType::activeNames();

        if ($accountCode === '') {
            $errors[] = 'Account Code is required.';
        } elseif (! preg_match('/^[A-Za-z0-9.\-_]+$/', $accountCode)) {
            $errors[] = 'Account Code may contain only letters, numbers, dots, hyphens, and underscores.';
        }

        if ($accountName === '') {
            $errors[] = 'Account Name is required.';
        }

        if (! in_array($coaLevel, [1, 2, 3, 4], true)) {
            $errors[] = 'CoA Level must be 1, 2, 3, or 4.';
        }

        $accountType = AccountType::query()->find($accountTypeId);
        if (! $accountType) {
            $errors[] = 'Account Class is invalid or missing.';
        }

        if (! in_array($ledgerType, $activeLedgerTypes, true)) {
            $errors[] = 'Ledger Type is invalid or inactive: ' . ($ledgerType ?: '(blank)') . '.';
        }

        if ($accountCode !== '') {
            $duplicateCode = ChartOfAccount::query()
                ->where('account_code', $accountCode)
                ->when($ignoreAccountId, fn ($query) => $query->whereKeyNot($ignoreAccountId))
                ->first();

            if ($duplicateCode) {
                $errors[] = 'Account Code already exists: ' . $duplicateCode->display_name . '.';
            }
        }

        if ($accountName !== '') {
            $duplicateName = ChartOfAccount::query()
                ->where('account_name', $accountName)
                ->when($ignoreAccountId, fn ($query) => $query->whereKeyNot($ignoreAccountId))
                ->first();

            if ($duplicateName) {
                $errors[] = 'Account Name already exists: ' . $duplicateName->display_name . '.';
            }
        }

        if ($coaLevel === 1 && ! empty($parentId)) {
            $errors[] = 'Level 1 Account Class cannot have a parent account.';
        }

        $parent = null;
        if ($coaLevel > 1) {
            if (empty($parentId)) {
                $errors[] = 'Parent Account is required for Level ' . $coaLevel . '.';
            } else {
                $parent = ChartOfAccount::query()->find($parentId);
                if (! $parent) {
                    $errors[] = 'Parent account was not found. Select a valid parent before adding this account.';
                } else {
                    $parentLevel = (int) ($parent->coa_level ?: ($parent->account_level === 'Ledger' ? 4 : max(1, $coaLevel - 1)));

                    if ($parent->status !== 'Active') {
                        $errors[] = 'Parent Account must be active.';
                    }

                    if ($parent->account_level !== 'Group') {
                        $errors[] = 'Parent Account must be a non-posting Group account.';
                    }

                    if ($parentLevel !== $coaLevel - 1) {
                        $errors[] = 'Parent Account must be exactly one level above this account.';
                    }

                    if ($accountType && (int) $parent->account_type_id !== (int) $accountType->id) {
                        $errors[] = 'Parent Account must use the same Account Class.';
                    }
                }
            }
        }

        if ($coaLevel < 4 && $ledgerType !== 'Group') {
            $errors[] = 'Level 1, 2, and 3 accounts must use Ledger Type Group because posting is allowed only in Level 4 ledger heads.';
        }

        if ($coaLevel === 4 && $ledgerType === 'Group') {
            $errors[] = 'Level 4 ledger heads cannot use Ledger Type Group. Select the actual ledger type such as Asset, Expense, Income, Cash, Bank, or Party Control.';
        }

        if ($accountType && $ledgerType !== '') {
            $compatibilityError = $this->ledgerTypeCompatibilityError($ledgerType, (string) $accountType->name);
            if ($compatibilityError) {
                $errors[] = $compatibilityError;
            }
        }

        if (in_array($ledgerType, ['Cash', 'Bank'], true) && $accountType && $accountType->name !== 'Asset') {
            $errors[] = 'Cash and Bank ledgers must use Asset account class.';
        }

        if ($ledgerType === 'Party Control' && empty($payload['party_type_id'])) {
            $errors[] = 'Party Type is required for Party Control ledgers.';
        }

        if ($ledgerType === 'Party Control' && $this->truthy($payload['is_user_selectable'] ?? false)) {
            $errors[] = 'Party Control ledgers should not be directly user selectable because party selection is handled by transaction rules.';
        }

        if (! in_array($normalBalance, ['Debit', 'Credit'], true)) {
            $errors[] = 'Normal Balance must be Debit or Credit.';
        } elseif ($accountType) {
            $expectedBalance = $this->expectedNormalBalance((string) $accountType->name, $ledgerType);
            if ($expectedBalance && $normalBalance !== $expectedBalance) {
                $errors[] = 'Normal Balance should be ' . $expectedBalance . ' for ' . $accountType->name . ($ledgerType ? ' / ' . $ledgerType : '') . ' according to accounting rules.';
            }
        }

        if (! in_array(($payload['status'] ?? 'Active'), ['Active', 'Inactive'], true)) {
            $errors[] = 'Status must be Active or Inactive.';
        }

        return array_values(array_unique($errors));
    }

    private function draftPayloadFromImportData(array $data): array
    {
        $coaLevel = (int) ($data['coa_level'] ?? 4);
        $accountClass = trim((string) ($data['account_class'] ?? ''));
        $accountType = $accountClass !== ''
            ? AccountType::query()->where('name', $accountClass)->orWhere('code', $accountClass)->first()
            : null;
        $normalBalance = $this->normalizeNormalBalance($data['normal_balance'] ?? '');
        $parentId = null;
        $parentCode = trim((string) ($data['parent_code'] ?? ''));
        if ($parentCode !== '') {
            $parentId = ChartOfAccount::query()->where('account_code', $parentCode)->value('id');
        }

        return [
            'account_code' => trim((string) ($data['account_code'] ?? '')),
            'account_name' => trim((string) ($data['account_name'] ?? '')),
            'coa_level' => in_array($coaLevel, [1, 2, 3, 4], true) ? $coaLevel : 4,
            'parent_id' => $parentId,
            'account_type_id' => $accountType?->id,
            'normal_balance' => in_array($normalBalance, ['Debit', 'Credit'], true) ? $normalBalance : ($accountType?->normal_balance ?? 'Debit'),
            'ledger_type' => $coaLevel === 4 ? trim((string) ($data['ledger_type'] ?? 'Asset')) : 'Group',
            'party_type_id' => null,
            'is_user_selectable' => $this->truthy($data['user_selectable'] ?? true),
            'is_system_ledger' => $this->truthy($data['system_ledger'] ?? false),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'example_usage' => trim((string) ($data['example_usage'] ?? '')) ?: null,
            'status' => in_array(($data['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $data['status'] : 'Active',
        ];
    }

    private function findDuplicateAccounts(array $payload): array
    {
        $duplicates = [];
        $accountCode = trim((string) ($payload['account_code'] ?? ''));
        $accountName = trim((string) ($payload['account_name'] ?? ''));

        if ($accountCode !== '') {
            $existingByCode = ChartOfAccount::query()->where('account_code', $accountCode)->first();
            if ($existingByCode) {
                $duplicates[$existingByCode->id] = $this->accountConflictSummary($existingByCode, 'Account Code already exists.');
            }
        }

        if ($accountName !== '') {
            $existingByName = ChartOfAccount::query()->where('account_name', $accountName)->first();
            if ($existingByName) {
                $duplicates[$existingByName->id] = $this->accountConflictSummary($existingByName, 'Account Name already exists.');
            }
        }

        return array_values($duplicates);
    }

    private function accountConflictSummary(ChartOfAccount $account, string $reason): array
    {
        return [
            'id' => $account->id,
            'reason' => $reason . ' Existing: ' . $account->display_name,
            'account_code' => $account->account_code,
            'account_name' => $account->account_name,
            'coa_level' => $account->coa_level,
            'account_type_id' => $account->account_type_id,
            'account_type_name' => $account->accountType?->name,
            'parent_id' => $account->parent_id,
            'parent_name' => $account->parent?->display_name,
            'normal_balance' => $account->normal_balance,
            'ledger_type' => $account->ledger_type,
            'status' => $account->status,
        ];
    }

    private function makeImportIssue(string $type, int $lineNumber, array $payload, array $reasons, array $existing = []): array
    {
        $id = substr(sha1($type . '|' . $lineNumber . '|' . json_encode($payload) . '|' . json_encode($reasons)), 0, 16);

        return [
            'id' => $id,
            'type' => $type,
            'line_number' => $lineNumber,
            'title' => $type === 'conflict' ? 'Existing Account Conflict' : 'Accounting Rule Issue',
            'account_code' => $payload['account_code'] ?? '',
            'account_name' => $payload['account_name'] ?? '',
            'reasons' => array_values(array_unique(array_filter($reasons))),
            'existing' => $existing,
            'payload' => $this->reviewPayload($payload),
        ];
    }

    private function reviewPayload(array $payload): array
    {
        return [
            'account_code' => (string) ($payload['account_code'] ?? ''),
            'account_name' => (string) ($payload['account_name'] ?? ''),
            'coa_level' => (int) ($payload['coa_level'] ?? 4),
            'parent_id' => $this->nullableInt($payload['parent_id'] ?? null),
            'account_type_id' => $this->nullableInt($payload['account_type_id'] ?? null),
            'normal_balance' => (string) ($payload['normal_balance'] ?? 'Debit'),
            'ledger_type' => (string) ($payload['ledger_type'] ?? 'Asset'),
            'party_type_id' => $this->nullableInt($payload['party_type_id'] ?? null),
            'is_user_selectable' => (bool) ($payload['is_user_selectable'] ?? false),
            'is_system_ledger' => (bool) ($payload['is_system_ledger'] ?? false),
            'description' => (string) ($payload['description'] ?? ''),
            'example_usage' => (string) ($payload['example_usage'] ?? ''),
            'status' => (string) ($payload['status'] ?? 'Active'),
        ];
    }

    private function ledgerTypeCompatibilityError(string $ledgerType, string $accountTypeName): ?string
    {
        $accountTypeName = trim($accountTypeName);

        $assetLedgerTypes = ['Cash', 'Bank', 'Inventory', 'Asset'];
        if (in_array($ledgerType, $assetLedgerTypes, true) && $accountTypeName !== 'Asset') {
            return $ledgerType . ' ledger must be placed under Asset account class.';
        }

        if ($ledgerType === 'Loan' && $accountTypeName !== 'Liability') {
            return 'Loan ledger should be placed under Liability account class.';
        }

        if ($ledgerType === 'Liability' && $accountTypeName !== 'Liability') {
            return 'Liability ledger must be placed under Liability account class.';
        }

        if (in_array($ledgerType, ['Equity', 'Equity Contra'], true) && ! in_array($accountTypeName, ['Equity', "Owner's Equity", 'Owner’s Equity'], true)) {
            return $ledgerType . ' ledger must be placed under Equity account class.';
        }

        if ($ledgerType === 'Income' && $accountTypeName !== 'Income') {
            return 'Income ledger must be placed under Income account class.';
        }

        if ($ledgerType === 'Expense' && $accountTypeName !== 'Expense') {
            return 'Expense ledger must be placed under Expense account class.';
        }

        return null;
    }

    private function expectedNormalBalance(string $accountTypeName, string $ledgerType): ?string
    {
        if ($ledgerType === 'Equity Contra') {
            return 'Debit';
        }

        return match ($accountTypeName) {
            'Asset', 'Expense' => 'Debit',
            'Liability', 'Equity', "Owner's Equity", 'Owner’s Equity', 'Income' => 'Credit',
            default => null,
        };
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('Could not read the uploaded file.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('XLSX import requires the PHP zip extension. Please upload CSV or enable ZipArchive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the Excel file.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = simplexml_load_string($sharedXml);
            foreach ($xml->si ?? [] as $item) {
                if (isset($item->t)) {
                    $sharedStrings[] = (string) $item->t;
                } else {
                    $text = '';
                    foreach ($item->r ?? [] as $run) {
                        $text .= (string) $run->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('The Excel file does not contain sheet1.');
        }

        $xml = simplexml_load_string($sheetXml);
        $rows = [];
        $expectedRowNumber = 1;

        foreach ($xml->sheetData->row ?? [] as $row) {
            $rowNumber = (int) ($row['r'] ?? $expectedRowNumber);
            while ($expectedRowNumber < $rowNumber) {
                $rows[] = [];
                $expectedRowNumber++;
            }

            $values = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $columnIndex = $this->columnIndex(preg_replace('/\d+/', '', $ref));
                $type = (string) $cell['t'];
                $value = '';

                if ($type === 's') {
                    $value = $sharedStrings[(int) $cell->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } else {
                    $value = (string) ($cell->v ?? '');
                }

                $values[$columnIndex] = $value;
            }

            if ($values) {
                ksort($values);
                $max = max(array_keys($values));
                $rowValues = [];
                for ($i = 0; $i <= $max; $i++) {
                    $rowValues[] = $values[$i] ?? '';
                }
                $rows[] = $rowValues;
            } else {
                $rows[] = [];
            }

            $expectedRowNumber = $rowNumber + 1;
        }

        return $rows;
    }

    private function writeXlsx(string $path, array $rows): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create Excel export file.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Chart of Accounts" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rows));
        $zip->close();
    }

    private function exportCsvPath(array $rows): string
    {
        $path = storage_path('app/exports/chart-of-accounts-' . now()->format('Ymd-His') . '.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    private function sheetXml(array $rows): string
    {
        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cells = '';
            foreach (array_values($row) as $colIndex => $value) {
                $ref = $this->columnName($colIndex) . ($rowIndex + 1);
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
            }
            $sheetRows .= '<row r="' . ($rowIndex + 1) . '">' . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheetRows . '</sheetData></worksheet>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = intdiv($index - $mod, 26);
        }

        return $name;
    }

    private function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    private function mapRow(array $headers, array $row): array
    {
        $mapped = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $mapped[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $mapped;
    }

    private function normalizeHeader(mixed $value): string
    {
        $header = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $value), '_'));

        return match ($header) {
            'code', 'account_code', 'account_no', 'account_number' => 'account_code',
            'name', 'account_name', 'ledger_name', 'account_title' => 'account_name',
            'level_1', 'level1' => 'level_1',
            'level_2', 'level2' => 'level_2',
            'level_3', 'level3' => 'level_3',
            'level_4', 'level4', 'level_4_ledger_control_head', 'ledger_control_head', 'level_4_ledger_head', 'level_4_ledger_posting_account' => 'level_4',
            'coa_level', 'level', 'account_level' => 'coa_level',
            'parent_code', 'parent_account_code', 'parent' => 'parent_code',
            'account_class', 'account_type', 'account_nature', 'nature' => 'account_class',
            'normal_balance', 'balance_type' => 'normal_balance',
            'ledger_type', 'ledger_types', 'type_of_ledger' => 'ledger_type',
            'party_type', 'party_person_type' => 'party_type',
            'posting_allowed', 'posting', 'can_post', 'can_transactions_be_posted_here' => 'posting_allowed',
            'user_selectable', 'selectable', 'should_users_select_this_in_transaction_entry' => 'user_selectable',
            'system_ledger', 'is_system', 'system' => 'system_ledger',
            'status', 'is_active', 'active', 'default_active' => $header === 'default_active' ? 'default_active' : 'status',
            'description', 'notes' => 'description',
            'module_sme_relevance', 'module_relevance', 'sme_relevance' => 'module_relevance',
            'example_use_notes', 'example_usage', 'example_use', 'examples' => 'example_usage',
            default => $header,
        };
    }

    private function normalizeNormalBalance(mixed $value): string
    {
        $value = strtolower($this->cleanValue($value));

        return match ($value) {
            'dr', 'debit' => 'Debit',
            'cr', 'credit' => 'Credit',
            default => '',
        };
    }

    private function blankRow(array $row): bool
    {
        return collect($row)->filter(fn ($value) => trim((string) $value) !== '')->isEmpty();
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'active', 'y'], true);
    }

    private function cleanValue(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            $value = $this->cleanValue($value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function key(string ...$parts): string
    {
        return strtolower(implode('|', array_map(fn ($part) => trim($part), $parts)));
    }
}
