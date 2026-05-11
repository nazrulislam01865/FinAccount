<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Accounting System')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body>
<div class="app">
    @include('partials.sidebar')

    <main class="main">
        @include('partials.topbar')

        <section class="content">
            @yield('content')
        </section>
    </main>
</div>

<div class="toast" id="toast">Saved successfully.</div>

@stack('scripts')
</body>
</html>
