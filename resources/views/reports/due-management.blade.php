<x-layouts::accounting title="Due Management">
    <div class="hg-report-page">
        <div class="hg-page-header hg-report-page-header">
            <div>
                <h1>Due Management</h1>
                <p class="hg-muted">Review outstanding customer receivables and supplier payables, then settle them from the transaction entry page.</p>
            </div>
        </div>

        <x-reports.partials.filter-toolbar
            :action="route('reports.due-management')"
            :export-url="route('reports.due-report', request()->query() + ['export' => 'csv'])"
        >
            <label>
                <span>As of date</span>
                <input type="date" name="as_of_date" value="{{ $report['as_of_date'] }}">
            </label>
            <label>
                <span>Due type</span>
                <select name="due_type">
                    <option value="all" @selected($report['due_type'] === 'all')>All dues</option>
                    <option value="receivable" @selected($report['due_type'] === 'receivable')>Receivable only</option>
                    <option value="payable" @selected($report['due_type'] === 'payable')>Payable only</option>
                </select>
            </label>
            <label>
                <span>Search party</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Party code, name or type">
            </label>
            <label class="hg-report-check">
                <input type="checkbox" name="include_zero_balances" value="1" @checked($report['include_zero_balances'])>
                <span>Show zero balances</span>
            </label>
        </x-reports.partials.filter-toolbar>

        @if ($errors->any())
            <div class="hg-alert hg-alert-danger">
                {{ $errors->first() }}
            </div>
        @endif

        @if(session('receipt_download_url'))
            <div class="hg-notice hg-notice-success">
                Receipt download should start automatically.
                @if(session('receipt_show_url'))
                    <a href="{{ session('receipt_show_url') }}">Open receipt</a>
                @endif
            </div>
            <iframe src="{{ session('receipt_download_url') }}" style="display:none" title="Receipt download"></iframe>
        @endif

        <div class="hg-grid hg-grid-3 hg-report-summary">
            <section class="hg-card hg-metric"><span class="label">Total Receivable</span><div class="value">{{ \App\Support\CompanyContext::money($report['total_receivable']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Total Payable</span><div class="value">{{ \App\Support\CompanyContext::money($report['total_payable']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Net Position</span><div class="value">{{ \App\Support\CompanyContext::money($report['total_receivable'] - $report['total_payable']) }}</div></section>
        </div>

        <section class="hg-card">
            <div class="hg-card-header-row">
                <div>
                    <h2 class="hg-card-title">Outstanding Dues</h2>
                    <p class="hg-report-note">Click Settlement to open Transaction Entry with party, ledger, transaction head, and due amount prefilled. The user only needs to enter the settlement amount and cash/bank/mobile account.</p>
                </div>
            </div>

            @if($report['rows']->isEmpty())
                <div class="hg-empty">No due records found.</div>
            @else
                <div class="hg-table-wrap">
                    <table class="hg-table hg-report-table hg-due-management-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Party</th>
                                <th>Account</th>
                                <th class="right">Closing Due</th>
                                <th class="right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['rows'] as $row)
                                @php
                                    $settlementCategory = $row['due_type'] === 'Receivable'
                                        ? \App\Support\TransactionTypes::CUSTOMER_COLLECTION
                                        : \App\Support\TransactionTypes::SUPPLIER_PAYMENT;
                                    $settlementUrl = route('transactions.create', [
                                        'category' => $settlementCategory,
                                        'due_settlement' => 1,
                                        'due_type' => strtolower($row['due_type']),
                                        'party_id' => $row['party_id'],
                                        'account_id' => $row['account_id'],
                                        'as_of_date' => $report['as_of_date'],
                                    ]);
                                @endphp
                                <tr>
                                    <td><span class="hg-badge {{ strtolower($row['due_type']) }}">{{ $row['due_type'] }}</span></td>
                                    <td>
                                        <strong>{{ $row['party_code'] }}</strong> — {{ $row['party_name'] }}
                                        <br><span class="hg-muted">{{ $row['party_type'] }}</span>
                                    </td>
                                    <td>{{ $row['account_code'] }} — {{ $row['account_name'] }}</td>
                                    <td class="right"><strong>{{ \App\Support\CompanyContext::money($row['closing_balance']) }}</strong></td>
                                    <td class="right">
                                        @if((float) $row['closing_balance'] <= 0)
                                            <span class="hg-muted">Settled</span>
                                        @else
                                            <a href="{{ $settlementUrl }}" class="hg-btn hg-btn-small hg-btn-primary">
                                                {{ $row['due_type'] === 'Receivable' ? 'Settlement / Receive' : 'Settlement / Pay' }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts::accounting>
