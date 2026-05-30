<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Cache TTLs
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'landing_page_ttl_seconds' => env('PERF_LANDING_PAGE_CACHE_TTL', 86400),
        'report_ttl_seconds' => env('PERF_REPORT_CACHE_TTL', 300),
        'dashboard_ttl_seconds' => env('PERF_DASHBOARD_CACHE_TTL', 120),
        'dropdown_ttl_seconds' => env('PERF_DROPDOWN_CACHE_TTL', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Defaults
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'dashboard_rows' => env('PERF_DASHBOARD_ROWS', 10),
        'admin_table_rows' => env('PERF_ADMIN_TABLE_ROWS', 10),
        'report_rows' => env('PERF_REPORT_ROWS', 10),
    ],
];
