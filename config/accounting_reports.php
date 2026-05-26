<?php

return [
    'route_prefix' => env('ACCOUNTING_REPORTS_ROUTE_PREFIX', 'accounting/reports'),
    'currency' => env('ACCOUNTING_REPORTS_CURRENCY', 'BDT'),
    'per_page' => 25,
    'add_transaction_route' => 'transactions.create',

    'permissions' => [
        'view_reports' => null,
        'reverse_transactions' => null,
    ],

    'statuses' => [
        'posted' => ['Posted', 'POSTED', 'posted'],
        'draft' => ['Draft', 'DRAFT', 'draft', 'Pending Review', 'PENDING_REVIEW'],
        'reversed' => ['Reversed', 'REVERSED', 'reversed'],
        'cancelled' => ['Cancelled', 'CANCELLED', 'cancelled'],
    ],

    'reverse' => [
        'voucher_prefix' => 'REV',
        'status_after_reverse' => 'Reversed',
        'reversal_journal_status' => 'Posted',
    ],

    'income_statement_notes' => [
        'revenue' => '12.00',
        'admin_selling_expenses' => '13.00',
        'financial_expenses' => '14.00',
        'other_income' => '16.00',
        'income_tax_expense' => '10.00',
    ],

    'audit_income_statement_labels' => [
        'revenue' => env('ACCOUNTING_REPORTS_AUDIT_REVENUE_LABEL', 'Commission'),
    ],

    'income_statement_sections' => [
        'cost_of_services_keywords' => [
            'cost of services',
            'service cost',
            'direct cost',
            'cost of sales',
            'purchase',
            'cogs',
            'seed cost',
            'stock cost',
        ],
        'admin_selling_expense_keywords' => [
            'administrative',
            'selling',
            'salary',
            'rent',
            'office',
            'utility',
            'electricity',
            'conveyance',
            'allowance',
            'entertainment',
            'telephone',
            'mobile',
            'fuel',
            'miscellaneous',
        ],
        'financial_expense_keywords' => [
            'financial expense',
            'finance cost',
            'bank charge',
            'bank charges',
            'interest',
            'loan interest',
        ],
        'other_income_keywords' => [
            'other income',
            'gain',
            'profit on sale',
            'miscellaneous income',
        ],
        'tax_expense_keywords' => [
            'income tax',
            'tax expense',
            'provision for tax',
            'taxation',
        ],
    ],

];
