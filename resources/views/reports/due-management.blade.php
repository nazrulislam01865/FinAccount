<x-layouts::accounting title="Due Management">
    <div class="hg-report-page">
        <div class="hg-page-header hg-report-page-header">
            <div>
                <h1>Due Management</h1>
                <p>Collect customer dues and pay supplier dues without selecting accounting rules.</p>
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

        <div class="hg-grid hg-grid-3 hg-report-summary">
            <section class="hg-card hg-metric"><span class="label">Total Receivable</span><div class="value">{{ \App\Support\CompanyContext::money($report['total_receivable']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Total Payable</span><div class="value">{{ \App\Support\CompanyContext::money($report['total_payable']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Net Position</span><div class="value">{{ \App\Support\CompanyContext::money($report['total_receivable'] - $report['total_payable']) }}</div></section>
        </div>

        <section class="hg-card">
            <h2 class="hg-card-title">Outstanding Dues</h2>
            <p class="hg-report-note">Choose the amount and cash/bank/mobile account. The system posts the correct collection or supplier payment automatically.</p>

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
                                <th>Settle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['rows'] as $row)
                                <tr>
                                    <td><span class="hg-badge {{ strtolower($row['due_type']) }}">{{ $row['due_type'] }}</span></td>
                                    <td>
                                        <strong>{{ $row['party_code'] }}</strong> — {{ $row['party_name'] }}
                                        <br><span class="hg-muted">{{ $row['party_type'] }}</span>
                                    </td>
                                    <td>{{ $row['account_code'] }} — {{ $row['account_name'] }}</td>
                                    <td class="right"><strong>{{ \App\Support\CompanyContext::money($row['closing_balance']) }}</strong></td>
                                    <td>
                                        @if((float) $row['closing_balance'] <= 0)
                                            <span class="hg-muted">No settlement needed</span>
                                        @elseif($moneyAccounts->isEmpty())
                                            <span class="hg-muted">Create an active money account first.</span>
                                        @else
                                            <form method="POST" action="{{ route('reports.due-management.settle') }}" class="hg-due-settle-form">
                                                @csrf
                                                <input type="hidden" name="as_of_date" value="{{ $report['as_of_date'] }}">
                                                <input type="hidden" name="party_id" value="{{ $row['party_id'] }}">
                                                <input type="hidden" name="account_id" value="{{ $row['account_id'] }}">
                                                <input type="hidden" name="due_type" value="{{ $row['due_type'] }}">

                                                <div class="hg-due-settle-grid">
                                                    <label>
                                                        <span>Date</span>
                                                        <input type="date" name="transaction_date" value="{{ old('transaction_date', $report['as_of_date']) }}" required>
                                                    </label>
                                                    <label>
                                                        <span>Amount</span>
                                                        <input type="number" name="amount" value="{{ old('amount', number_format((float) $row['closing_balance'], 2, '.', '')) }}" min="0.01" step="0.01" max="{{ number_format((float) $row['closing_balance'], 2, '.', '') }}" required>
                                                    </label>
                                                    <label>
                                                        <span>{{ $row['due_type'] === 'Receivable' ? 'Received In' : 'Paid From' }}</span>
                                                        <select name="money_account_id" required>
                                                            <option value="">Select cash, bank or mobile account</option>
                                                            @foreach($moneyAccounts as $moneyAccount)
                                                                <option value="{{ $moneyAccount->id }}" @selected((int) old('money_account_id') === (int) $moneyAccount->id)>
                                                                    {{ $moneyAccount->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label>
                                                        <span>Reference</span>
                                                        <input type="text" name="reference" value="{{ old('reference') }}" placeholder="Optional">
                                                    </label>
                                                    <label>
                                                        <span>Description</span>
                                                        <input type="text" name="description" value="{{ old('description') }}" placeholder="Optional note">
                                                    </label>
                                                </div>
                                                <button type="submit" class="hg-btn hg-btn-primary hg-due-settle-button">{{ $row['due_type'] === 'Receivable' ? 'Collect Payment' : 'Pay Supplier' }}</button>
                                            </form>
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
