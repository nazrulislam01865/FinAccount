@php
    $draftRows = \App\Support\VisibleFormDrafts::forBase('transactions');
    $canManageTransactions = auth()->user()?->canAccounting('transactions.manage') ?? false;
@endphp

<x-layouts::accounting title="Transaction Register">
    <div class="hg-page-header">
        <div>
            <h1>Transaction Register</h1>
        </div>
        <div class="hg-actions">
            <a class="hg-btn" href="{{ route('transactions.export', array_filter(['search' => $search, 'category' => $category])) }}">Export CSV</a>
            @if($canManageTransactions)
                <a class="hg-btn hg-btn-primary" href="{{ route('transactions.create') }}">+ Add Transaction</a>
            @endif
        </div>
    </div>



    @if(session('invoice_download_url'))
        <div class="hg-notice hg-notice-success">
            Invoice download should start automatically.
            @if(session('invoice_show_url'))
                <a href="{{ session('invoice_show_url') }}">Open invoice</a>
            @endif
        </div>
        <iframe src="{{ session('invoice_download_url') }}" style="display:none" title="Invoice download"></iframe>
    @endif

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

    @if ($transactions->isEmpty() && $draftRows->isEmpty())
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
                        <th>Attachment</th>
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
                                <br><small class="hg-muted">{{ $settlementLabels[$transaction->settlement_type] ?? $transaction->settlement_type }}</small>
                            </td>
                            <td>{{ $transaction->transactionHead?->name ?? '-' }}</td>
                            <td>{{ $transaction->moneyAccount?->name ?? '-' }}</td>
                            <td>{{ $transaction->party?->name ?? '-' }}</td>
                            <td>{{ $transaction->reference ?: '-' }}</td>
                            <td>
                                @if($transaction->attachments->isNotEmpty())
                                    <div class="hg-attachment-list compact">
                                        @foreach($transaction->attachments as $attachment)
                                            <a class="hg-attachment-link" href="{{ route('transactions.attachments.show', [$transaction, $attachment]) }}" target="_blank" rel="noopener">
                                                {{ $attachment->is_image ? 'Image' : 'File' }} {{ $loop->iteration }}
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="hg-muted">-</span>
                                @endif
                            </td>
                            <td class="right">
                                {{ \App\Support\CompanyContext::money((float) $transaction->amount) }}
                                @if(($transaction->settlement_type ?? \App\Support\TransactionTypes::CASH) === \App\Support\TransactionTypes::PARTIAL)
                                    <br>
                                    <small class="hg-muted">
                                        Paid: {{ \App\Support\CompanyContext::money((float) $transaction->paid_amount) }}<br>
                                        Initial due: {{ \App\Support\CompanyContext::money((float) $transaction->due_amount) }}
                                        @if($transaction->due_date)<br>Due date: {{ $transaction->due_date->format('Y-m-d') }}@endif
                                    </small>
                                @endif
                            </td>
                            <td>
                                <span class="hg-badge {{ $transaction->status === 'posted' ? 'on' : 'incomplete' }}">{{ ucfirst($transaction->status) }}</span>
                                @if($transaction->salesInvoice)
                                    <br><small class="hg-muted">Invoice: {{ ucfirst($transaction->salesInvoice->status) }}</small>
                                @endif
                            </td>
                            <td>
                                <div class="hg-actions">
                                    @if($transaction->salesInvoice)
                                        <a class="hg-btn hg-btn-small hg-btn-soft" href="{{ route('sales-invoices.show', $transaction->salesInvoice) }}">Invoice</a>
                                        <a class="hg-btn hg-btn-small" href="{{ route('sales-invoices.download', $transaction->salesInvoice) }}">Download</a>
                                    @elseif($canManageTransactions && $transaction->category === \App\Support\TransactionTypes::SALE)
                                        <form method="POST" action="{{ route('transactions.invoice.generate', $transaction) }}">
                                            @csrf
                                            <button class="hg-btn hg-btn-small hg-btn-soft" type="submit">Generate Invoice</button>
                                        </form>
                                    @endif
                                    @if($canManageTransactions)
                                        <a class="hg-btn hg-btn-small" data-draft-edit-key="transactions.edit.{{ $transaction->id }}" href="{{ route('transactions.edit', $transaction) }}">Edit</a>
                                    @endif
                                    @if($canManageTransactions && auth()->user()?->canDeleteAccountingRecords())
                                        <form method="POST" action="{{ route('transactions.destroy', $transaction) }}" data-safe-delete-form>
                                            @csrf
                                            @method('DELETE')
                                            <button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button>
                                        </form>
                                    @endif
                                    @if(! $canManageTransactions)<span class="hg-muted">View only</span>@endif
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    @foreach ($draftRows as $draft)
                        @php
                            $fields = \App\Support\VisibleFormDrafts::fields($draft);
                            $isEditDraft = \App\Support\VisibleFormDrafts::isEdit($draft);
                            $draftRecordId = \App\Support\VisibleFormDrafts::recordId($draft);
                            $draftCategory = \App\Support\VisibleFormDrafts::transactionCategory($draft);
                        @endphp
                        <tr class="hg-table-draft-row">
                            <td><strong>Draft</strong><br><span class="hg-muted">{{ $fields['transaction_date'] ?? 'No date selected' }}</span></td>
                            <td><span class="hg-badge {{ strtolower($draftCategory) }}">{{ $categoryLabels[$draftCategory] ?? ($draftCategory ?: 'Draft') }}</span></td>
                            <td>{{ filled($fields['transaction_head_id'] ?? null) ? 'Head ID #'.$fields['transaction_head_id'] : 'Not selected' }}</td>
                            <td>{{ filled($fields['money_account_id'] ?? null) ? 'Money ID #'.$fields['money_account_id'] : '-' }}</td>
                            <td>{{ filled($fields['party_id'] ?? null) ? 'Party ID #'.$fields['party_id'] : '-' }}</td>
                            <td>{{ $fields['reference'] ?? '-' }}</td>
                            <td><span class="hg-muted">Attach after final save</span></td>
                            <td class="right">
                                {{ \App\Support\CompanyContext::money((float) ($fields['amount'] ?? 0)) }}
                                @if(($fields['settlement_type'] ?? \App\Support\TransactionTypes::CASH) === \App\Support\TransactionTypes::PARTIAL)
                                    <br><small class="hg-muted">Partial draft</small>
                                @endif
                            </td>
                            <td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
                            <td>
                                <div class="hg-actions">
                                    @if($canManageTransactions)
                                        @if($isEditDraft && $draftRecordId)
                                            <a class="hg-btn hg-btn-small" href="{{ route('transactions.edit', ['transaction' => $draftRecordId]) }}">Continue</a>
                                        @else
                                            <a class="hg-btn hg-btn-small" href="{{ route('transactions.create', array_filter(['category' => $draftCategory])) }}">Continue</a>
                                        @endif
                                    @endif
                                    <form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button>
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
