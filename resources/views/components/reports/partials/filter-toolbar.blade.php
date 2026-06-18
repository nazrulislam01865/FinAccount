<form method="GET" action="{{ $action }}" class="hg-report-toolbar">
    {{ $slot }}
    <div class="hg-report-toolbar-actions">
        <button type="submit" class="hg-btn hg-btn-primary">Generate</button>
        <a class="hg-btn" href="{{ $action }}">Reset</a>
        <a class="hg-btn" href="{{ $exportUrl }}">Export CSV</a>
        <button type="button" class="hg-btn" onclick="window.print()">Print</button>
    </div>
</form>
