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
                    'opening_amount' => '',
                    'balance_side' => '',
                    'status' => $defaultStatus,
                    'reference' => '',
                    'note' => '',
                ]) }}"
            >+ Add Opening Balance</button>
        @endif
    </div>

    <div class="hg-card" style="margin-bottom: 16px;">
        <strong>How it works:</strong>
        <span class="hg-muted">Select a ledger and enter one opening amount. The balance side is auto-selected from the ledger, and Party or Money Account appears only when that ledger needs it.</span>
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
                        'opening_amount' => '',
                        'balance_side' => '',
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
                                            'opening_amount' => ((float) $openingBalance->debit > 0 ? $openingBalance->debit : $openingBalance->credit),
                                            'balance_side' => ((float) $openingBalance->credit > 0 ? 'credit' : 'debit'),
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
                class="hg-opening-form"
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

                @php
                    $initialDebit = (float) old('debit', $editingOpeningBalance?->debit ?? 0);
                    $initialCredit = (float) old('credit', $editingOpeningBalance?->credit ?? 0);
                    $initialAmount = old('opening_amount', $initialDebit > 0 ? $initialDebit : ($initialCredit > 0 ? $initialCredit : ''));
                    $initialSide = old('balance_side', $initialCredit > 0 ? 'credit' : ($initialDebit > 0 ? 'debit' : ''));
                    $initialStatus = old('status', $editingOpeningBalance?->status ?? $defaultStatus);
                @endphp

                <input type="hidden" name="status" value="{{ $initialStatus ?: $defaultStatus }}" data-opening-status>
                <input type="hidden" name="debit" value="{{ old('debit', $editingOpeningBalance?->debit ?? 0) }}" data-opening-debit>
                <input type="hidden" name="credit" value="{{ old('credit', $editingOpeningBalance?->credit ?? 0) }}" data-opening-credit>

                <div class="hg-opening-card hg-opening-card-compact">
                    <div class="hg-field">
                        <label for="opening-financial-year">Financial Year</label>
                    <select id="opening-financial-year" name="financial_year_id">
                        <option value="">None</option>
                        @foreach ($financialYears as $financialYear)
                            <option
                                value="{{ $financialYear->id }}"
                                data-start-date="{{ $financialYear->start_date?->toDateString() }}"
                                @selected((string) old('financial_year_id', $editingOpeningBalance?->financial_year_id ?? $defaultFinancialYear?->id) === (string) $financialYear->id)
                            >
                                {{ $financialYear->name }}
                            </option>
                        @endforeach
                    </select>
                        @error('financial_year_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>
                </div>

                <div class="hg-opening-card hg-opening-card-main">
                    <div class="hg-field full">
                    <label for="opening-account">COA Ledger <span class="hg-required">*</span></label>
                    <select id="opening-account" name="chart_of_account_id" required data-opening-account-select>
                        <option value="">Select ledger</option>
                        @foreach ($accounts as $account)
                            @php
                                $normalSide = $account->normal_balance === 'Credit' ? 'credit' : 'debit';
                                $isPartyControl = (bool) ($account->is_party_control ?? false);
                                $isCashBank = (bool) ($account->is_cash_bank ?? false);
                            @endphp
                            <option
                                value="{{ $account->id }}"
                                data-normal-balance="{{ $account->normal_balance }}"
                                data-default-side="{{ $normalSide }}"
                                data-is-party-control="{{ $isPartyControl ? '1' : '0' }}"
                                data-is-cash-bank="{{ $isCashBank ? '1' : '0' }}"
                                @selected((string) old('chart_of_account_id', $editingOpeningBalance?->chart_of_account_id) === (string) $account->id)
                            >
                                {{ $account->code }} — {{ $account->name }} ({{ $account->type }})
                            </option>
                        @endforeach
                    </select>
                    @error('chart_of_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                        <small class="hg-field-help" data-opening-account-help>Party or Money Account will appear only when required.</small>
                    </div>

                    <div class="hg-opening-amount-row">
                        <div class="hg-field">
                    <label for="opening-amount">Opening Balance <span class="hg-required">*</span></label>
                    <input
                        id="opening-amount"
                        type="number"
                        step="{{ \App\Support\CompanyContext::amountStep() }}"
                        min="0"
                        name="opening_amount"
                        value="{{ $initialAmount }}"
                        placeholder="0.00"
                        required
                        data-opening-amount
                    >
                    @error('opening_amount')<small class="hg-field-error">{{ $message }}</small>@enderror
                    @error('debit')<small class="hg-field-error">{{ $message }}</small>@enderror
                        @error('credit')<small class="hg-field-error">{{ $message }}</small>@enderror
                        </div>

                        <div class="hg-field">
                            <label for="opening-balance-side">Balance Side <span class="hg-required">*</span></label>
                            <select id="opening-balance-side" name="balance_side" required data-opening-side>
                                <option value="debit" @selected($initialSide !== 'credit')>Debit</option>
                                <option value="credit" @selected($initialSide === 'credit')>Credit</option>
                            </select>
                            @error('balance_side')<small class="hg-field-error">{{ $message }}</small>@enderror
                            <small class="hg-field-help" data-opening-side-help>Auto-selected from the selected ledger.</small>
                        </div>
                    </div>

                    <div class="hg-opening-conditional-grid">
                        <div class="hg-field" data-opening-party-field hidden>
                    <label for="opening-party">Party / Sub-ledger</label>
                    <select id="opening-party" name="party_id" data-opening-party-select disabled>
                        <option value="">None</option>
                        @foreach ($parties as $party)
                            <option value="{{ $party->id }}" @selected((string) old('party_id', $editingOpeningBalance?->party_id) === (string) $party->id)>
                                {{ $party->code }} — {{ $party->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('party_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                            <small class="hg-field-help">Shown only for receivable/payable party-control ledgers.</small>
                        </div>

                        <div class="hg-field" data-opening-money-field hidden>
                    <label for="opening-money-account">Money Account</label>
                    <select id="opening-money-account" name="money_account_id" data-opening-money-select disabled>
                        <option value="">None</option>
                        @foreach ($moneyAccounts as $moneyAccount)
                            <option
                                value="{{ $moneyAccount->id }}"
                                data-account-id="{{ $moneyAccount->chart_of_account_id }}"
                                @selected((string) old('money_account_id', $editingOpeningBalance?->money_account_id) === (string) $moneyAccount->id)
                            >
                                {{ $moneyAccount->name }}{{ $moneyAccount->chartOfAccount ? ' — '.$moneyAccount->chartOfAccount->code : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('money_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                            <small class="hg-field-help">Shown only for cash/bank ledgers.</small>
                        </div>
                    </div>
                </div>

                <div class="hg-opening-card hg-opening-more-card">
                    <details class="hg-opening-more-details" {{ ($errors->has('balance_date') || $errors->has('reference') || $errors->has('note')) ? 'open' : '' }}>
                        <summary>More Details</summary>
                        <div class="hg-form-grid hg-opening-more-grid">
                            <div class="hg-field">
                                <label for="opening-date">Opening Date <span class="hg-required">*</span></label>
                                <input id="opening-date" type="date" name="balance_date" value="{{ old('balance_date', $editingOpeningBalance?->balance_date?->toDateString() ?? $defaultBalanceDate) }}" required data-opening-date>
                                @error('balance_date')<small class="hg-field-error">{{ $message }}</small>@enderror
                                <small class="hg-field-help">Defaults to the financial year start date.</small>
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
                        </div>
                    </details>
                </div>

                <div class="hg-opening-actions-card">
                    <div class="hg-form-actions">
                        <div class="hg-actions">
                            <button type="button" class="hg-btn hg-btn-draft" data-draft-save>Save Draft</button>
                            <button type="submit" class="hg-btn hg-btn-primary" data-opening-post-button>Post Opening Balance</button>
                        </div>
                        <div class="hg-draft-feedback" data-draft-feedback role="status" aria-live="polite" hidden>
                            <span data-draft-message></span>
                            <button type="button" class="hg-draft-discard" data-draft-discard hidden>Discard draft</button>
                        </div>
                    </div>
                </div>
            </form>
        </x-accounting.setup-modal>
    @endif

    @push('styles')
        <style>
            #opening-balance-modal .hg-modal-box {
                width: min(780px, calc(100vw - 28px));
                border-radius: 22px;
            }

            #opening-balance-modal .hg-modal-head {
                padding: 18px 22px;
            }

            #opening-balance-modal .hg-modal-body {
                padding: 18px 22px 22px;
                background: #f8fafc;
            }

            .hg-opening-form {
                display: flex;
                flex-direction: column;
                gap: 14px;
                max-width: 720px;
                margin: 0 auto;
            }

            .hg-opening-form [hidden] {
                display: none !important;
            }

            .hg-opening-card,
            .hg-opening-actions-card {
                border: 1px solid #e5eaf3;
                border-radius: 18px;
                background: #fff;
                box-shadow: 0 10px 26px rgba(15, 23, 42, .04);
            }

            .hg-opening-card {
                padding: 16px;
            }

            .hg-opening-card-compact {
                display: grid;
                grid-template-columns: minmax(220px, 320px);
                padding-bottom: 14px;
            }

            .hg-opening-card-main {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .hg-opening-form .hg-field label {
                margin-bottom: 7px;
                font-size: 13px;
                color: #344054;
                letter-spacing: -.01em;
            }

            .hg-opening-form .hg-field input:not([type="radio"]),
            .hg-opening-form .hg-field select,
            .hg-opening-form .hg-field textarea {
                min-height: 46px;
                border-radius: 13px;
                border-color: #dfe5ef;
                background: #fff;
                font-size: 14px;
            }

            .hg-opening-form .hg-field-help {
                margin-top: 6px;
                font-size: 12px;
                color: #667085;
            }

            .hg-opening-amount-row {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 250px;
                gap: 14px;
                align-items: start;
            }

            #opening-balance-side {
                cursor: pointer;
            }

            .hg-opening-conditional-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 14px;
            }

            .hg-opening-more-card {
                padding: 0;
                overflow: hidden;
            }

            .hg-opening-more-details {
                border: 0;
                background: transparent;
            }

            .hg-opening-more-details summary {
                cursor: pointer;
                padding: 14px 16px;
                font-size: 14px;
                font-weight: 800;
                color: #344054;
                list-style: none;
            }

            .hg-opening-more-details summary::-webkit-details-marker {
                display: none;
            }

            .hg-opening-more-details summary::before {
                content: '+';
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 22px;
                height: 22px;
                margin-right: 8px;
                border-radius: 999px;
                background: #eef4ff;
                color: #2563eb;
                font-weight: 900;
            }

            .hg-opening-more-details[open] summary::before {
                content: '−';
            }

            .hg-opening-more-grid {
                padding: 0 16px 16px;
            }

            .hg-opening-actions-card {
                padding: 14px 16px;
            }

            .hg-opening-actions-card .hg-form-actions {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }

            .hg-opening-actions-card .hg-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }

            .hg-opening-actions-card .hg-btn {
                min-height: 44px;
                border-radius: 13px;
                padding-inline: 18px;
                font-weight: 800;
            }

            @media (max-width: 760px) {
                #opening-balance-modal .hg-modal-body {
                    padding: 14px;
                }

                .hg-opening-card,
                .hg-opening-actions-card {
                    border-radius: 16px;
                }

                .hg-opening-amount-row,
                .hg-opening-conditional-grid,
                .hg-opening-card-compact,
                .hg-opening-more-grid {
                    grid-template-columns: 1fr;
                }

                .hg-opening-actions-card .hg-form-actions,
                .hg-opening-actions-card .hg-actions,
                .hg-opening-actions-card .hg-btn {
                    width: 100%;
                }

                .hg-opening-actions-card .hg-btn {
                    justify-content: center;
                }
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function () {
                function selectedOption(select) {
                    return select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
                }

                function selectedSide(form) {
                    return form.querySelector('[name="balance_side"]')?.value || 'debit';
                }

                function setSide(form, side) {
                    const value = side === 'credit' ? 'credit' : 'debit';
                    const input = form.querySelector('[name="balance_side"]');
                    if (input) input.value = value;
                }

                function syncDebitCredit(form) {
                    const amountField = form.querySelector('[data-opening-amount]');
                    const debitField = form.querySelector('[data-opening-debit]');
                    const creditField = form.querySelector('[data-opening-credit]');
                    if (!amountField || !debitField || !creditField) return;

                    const amount = Math.max(0, parseFloat(String(amountField.value || '0').replace(/,/g, '')) || 0);
                    const side = selectedSide(form);

                    debitField.value = side === 'debit' ? amount : 0;
                    creditField.value = side === 'credit' ? amount : 0;
                }

                function updateOpeningForm(form, options = {}) {
                    const accountSelect = form.querySelector('[data-opening-account-select]');
                    const option = selectedOption(accountSelect);
                    const hasAccount = !!(option && option.value);
                    const defaultSide = option?.getAttribute('data-default-side') || '';
                    const isPartyControl = option?.getAttribute('data-is-party-control') === '1';
                    const isCashBank = option?.getAttribute('data-is-cash-bank') === '1';

                    if (hasAccount && defaultSide && options.applyDefaultSide !== false) {
                        const currentAmount = form.querySelector('[data-opening-amount]')?.value || '';
                        const hasExistingAmount = currentAmount !== '' && parseFloat(currentAmount) > 0;
                        const explicitSide = form.dataset.openingSideExplicit === '1';
                        if (!hasExistingAmount && !explicitSide) {
                            setSide(form, defaultSide);
                        }
                    }

                    const partyField = form.querySelector('[data-opening-party-field]');
                    const partySelect = form.querySelector('[data-opening-party-select]');
                    if (partyField && partySelect) {
                        partyField.hidden = !isPartyControl;
                        partySelect.disabled = !isPartyControl;
                        if (!isPartyControl) partySelect.value = '';
                    }

                    const moneyField = form.querySelector('[data-opening-money-field]');
                    const moneySelect = form.querySelector('[data-opening-money-select]');
                    if (moneyField && moneySelect) {
                        moneyField.hidden = !isCashBank;
                        moneySelect.disabled = !isCashBank;

                        let selectedStillVisible = false;
                        Array.from(moneySelect.options).forEach((moneyOption) => {
                            if (!moneyOption.value) {
                                moneyOption.hidden = false;
                                moneyOption.disabled = false;
                                return;
                            }

                            const matches = hasAccount && moneyOption.getAttribute('data-account-id') === accountSelect.value;
                            moneyOption.hidden = !matches;
                            moneyOption.disabled = !matches;
                            if (matches && moneyOption.selected) selectedStillVisible = true;
                        });

                        if (!isCashBank || !selectedStillVisible) {
                            moneySelect.value = '';
                        }
                    }

                    const help = form.querySelector('[data-opening-side-help]');
                    if (help) {
                        help.textContent = hasAccount
                            ? `Auto-selected from the selected ledger${defaultSide ? ' (' + defaultSide.charAt(0).toUpperCase() + defaultSide.slice(1) + ')' : ''}.`
                            : 'Auto-selected after selecting a ledger.';
                    }

                    syncDebitCredit(form);
                }

                document.addEventListener('change', function (event) {
                    const accountSelect = event.target.closest('[data-opening-account-select]');
                    if (accountSelect) {
                        const form = accountSelect.closest('form');
                        if (form) {
                            form.dataset.openingSideExplicit = '0';
                            const option = selectedOption(accountSelect);
                            const defaultSide = option?.getAttribute('data-default-side') || 'debit';
                            setSide(form, defaultSide);
                            updateOpeningForm(form, { applyDefaultSide: false });
                        }
                        return;
                    }

                    const sideInput = event.target.closest('[data-opening-side]');
                    if (sideInput) {
                        const form = sideInput.closest('form');
                        if (form) {
                            form.dataset.openingSideExplicit = '1';
                            syncDebitCredit(form);
                        }
                        return;
                    }

                    const moneySelect = event.target.closest('[data-opening-money-select]');
                    if (moneySelect) {
                        const selected = selectedOption(moneySelect);
                        const accountId = selected ? selected.getAttribute('data-account-id') : '';
                        const form = moneySelect.closest('form');
                        const account = form ? form.querySelector('[data-opening-account-select]') : null;

                        if (accountId && account && account.value !== accountId) {
                            account.value = accountId;
                            account.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }

                    const yearSelect = event.target.closest('#opening-financial-year');
                    if (yearSelect) {
                        const form = yearSelect.closest('form');
                        const dateField = form ? form.querySelector('[data-opening-date]') : null;
                        const startDate = selectedOption(yearSelect)?.getAttribute('data-start-date') || '';
                        if (dateField && startDate && !dateField.value) {
                            dateField.value = startDate;
                        }
                    }
                });

                document.addEventListener('input', function (event) {
                    const amountField = event.target.closest('[data-opening-amount]');
                    if (!amountField) return;
                    const form = amountField.closest('form');
                    if (form) syncDebitCredit(form);
                });

                document.addEventListener('hisebghor:setup-values-applied', function (event) {
                    const form = event.target;
                    if (!(form instanceof HTMLFormElement)) return;

                    const values = event.detail?.values || {};
                    const amountField = form.querySelector('[data-opening-amount]');
                    if (amountField && !amountField.value) {
                        const debit = parseFloat(values.debit || 0) || 0;
                        const credit = parseFloat(values.credit || 0) || 0;
                        if (debit > 0 || credit > 0) {
                            amountField.value = debit > 0 ? debit : credit;
                            setSide(form, credit > 0 ? 'credit' : 'debit');
                            form.dataset.openingSideExplicit = '1';
                        } else {
                            form.dataset.openingSideExplicit = '0';
                        }
                    } else if (values.balance_side) {
                        setSide(form, values.balance_side);
                        form.dataset.openingSideExplicit = '1';
                    }

                    const statusField = form.querySelector('[data-opening-status]');
                    if (statusField) statusField.value = 'posted';

                    updateOpeningForm(form, { applyDefaultSide: false });
                });

                document.querySelectorAll('form[data-setup-form]').forEach((form) => {
                    if (!form.querySelector('[data-opening-account-select]')) return;
                    updateOpeningForm(form, { applyDefaultSide: false });
                });
            })();
        </script>
    @endpush
</x-layouts::accounting>
