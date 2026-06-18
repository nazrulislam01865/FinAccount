@php
    $systemBrand = \App\Support\HisebGhorBrand::data();
@endphp
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<title>{{ filled($title ?? null) ? $title.' - '.($systemBrand['name'] ?? config('app.name')) : ($systemBrand['name'] ?? config('app.name')) }}</title>
@if(!empty($systemBrand['favicon_url']))
<link rel="icon" href="{{ $systemBrand['favicon_url'] }}">
<link rel="shortcut icon" href="{{ $systemBrand['favicon_url'] }}">
<link rel="apple-touch-icon" href="{{ $systemBrand['favicon_url'] }}">
@else
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif
@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="stylesheet" href="{{ asset('css/hisebghor-profile-notifications.css') }}?v={{ is_file(public_path('css/hisebghor-profile-notifications.css')) ? filemtime(public_path('css/hisebghor-profile-notifications.css')) : '1' }}">
@fluxAppearance
