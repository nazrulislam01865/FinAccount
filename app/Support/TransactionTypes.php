<?php

namespace App\Support;

final class TransactionTypes
{
    public const SALE = 'SALE';
    public const PURCHASE = 'PURCHASE';
    public const CUSTOMER_COLLECTION = 'CUSTOMER_COLLECTION';
    public const SUPPLIER_PAYMENT = 'SUPPLIER_PAYMENT';
    public const EXPENSE = 'EXPENSE';
    public const OWNER_INVESTMENT = 'OWNER_INVESTMENT';
    public const OWNER_WITHDRAWAL = 'OWNER_WITHDRAWAL';
    public const LOAN_RECEIVED = 'LOAN_RECEIVED';
    public const LOAN_REPAYMENT = 'LOAN_REPAYMENT';
    public const LOAN_INTEREST_PAYMENT = 'LOAN_INTEREST_PAYMENT';
    public const ASSET_PURCHASE = 'ASSET_PURCHASE';

    public const CASH = 'CASH';
    public const CREDIT = 'CREDIT';
    public const PARTIAL = 'PARTIAL';

    /** @var array<int, string> */
    public const ALL_SETTLEMENTS = [self::CASH, self::PARTIAL, self::CREDIT];

    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        return [
            self::SALE => [
                'label' => 'Sale',
                'action_label' => 'I sold something',
                'voucher_prefix' => 'SAL',
                'money_label' => 'Receive In',
                'party_label' => 'Customer',
                'party_type' => 'Customer',
                'allowed_settlements' => self::ALL_SETTLEMENTS,
                'default_settlements' => [self::CASH],
                'posting_types' => ['Income'],
            ],
            self::PURCHASE => [
                'label' => 'Purchase',
                'action_label' => 'I bought goods or materials',
                'voucher_prefix' => 'PUR',
                'money_label' => 'Pay From',
                'party_label' => 'Supplier',
                'party_type' => 'Supplier',
                'allowed_settlements' => self::ALL_SETTLEMENTS,
                'default_settlements' => [self::CASH],
                'posting_types' => ['Asset', 'Expense'],
            ],
            self::CUSTOMER_COLLECTION => [
                'label' => 'Customer Collection',
                'action_label' => 'I received a customer due',
                'voucher_prefix' => 'COL',
                'money_label' => 'Receive In',
                'party_label' => 'Customer',
                'party_type' => 'Customer',
                'allowed_settlements' => self::ALL_SETTLEMENTS,
                'default_settlements' => [self::CASH],
                'posting_types' => ['Asset'],
            ],
            self::SUPPLIER_PAYMENT => [
                'label' => 'Supplier Payment',
                'action_label' => 'I paid a supplier due',
                'voucher_prefix' => 'SPY',
                'money_label' => 'Pay From',
                'party_label' => 'Supplier',
                'party_type' => 'Supplier',
                'allowed_settlements' => self::ALL_SETTLEMENTS,
                'default_settlements' => [self::CASH],
                'posting_types' => ['Liability'],
            ],
            self::EXPENSE => [
                'label' => 'Expense',
                'action_label' => 'I recorded a business expense',
                'voucher_prefix' => 'EXP',
                'money_label' => 'Pay From',
                'party_label' => 'Payable To',
                'party_type' => 'Any',
                'allowed_settlements' => self::ALL_SETTLEMENTS,
                'default_settlements' => [self::CASH],
                'posting_types' => ['Expense'],
            ],
            self::OWNER_INVESTMENT => [
                'label' => 'Owner Investment',
                'action_label' => 'Owner added money',
                'voucher_prefix' => 'OIN',
                'money_label' => 'Receive In',
                'party_label' => 'Owner',
                'party_type' => 'Owner',
                'allowed_settlements' => self::ALL_SETTLEMENTS,
                'default_settlements' => [self::CASH],
                'posting_types' => ['Equity'],
            ],
            self::OWNER_WITHDRAWAL => [
                'label' => 'Owner Withdrawal',
                'action_label' => 'Owner took money',
                'voucher_prefix' => 'OWD',
                'money_label' => 'Pay From',
                'party_label' => 'Owner',
                'party_type' => 'Owner',
                'allowed_settlements' => self::ALL_SETTLEMENTS,
                'default_settlements' => [self::CASH],
                'posting_types' => ['Equity'],
            ],
            self::LOAN_RECEIVED => [
                'label' => 'Loan Received',
                'action_label' => 'I received a loan',
                'voucher_prefix' => 'LRV',
                'money_label' => 'Receive In',
                'party_label' => 'Lender',
                'party_type' => 'Lender',
                'allowed_settlements' => self::ALL_SETTLEMENTS,
                'default_settlements' => [self::CASH],
                'posting_types' => ['Liability'],
            ],
            self::LOAN_REPAYMENT => [
                'label' => 'Loan Repayment',
                'action_label' => 'I repaid loan principal',
                'voucher_prefix' => 'LRP',
                'money_label' => 'Pay From',
                'party_label' => 'Lender',
                'party_type' => 'Lender',
                'allowed_settlements' => self::ALL_SETTLEMENTS,
                'default_settlements' => [self::CASH],
                'posting_types' => ['Liability'],
            ],
            self::LOAN_INTEREST_PAYMENT => [
                'label' => 'Loan Interest Payment',
                'action_label' => 'I paid loan interest',
                'voucher_prefix' => 'LIP',
                'money_label' => 'Pay From',
                'party_label' => 'Lender',
                'party_type' => 'Lender',
                'allowed_settlements' => self::ALL_SETTLEMENTS,
                'default_settlements' => [self::CASH],
                'posting_types' => ['Expense'],
            ],
            self::ASSET_PURCHASE => [
                'label' => 'Asset Purchase',
                'action_label' => 'I bought a business asset',
                'voucher_prefix' => 'AST',
                'money_label' => 'Pay From',
                'party_label' => 'Supplier',
                'party_type' => 'Supplier',
                'allowed_settlements' => self::ALL_SETTLEMENTS,
                'default_settlements' => [self::CASH],
                'posting_types' => ['Asset'],
            ],
        ];
    }

    /** @return array<string, array{label:string,description:string}> */
    public static function settlementDefinitions(): array
    {
        return [
            self::CASH => [
                'label' => 'Fully paid/received',
                'description' => 'The full amount is paid or received now.',
            ],
            self::PARTIAL => [
                'label' => 'Partially paid/received',
                'description' => 'Some money moves now and the remaining amount becomes due.',
            ],
            self::CREDIT => [
                'label' => 'Fully due',
                'description' => 'No money moves now; the full amount remains due.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function definition(string $type): array
    {
        return self::definitions()[$type] ?? [];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array{
     *     label:string,
     *     action_label:string,
     *     voucher_prefix:string,
     *     money_label:string,
     *     party_label:string,
     *     party_type:string,
     *     allowed_settlements:array<int,string>,
     *     default_settlements:array<int,string>,
     *     posting_types:array<int,string>,
     *     flow?:string
     * }
     */
    public static function configuredDefinition(string $type, array $metadata = [], ?string $label = null): array
    {
        $definition = self::definition($type);
        $source = $definition !== [] ? $definition : $metadata;
        $displayLabel = (string) ($definition['label'] ?? $label ?: $type);

        return [
            'label' => $displayLabel,
            'action_label' => (string) ($source['action_label'] ?? $displayLabel),
            'voucher_prefix' => (string) ($source['voucher_prefix'] ?? ''),
            'money_label' => (string) ($source['money_label'] ?? 'Cash / Bank / Mobile Account'),
            'party_label' => (string) ($source['party_label'] ?? 'Party'),
            'party_type' => (string) ($source['party_type'] ?? 'Any'),
            'allowed_settlements' => array_values(array_map(
                static fn ($value): string => (string) $value,
                (array) ($source['allowed_settlements'] ?? self::ALL_SETTLEMENTS),
            )),
            'default_settlements' => array_values(array_map(
                static fn ($value): string => (string) $value,
                (array) ($source['default_settlements'] ?? [self::CASH]),
            )),
            'posting_types' => array_values(array_map(
                static fn ($value): string => (string) $value,
                (array) ($source['posting_types'] ?? []),
            )),
            'flow' => self::flow($type, $metadata),
        ];
    }

    /** @param array<string, mixed> $metadata */
    public static function flow(string $type, array $metadata = []): string
    {
        if (in_array($type, [
            self::SALE,
            self::CUSTOMER_COLLECTION,
            self::OWNER_INVESTMENT,
            self::LOAN_RECEIVED,
        ], true)) {
            return 'incoming';
        }

        if (in_array($type, [
            self::PURCHASE,
            self::SUPPLIER_PAYMENT,
            self::EXPENSE,
            self::OWNER_WITHDRAWAL,
            self::LOAN_REPAYMENT,
            self::LOAN_INTEREST_PAYMENT,
            self::ASSET_PURCHASE,
        ], true)) {
            return 'outgoing';
        }

        return in_array(($metadata['flow'] ?? null), ['incoming', 'outgoing'], true)
            ? (string) $metadata['flow']
            : 'outgoing';
    }

    /** @return array<int, string> */
    public static function allowedSettlements(string $type): array
    {
        return self::definition($type)['allowed_settlements'] ?? self::ALL_SETTLEMENTS;
    }

    /** @return array<int, string> */
    public static function defaultSettlements(string $type): array
    {
        return self::definition($type)['default_settlements'] ?? [self::CASH];
    }

    /** @return array<int, string> */
    public static function settlementCodes(): array
    {
        return array_keys(self::settlementDefinitions());
    }

    public static function partyType(string $type): string
    {
        return (string) (self::definition($type)['party_type'] ?? 'Any');
    }

    public static function partyLabel(string $type): string
    {
        return (string) (self::definition($type)['party_label'] ?? 'Party');
    }

    public static function moneyLabel(string $type): string
    {
        return (string) (self::definition($type)['money_label'] ?? 'Cash / Bank / Mobile Account');
    }

    /** @return array<int, string> */
    public static function postingTypes(string $type): array
    {
        return self::definition($type)['posting_types'] ?? [];
    }

    public static function isSale(string $type): bool
    {
        return $type === self::SALE;
    }
}
