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
];
