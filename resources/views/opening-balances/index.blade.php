@php
    $modalRecordId = (int) old('record_id', 0);
    $editingOpeningBalance = $modalRecordId > 0 ? $openingBalances->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'opening-balance' || $addOnlyMode;
    $canManage = auth()->user()?->canAccounting('opening_balances.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $defaultStatus = \App\Models\OpeningBalance::STATUS_POSTED;
@endphp

<x-layouts::accounting title="Opening Balances">
    <div class="hg-page-header">
        <div>
            <h1>Opening Balances</h1>
            <p class="hg-muted">Enter beginning cash, bank, receivable, payable, capital and other ledger balances separately from Party or Money Account setup.</p>
        </div>
        @if($canManage)
            <button
                type="button"
                class="hg-btn hg-btn-primary"
                data-setup-open="create"
                data-setup-target="opening-balance-modal"
                data-defaults="{{ json_encode([
                    'record_id' => '',
                    'financial_year_id' => $defaultFinancialYear?->id,
                    'balance_date' => $defaultBalanceDate,
                    'chart_of_account_id' => '',
                    'party_id' => '',
                    'money_account_id' => '',
                    'debit' => '0',
                    'credit' => '0',
                    'status' => $defaultStatus,
                    'reference' => '',
                    'note' => '',
                ]) }}"
            >+ Add Opening Balance</button>
        @endif
    </div>

    <div class="hg-card" style="margin-bottom: 16px;">
        <strong>How it works:</strong>
        <span class="hg-muted">Setup pages now create only master records. Opening values are entered here. Use Debit for assets/receivables/expenses and Credit for liabilities/capital/income.</span>
    </div>

    @if ($openingBalances->isEmpty())
        <div class="hg-empty">
            <div>{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No opening balances found.' }}</div>
            @if($canManage)
                <button
                    type="button"
                    class="hg-btn hg-btn-primary"
                    style="margin-top: 12px;"
                    data-setup-open="create"
                    data-setup-target="opening-balance-modal"
                    data-defaults="{{ json_encode([
                        'record_id' => '',
                        'financial_year_id' => $defaultFinancialYear?->id,
                        'balance_date' => $defaultBalanceDate,
                        'chart_of_account_id' => '',
                        'party_id' => '',
                        'money_account_id' => '',
                        'debit' => '0',
                        'credit' => '0',
                        'status' => $defaultStatus,
                        'reference' => '',
                        'note' => '',
                    ]) }}"
                >+ Add Opening Balance</button>
            @endif
        </div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Financial Year</th>
                    <th>Account</th>
                    <th>Party</th>
                    <th>Money Account</th>
                    <th class="right">Debit</th>
                    <th class="right">Credit</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($openingBalances as $openingBalance)
                    <tr>
                        <td>{{ $openingBalance->balance_date?->format('d M Y') ?: '—' }}<br><span class="hg-muted">{{ $openingBalance->reference ?: 'Opening' }}</span></td>
                        <td>{{ $openingBalance->financialYear?->name ?: '—' }}</td>
                        <td>{{ $openingBalance->chartOfAccount ? ($openingBalance->chartOfAccount->code.' — '.$openingBalance->chartOfAccount->name) : 'Relationship removed' }}</td>
                        <td>{{ $openingBalance->party ? ($openingBalance->party->code.' — '.$openingBalance->party->name) : '—' }}</td>
                        <td>{{ $openingBalance->moneyAccount?->name ?: '—' }}</td>
                        <td class="right">{{ \App\Support\CompanyContext::money((float) $openingBalance->debit) }}</td>
                        <td class="right">{{ \App\Support\CompanyContext::money((float) $openingBalance->credit) }}</td>
                        <td><span class="hg-badge {{ $openingBalance->status === \App\Models\OpeningBalance::STATUS_POSTED ? 'on' : 'draft' }}">{{ $statusOptions[$openingBalance->status] ?? ucfirst($openingBalance->status) }}</span></td>
                        <td>
                            <div class="hg-actions">
                                @if($canManage)
                                    <button
                                        type="button"
                                        class="hg-btn hg-btn-small"
                                        data-setup-open="edit"
                                        data-setup-target="opening-balance-modal"
                                        data-edit-title="Edit Opening Balance"
                                        data-update-url="{{ route('opening-balances.update', $openingBalance) }}"
                                        data-values="{{ json_encode([
                                            'record_id' => $openingBalance->id,
                                            'financial_year_id' => $openingBalance->financial_year_id,
                                            'balance_date' => $openingBalance->balance_date?->toDateString(),
                                            'chart_of_account_id' => $openingBalance->chart_of_account_id,
                                            'party_id' => $openingBalance->party_id,
                                            'money_account_id' => $openingBalance->money_account_id,
                                            'debit' => $openingBalance->debit,
                                            'credit' => $openingBalance->credit,
                                            'status' => $openingBalance->status,
                                            'reference' => $openingBalance->reference,
                                            'note' => $openingBalance->note,
                                        ]) }}"
                                    >Edit</button>
                                @endif
                                @if($canDelete)
                                    <form method="POST" action="{{ route('opening-balances.destroy', $openingBalance) }}" onsubmit="return confirm('Delete this opening balance row?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($canManage)
        <x-accounting.setup-modal
            id="opening-balance-modal"
            :show="$reopenModal"
            :title="$editingOpeningBalance ? 'Edit Opening Balance' : 'Add Opening Balance'"
            :store-url="route('opening-balances.store')"
            create-title="Add Opening Balance"
        >
            <form
                method="POST"
                action="{{ $editingOpeningBalance ? route('opening-balances.update', $editingOpeningBalance) : route('opening-balances.store') }}"
                class="hg-form-grid"
                data-setup-form
                data-draft-form
                data-draft-defer
                data-draft-key-base="opening-balances"
                data-draft-key="{{ $editingOpeningBalance ? 'opening-balances.edit.'.$editingOpeningBalance->id : 'opening-balances.create' }}"
                data-draft-title="Opening Balance"
            >
                @csrf
                <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingOpeningBalance)>
                <input type="hidden" name="setup_modal" value="opening-balance">
                <input type="hidden" name="record_id" value="{{ old('record_id') }}">

                <div class="hg-field">
                    <label for="opening-financial-year">Financial Year</label>
                    <select id="opening-financial-year" name="financial_year_id">
                        <option value="">None</option>
                        @foreach ($financialYears as $financialYear)
                            <option value="{{ $financialYear->id }}" @selected((string) old('financial_year_id', $editingOpeningBalance?->financial_year_id ?? $defaultFinancialYear?->id) === (string) $financialYear->id)>
                                {{ $financialYear->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('financial_year_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="opening-date">Opening Date <span class="hg-required">*</span></label>
                    <input id="opening-date" type="date" name="balance_date" value="{{ old('balance_date', $editingOpeningBalance?->balance_date?->toDateString() ?? $defaultBalanceDate) }}" required>
                    @error('balance_date')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full">
                    <label for="opening-account">COA Ledger <span class="hg-required">*</span></label>
                    <select id="opening-account" name="chart_of_account_id" required data-opening-account-select>
                        <option value="">Select ledger</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('chart_of_account_id', $editingOpeningBalance?->chart_of_account_id) === (string) $account->id)>
                                {{ $account->code }} — {{ $account->name }} ({{ $account->type }})
                            </option>
                        @endforeach
                    </select>
                    @error('chart_of_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="opening-party">Party / Sub-ledger</label>
                    <select id="opening-party" name="party_id">
                        <option value="">None</option>
                        @foreach ($parties as $party)
                            <option value="{{ $party->id }}" @selected((string) old('party_id', $editingOpeningBalance?->party_id) === (string) $party->id)>
                                {{ $party->code }} — {{ $party->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('party_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="opening-money-account">Money Account</label>
                    <select id="opening-money-account" name="money_account_id" data-opening-money-select>
                        <option value="">None</option>
                        @foreach ($moneyAccounts as $moneyAccount)
                            <option value="{{ $moneyAccount->id }}" data-account-id="{{ $moneyAccount->chart_of_account_id }}" @selected((string) old('money_account_id', $editingOpeningBalance?->money_account_id) === (string) $moneyAccount->id)>
                                {{ $moneyAccount->name }}{{ $moneyAccount->chartOfAccount ? ' — '.$moneyAccount->chartOfAccount->code : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('money_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="opening-debit">Debit Opening</label>
                    <input id="opening-debit" type="number" step="{{ \App\Support\CompanyContext::amountStep() }}" min="0" name="debit" value="{{ old('debit', $editingOpeningBalance?->debit ?? 0) }}">
                    @error('debit')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="opening-credit">Credit Opening</label>
                    <input id="opening-credit" type="number" step="{{ \App\Support\CompanyContext::amountStep() }}" min="0" name="credit" value="{{ old('credit', $editingOpeningBalance?->credit ?? 0) }}">
                    @error('credit')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="opening-status">Status</label>
                    <select id="opening-status" name="status" required>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $editingOpeningBalance?->status ?? $defaultStatus) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="opening-reference">Reference</label>
                    <input id="opening-reference" name="reference" value="{{ old('reference', $editingOpeningBalance?->reference) }}" maxlength="100">
                    @error('reference')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full">
                    <label for="opening-note">Note</label>
                    <textarea id="opening-note" name="note" rows="3">{{ old('note', $editingOpeningBalance?->note) }}</textarea>
                    @error('note')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full"><x-accounting.form-actions submit-label="Save Opening Balance" /></div>
            </form>
        </x-accounting.setup-modal>
    @endif

    @push('scripts')
        <script>
            document.addEventListener('change', function (event) {
                const moneySelect = event.target.closest('[data-opening-money-select]');
                if (!moneySelect) return;

                const selected = moneySelect.options[moneySelect.selectedIndex];
                const accountId = selected ? selected.getAttribute('data-account-id') : '';
                const form = moneySelect.closest('form');
                const accountSelect = form ? form.querySelector('[data-opening-account-select]') : null;

                if (accountId && accountSelect) {
                    accountSelect.value = accountId;
                    accountSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        </script>
    @endpush
</x-layouts::accounting>
