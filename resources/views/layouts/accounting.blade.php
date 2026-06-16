<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="hg-body">
<div class="hg-app">
    @include('partials.accounting.sidebar')

    <main class="hg-main">
        <header class="hg-topbar">
            <div class="hg-topbar-title">{{ $title ?? 'HisebGhor' }}</div>
        </header>

        <div class="hg-content">
            @if (session('success'))
                <div class="hg-alert hg-alert-success">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="hg-alert hg-alert-danger">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="hg-alert hg-alert-danger">
                    <strong>Please correct the following:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </div>
    </main>
</div>

<x-accounting.safe-delete-modal />

@stack('scripts')
</body>
</html>
