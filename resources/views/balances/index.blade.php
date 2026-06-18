<x-layouts::accounting title="Balances">
    <div class="hg-page-header">
        <div>
            <h1>Balances</h1>
        </div>
    </div>

    <div class="hg-grid hg-grid-2">
        <section class="hg-card" id="account-balances">
            <h2 class="hg-card-title">Account Balances</h2>
            @if ($accounts->isEmpty())
                <div class="hg-empty">No records found.</div>
            @else
                <div class="hg-table-wrap">
                    <table class="hg-table">
                        <thead><tr><th>Account</th><th>Type</th><th class="right">Balance</th></tr></thead>
                        <tbody>
                        @foreach ($accounts as $account)
                            <tr>
                                <td>{{ $account->code }} — {{ $account->name }}</td>
                                <td><span class="hg-badge {{ strtolower($account->type) }}">{{ $account->type }}</span></td>
                                <td class="right">{{ \App\Support\CompanyContext::money($accountBalances[$account->id] ?? 0) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="hg-card" id="party-balances">
            <h2 class="hg-card-title">Party Balances</h2>
            @if ($parties->isEmpty())
                <div class="hg-empty">No records found.</div>
            @else
                <div class="hg-table-wrap">
                    <table class="hg-table">
                        <thead><tr><th>Party</th><th>Type</th><th class="right">Balance</th></tr></thead>
                        <tbody>
                        @foreach ($parties as $party)
                            <tr>
                                <td>{{ $party->code }} — {{ $party->name }}</td>
                                <td><span class="hg-badge">{{ $partyTypeLabels[$party->type] ?? $party->type }}</span></td>
                                <td class="right">{{ \App\Support\CompanyContext::money($partyBalances[$party->id] ?? 0) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts::accounting>
