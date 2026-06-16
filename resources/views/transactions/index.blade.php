<x-layouts::accounting title="Transaction Register">
    <div class="hg-page-header">
        <div>
            <h1>Transaction Register</h1>
            <p>Posted and incomplete transactions. Incomplete records were detached by safe deletion and must be edited to restore valid dependencies.</p>
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
            @foreach ($transactionCategories as $categoryOption)
                <option value="{{ $categoryOption->value }}" @selected($category === $categoryOption->value)>{{ $categoryOption->label }}</option>
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
                        <th>Status</th>
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
                                <span class="hg-badge {{ strtolower($transaction->category ?? '') }}">
                                    {{ $transaction->category ? ($categoryLabels[$transaction->category] ?? $transaction->category) : 'Relationship removed' }}
                                </span>
                            </td>
                            <td>{{ $transaction->transactionHead?->name ?? '-' }}</td>
                            <td>{{ $transaction->moneyAccount?->name ?? '-' }}</td>
                            <td>{{ $transaction->party?->name ?? '-' }}</td>
                            <td>{{ $transaction->reference ?: '-' }}</td>
                            <td class="right">৳ {{ number_format((float) $transaction->amount, 2) }}</td>
                            <td><span class="hg-badge {{ $transaction->status === 'posted' ? 'on' : 'incomplete' }}">{{ ucfirst($transaction->status) }}</span></td>
                            <td>
                                <div class="hg-actions">
                                    <a class="hg-btn hg-btn-small" href="{{ route('transactions.edit', $transaction) }}">Edit</a>

                                    <form
                                        method="POST"
                                        action="{{ route('transactions.destroy', $transaction) }}"
                                     data-safe-delete-form>
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
