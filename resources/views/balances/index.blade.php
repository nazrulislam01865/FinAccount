<x-layouts::accounting title="Balances">
    <div class="hg-page-header">
        <div>
            <h1>Balances</h1>
            <p>Click an account, party, or balance amount to open its complete transaction history.</p>
        </div>
    </div>

    <div class="hg-grid hg-balance-stack">
        <section class="hg-card" id="account-balances">
            <div class="hg-balance-section-header">
                <div>
                    <h2 class="hg-card-title">Account Balances</h2>
                    <p class="hg-muted">Account names and amounts open the corresponding ledger report.</p>
                </div>
            </div>

            @if ($accounts->isEmpty())
                <div class="hg-empty">No records found.</div>
            @else
                <div class="hg-table-wrap">
                    <table class="hg-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Type</th>
                                <th class="right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($accounts as $account)
                            @php
                                $accountTransactionsUrl = route('reports.ledger-report', [
                                    'account_id' => $account->id,
                                    'from_date' => '1900-01-01',
                                    'to_date' => now()->toDateString(),
                                ]);
                            @endphp
                            <tr>
                                <td>
                                    <a class="hg-balance-link" href="{{ $accountTransactionsUrl }}">
                                        {{ $account->code }} — {{ $account->name }}
                                    </a>
                                </td>
                                <td><span class="hg-badge {{ strtolower($account->type) }}">{{ $account->type }}</span></td>
                                <td class="right">
                                    <a class="hg-balance-link hg-balance-amount" href="{{ $accountTransactionsUrl }}">
                                        {{ \App\Support\CompanyContext::money($accountBalances[$account->id] ?? 0) }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="hg-card" id="party-balances">
            <div class="hg-balance-section-header">
                <div>
                    <h2 class="hg-card-title">Party Balances</h2>
                    <p class="hg-muted">Search by party code, name, type, phone, or email.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('balances.index') }}#party-balances" class="hg-toolbar hg-balance-party-search">
                <input type="hidden" name="section" value="parties">
                <input
                    class="hg-search"
                    type="search"
                    name="party_search"
                    value="{{ $partySearch }}"
                    placeholder="Search party by code, name, type, phone or email..."
                    aria-label="Search party balances"
                >
                <button class="hg-btn hg-btn-primary" type="submit">Search</button>
                @if($partySearch !== '')
                    <a class="hg-btn" href="{{ route('balances.index', ['section' => 'parties']) }}#party-balances">Clear</a>
                @endif
            </form>

            @if ($parties->isEmpty())
                <div class="hg-empty">{{ $partySearch !== '' ? 'No matching party found.' : 'No records found.' }}</div>
            @else
                <div class="hg-table-wrap">
                    <table class="hg-table">
                        <thead>
                            <tr>
                                <th>Party</th>
                                <th>Type</th>
                                <th class="right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($parties as $party)
                            @php
                                $partyTransactionsUrl = route('transactions.index', ['party_id' => $party->id]);
                            @endphp
                            <tr>
                                <td>
                                    <a class="hg-balance-link" href="{{ $partyTransactionsUrl }}">
                                        {{ $party->code }} — {{ $party->name }}
                                    </a>
                                </td>
                                <td><span class="hg-badge">{{ $partyTypeLabels[$party->type] ?? $party->type }}</span></td>
                                <td class="right">
                                    <a class="hg-balance-link hg-balance-amount" href="{{ $partyTransactionsUrl }}">
                                        {{ \App\Support\CompanyContext::money($partyBalances[$party->id] ?? 0) }}
                                    </a>
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
