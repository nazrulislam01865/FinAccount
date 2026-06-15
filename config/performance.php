<?php

return [
    'cache' => [
        'landing_page_ttl_seconds' => (int) env('PERF_LANDING_PAGE_CACHE_TTL', 86400),
    ],
];
