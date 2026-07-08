<x-layouts::accounting title="Balance Sheet">
    @php
        $sectionGroups = $report['section_groups'];
        $assetSections = $sectionGroups->get('Asset', collect());
        $liabilitySections = $sectionGroups->get('Liability', collect());
        $equitySections = $sectionGroups->get('Equity', collect());
    @endphp

    <div class="hg-report-page">
        <div class="hg-page-header hg-report-page-header">
            <div>
                <h1>Balance Sheet</h1>
                <p>Shows assets, liabilities, equity, and retained profit from posted journals up to the selected date.</p>
            </div>
        </div>

        <x-reports.partials.filter-toolbar
            :action="route('reports.balance-sheet')"
            :export-url="route('reports.balance-sheet', request()->query() + ['export' => 'csv'])"
        >
            <label>
                <span>As of date</span>
                <input type="date" name="as_of_date" value="{{ $report['as_of_date'] }}">
            </label>
            <label>
                <span>Search account</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Code or account name">
            </label>
            <label class="hg-report-check">
                <input type="checkbox" name="include_zero_balances" value="1" @checked($report['include_zero_balances'])>
                <span>Show zero balances</span>
            </label>
        </x-reports.partials.filter-toolbar>

        <div class="hg-grid hg-grid-4 hg-report-summary">
            <section class="hg-card hg-metric"><span class="label">Total Assets</span><div class="value">{{ \App\Support\CompanyContext::money($report['assets']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Total Liabilities</span><div class="value">{{ \App\Support\CompanyContext::money($report['liabilities']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Equity + Profit</span><div class="value">{{ \App\Support\CompanyContext::money($report['equity'] + $report['retained_profit']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Difference</span><div class="value">{{ \App\Support\CompanyContext::money($report['difference']) }}</div><small class="hint">{{ $report['is_balanced'] ? 'Balanced' : 'Review setup/journals' }}</small></section>
        </div>

        <div class="hg-grid hg-grid-2 hg-report-sections">
            <section class="hg-card">
                <h2 class="hg-card-title">Assets</h2>
                @forelse($assetSections as $section => $rows)
                    <h3 class="hg-report-subtitle">{{ $section }}</h3>
                    @include('reports.partials.financial-rows', ['rows' => $rows, 'amountKey' => 'balance'])
                    <div class="hg-report-line"><span>Total {{ $section }}</span><strong>{{ \App\Support\CompanyContext::money($rows->sum('balance')) }}</strong></div>
                @empty
                    <div class="hg-empty">No asset records found.</div>
                @endforelse
                <div class="hg-report-total"><span>Total Assets</span><strong>{{ \App\Support\CompanyContext::money($report['assets']) }}</strong></div>
            </section>

            <section class="hg-card">
                <h2 class="hg-card-title">Liabilities & Equity</h2>

                <h3 class="hg-report-subtitle">Liabilities</h3>
                @forelse($liabilitySections as $section => $rows)
                    <h4 class="hg-report-subtitle">{{ $section }}</h4>
                    @include('reports.partials.financial-rows', ['rows' => $rows, 'amountKey' => 'balance'])
                    <div class="hg-report-line"><span>Total {{ $section }}</span><strong>{{ \App\Support\CompanyContext::money($rows->sum('balance')) }}</strong></div>
                @empty
                    <div class="hg-empty">No liability records found.</div>
                @endforelse
                <div class="hg-report-total"><span>Total Liabilities</span><strong>{{ \App\Support\CompanyContext::money($report['liabilities']) }}</strong></div>

                <h3 class="hg-report-subtitle">Equity</h3>
                @forelse($equitySections as $section => $rows)
                    <h4 class="hg-report-subtitle">{{ $section }}</h4>
                    @include('reports.partials.financial-rows', ['rows' => $rows, 'amountKey' => 'balance'])
                    <div class="hg-report-line"><span>Total {{ $section }}</span><strong>{{ \App\Support\CompanyContext::money($rows->sum('balance')) }}</strong></div>
                @empty
                    <div class="hg-empty">No equity records found.</div>
                @endforelse
                <div class="hg-report-line"><span>Retained Profit / Loss</span><strong>{{ \App\Support\CompanyContext::money($report['retained_profit']) }}</strong></div>
                <div class="hg-report-total"><span>Total Liabilities + Equity</span><strong>{{ \App\Support\CompanyContext::money($report['liabilities_and_equity']) }}</strong></div>
            </section>
        </div>
    </div>
</x-layouts::accounting>
