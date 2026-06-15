<x-layouts::accounting title="Transaction Register">
    <div class="hg-page-header">
        <div>
            <h1>Transaction Register</h1>
            <p>All posted sales, payments and liability transactions. Edit recalculates journals. Delete removes the transaction and its derived journal lines.</p>
        </div>
        <div class="hg-actions">
            <a
                class="hg-btn"
                href="{{ route('transactions.export', array_filter(['search' => $search, 'category' => $category])) }}"
            >Export CSV</a>
        </div>
    </div>

    <form method="GET" action="{{ route('transactions.index') }}" class="hg-toolbar" id="transaction-filter-form">
        <input
            class="hg-search"
            type="search"
            name="search"
            value="{{ $search }}"
            placeholder="Search voucher, head, party or description..."
            aria-label="Search transactions"
        >

        <select name="category" class="hg-filter-select" aria-label="Filter transaction category" onchange="this.form.submit()">
            <option value="">All categories</option>
            @foreach (['Sales', 'Payment', 'Liability'] as $option)
                <option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </form>

    @if ($transactions->isEmpty())
        <div class="hg-empty">No records found.</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                    <tr>
                        <th>Voucher</th>
                        <th>Type</th>
                        <th>Head</th>
                        <th>Money</th>
                        <th>Party</th>
                        <th>Ref</th>
                        <th class="right">Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td>
                                <strong>{{ $transaction->voucher_no }}</strong><br>
                                <span class="hg-muted">{{ $transaction->transaction_date->format('Y-m-d') }}</span>
                            </td>
                            <td>
                                <span class="hg-badge {{ strtolower($transaction->category) }}">
                                    {{ $transaction->category }}
                                </span>
                            </td>
                            <td>{{ $transaction->transactionHead?->name ?? '-' }}</td>
                            <td>{{ $transaction->moneyAccount?->name ?? '-' }}</td>
                            <td>{{ $transaction->party?->name ?? '-' }}</td>
                            <td>{{ $transaction->reference ?: '-' }}</td>
                            <td class="right">৳ {{ number_format((float) $transaction->amount, 2) }}</td>
                            <td>
                                <div class="hg-actions">
                                    <a class="hg-btn hg-btn-small" href="{{ route('transactions.edit', $transaction) }}">Edit</a>

                                    <form
                                        method="POST"
                                        action="{{ route('transactions.destroy', $transaction) }}"
                                        onsubmit="return confirm('Delete {{ $transaction->voucher_no }}? Related journal lines will be removed.')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts::accounting>
