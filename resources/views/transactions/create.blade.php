@extends('layouts.app')

@section('title', 'Daily Transaction Entry | Accounting System')

@section('content')
@php
    $money = fn ($value) => number_format((float) $value, 2);

    $headPayload = $transactionHeads->map(fn ($head) => [
        'id' => $head->id,
        'name' => $head->name,
        'nature' => $head->nature,
        'default_party_type_id' => $head->default_party_type_id,
        'requires_party' => (bool) $head->requires_party,
        'requires_reference' => (bool) $head->requires_reference,
        'settlements' => $head->settlementTypes->map(fn ($settlement) => [
            'id' => $settlement->id,
            'name' => $settlement->name,
            'code' => $settlement->code,
        ])->values(),
    ])->values();

    $partyPayload = $parties->map(fn ($party) => [
        'id' => $party->id,
        'party_name' => $party->party_name,
        'party_code' => $party->party_code,
        'party_type_id' => $party->party_type_id,
        'linked_ledger_account_id' => $party->linked_ledger_account_id,
    ])->values();

    $cashBankPayload = $cashBankAccounts->map(fn ($account) => [
        'id' => $account->id,
        'cash_bank_name' => $account->cash_bank_name,
        'type' => $account->type,
        'linked_ledger_account_id' => $account->linked_ledger_account_id,
    ])->values();

    $firstHead = $transactionHeads->first();
@endphp

<div class="page-title">
    <div>
        <h2>Log Daily Transaction</h2>
        <p>Enter simple business information. The system will prepare debit and credit automatically.</p>
    </div>

    <div class="quick-actions">
        <button class="btn-outline" id="newBtn" type="button">+ New</button>
        <button class="btn-ghost" type="button" data-toast="Transaction list will be added in the next screen.">View Transactions</button>
    </div>
</div>

<div class="transaction-stats">
    <div class="card transaction-stat">
        <span>Today Cash In</span>
        <strong class="green">BDT {{ $money($todayCashIn) }}</strong>
        <small>Receipt and collections</small>
    </div>

    <div class="card transaction-stat">
        <span>Today Cash Out</span>
        <strong class="red">BDT {{ $money($todayCashOut) }}</strong>
        <small>Payments and expenses</small>
    </div>

    <div class="card transaction-stat">
        <span>Due Payable</span>
        <strong class="orange">BDT {{ $money($duePayable) }}</strong>
        <small>We need to pay</small>
    </div>

    <div class="card transaction-stat">
        <span>Due Receivable</span>
        <strong class="blue">BDT {{ $money($dueReceivable) }}</strong>
        <small>We need to collect</small>
    </div>
</div>

<div class="layout" style="margin-top:18px">
    <div class="left-stack">
        <form
            class="card form-card"
            id="transactionForm"
            data-preview-url="{{ route('api.transactions.preview') }}"
            data-store-url="{{ route('api.transactions.store') }}"
        >
            @csrf

            <input type="hidden" id="statusInput" name="status" value="Posted">

            <h3 class="section-title">Transaction Information</h3>

            <div class="form-grid transaction-form-grid">
                <div>
                    <label>Date <span class="required">*</span></label>
                    <input
                        type="date"
                        id="date"
                        name="voucher_date"
                        value="{{ now()->toDateString() }}"
                        required
                    >
                </div>

                <div>
                    <label>Voucher Type</label>
                    <select id="voucherType" name="voucher_type">
                        <option>Auto Select</option>
                        @foreach($voucherTypes as $voucherType)
                            <option>{{ $voucherType }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Transaction Head <span class="required">*</span></label>
                    <select id="head" name="transaction_head_id" required>
                        <option value="">Select Transaction Head</option>
                        @foreach($transactionHeads as $transactionHead)
                            <option value="{{ $transactionHead->id }}" @selected($firstHead?->id === $transactionHead->id)>
                                {{ $transactionHead->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Party / Person <span class="required" id="partyRequired">*</span></label>
                    <select id="party" name="party_id">
                        <option value="">Select Party</option>
                        @foreach($parties as $party)
                            <option
                                value="{{ $party->id }}"
                                data-party-type="{{ $party->party_type_id }}"
                            >
                                {{ $party->party_code }} - {{ $party->party_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="money-wrap">
                    <label>Amount <span class="required">*</span></label>
                    <span>BDT</span>
                    <input
                        type="number"
                        id="amount"
                        name="amount"
                        value="10000"
                        min="0.01"
                        step="0.01"
                        required
                    >
                </div>

                <div>
                    <label>Settlement Type <span class="required">*</span></label>
                    <select id="settlement" name="settlement_type_id" required>
                        <option value="">Select Settlement</option>
                    </select>
                </div>

                <div id="cashBankBox">
                    <label>Paid From / Received In <span class="required">*</span></label>
                    <select id="cashBank" name="cash_bank_account_id">
                        <option value="">Select Cash / Bank</option>
                        @foreach($cashBankAccounts as $cashBankAccount)
                            <option value="{{ $cashBankAccount->id }}">
                                {{ $cashBankAccount->cash_bank_name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">Shown when settlement is Cash or Bank.</div>
                </div>

                <div>
                    <label>Reference</label>
                    <input
                        id="reference"
                        name="reference"
                        placeholder="Example: Salary for July, Fuel bill #123"
                    >
                    <div class="hint" id="referenceHint">Optional unless transaction head requires it.</div>
                </div>

                <div>
                    <label>Attachment</label>
                    <input type="file" id="attachment" name="attachment">
                    <div class="hint">Receipt, bill, invoice, or proof</div>
                </div>

                <div class="span-3">
                    <label>Notes</label>
                    <textarea
                        id="notes"
                        name="notes"
                        placeholder="Write short remarks for this transaction"
                    ></textarea>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn-ghost" id="clearBtn">Clear</button>
                <button type="button" class="btn-outline" id="draftBtn">Save Draft</button>
                <button type="submit" class="btn-primary">Post Transaction</button>
            </div>
        </form>

        <div class="card preview-card">
            <div class="preview-head">
                <h3>Generated Ledger Preview</h3>
                <span class="badge badge-warning" id="mappingStatus">Waiting</span>
            </div>

            <table class="ledger-table">
                <thead>
                    <tr>
                        <th>Ledger Account</th>
                        <th>Debit</th>
                        <th>Credit</th>
                    </tr>
                </thead>

                <tbody id="ledgerRows">
                    <tr>
                        <td colspan="3" style="text-align:center;color:var(--muted);padding:22px">
                            Select transaction information to preview ledger entries.
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="validation warn" id="validationBox">
                Select transaction information to generate preview.
            </div>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card side-card">
            <h3>Transaction Summary</h3>

            <div class="summary-list">
                <div class="summary-row">
                    <span>Voucher No.</span>
                    <strong id="voucherNo">—</strong>
                </div>

                <div class="summary-row">
                    <span>Nature</span>
                    <strong id="nature">—</strong>
                </div>

                <div class="summary-row">
                    <span>Party Effect</span>
                    <strong id="partyEffect">—</strong>
                </div>

                <div class="summary-row">
                    <span>Cash/Bank Effect</span>
                    <strong id="cashEffect">—</strong>
                </div>

                <div class="summary-row">
                    <span>Status</span>
                    <strong><span class="badge badge-warning" id="summaryStatus">Draft</span></strong>
                </div>
            </div>
        </div>

        <div class="card tip-card">
            <div class="tip-icon">💡</div>
            <div>
                <strong>Simple rule</strong>
                <p>User only selects transaction head, party, amount, and settlement type. Backend mapping creates the ledger entry.</p>
            </div>
        </div>

        <div class="card side-card">
            <h3>Recent Transactions</h3>

            <div class="recent-list" id="recentList">
                @forelse($recentTransactions as $transaction)
                    <div class="recent-item">
                        <strong>
                            {{ $transaction->transactionHead?->name ?? 'Transaction' }}
                            - BDT {{ $money($transaction->amount) }}
                        </strong>
                        <span>
                            {{ $transaction->party?->party_name ?? 'No Party' }}
                            • {{ $transaction->settlementType?->name ?? '—' }}
                            • {{ $transaction->voucher_number }}
                        </span>
                    </div>
                @empty
                    <div class="recent-item">
                        <strong>No transactions yet</strong>
                        <span>Post your first transaction to see it here.</span>
                    </div>
                @endforelse
            </div>
        </div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const heads = @json($headPayload);
    const parties = @json($partyPayload);
    const cashBanks = @json($cashBankPayload);

    const form = document.getElementById('transactionForm');

    const date = document.getElementById('date');
    const voucherType = document.getElementById('voucherType');
    const head = document.getElementById('head');
    const party = document.getElementById('party');
    const partyRequired = document.getElementById('partyRequired');
    const amount = document.getElementById('amount');
    const settlement = document.getElementById('settlement');
    const cashBank = document.getElementById('cashBank');
    const cashBankBox = document.getElementById('cashBankBox');
    const reference = document.getElementById('reference');
    const referenceHint = document.getElementById('referenceHint');
    const notes = document.getElementById('notes');
    const statusInput = document.getElementById('statusInput');

    const ledgerRows = document.getElementById('ledgerRows');
    const mappingStatus = document.getElementById('mappingStatus');
    const validationBox = document.getElementById('validationBox');

    const voucherNo = document.getElementById('voucherNo');
    const nature = document.getElementById('nature');
    const partyEffect = document.getElementById('partyEffect');
    const cashEffect = document.getElementById('cashEffect');
    const summaryStatus = document.getElementById('summaryStatus');

    let previewTimer = null;
    let lastPreviewOk = false;

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        alert(message);
    }

    function money(value) {
        return Number(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function text(value, fallback = '—') {
        const stringValue = String(value ?? '').trim();

        return stringValue === '' ? fallback : stringValue;
    }

    function headById(id) {
        return heads.find((item) => String(item.id) === String(id));
    }

    function selectedSettlement() {
        return settlement.selectedOptions[0] || null;
    }

    function isCashBankSettlement() {
        const option = selectedSettlement();

        if (!option) {
            return false;
        }

        return ['CASH', 'BANK'].includes(option.dataset.code || '')
            || ['Cash', 'Bank'].includes(option.textContent.trim());
    }

    function resetPreview(message = 'Select transaction information to generate preview.') {
        lastPreviewOk = false;

        ledgerRows.innerHTML = `
            <tr>
                <td colspan="3" style="text-align:center;color:var(--muted);padding:22px">${message}</td>
            </tr>
        `;

        mappingStatus.className = 'badge badge-warning';
        mappingStatus.textContent = 'Waiting';

        validationBox.className = 'validation warn';
        validationBox.textContent = message;

        voucherNo.textContent = '—';
        nature.textContent = '—';
        partyEffect.textContent = '—';
        cashEffect.textContent = '—';
    }

    function populateSettlementOptions() {
        const selectedHead = headById(head.value);

        settlement.innerHTML = '<option value="">Select Settlement</option>';

        if (!selectedHead) {
            resetPreview();
            return;
        }

        selectedHead.settlements.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            option.dataset.code = item.code;
            settlement.appendChild(option);
        });

        if (selectedHead.settlements.length > 0) {
            settlement.value = selectedHead.settlements[0].id;
        }

        party.required = selectedHead.requires_party;
        partyRequired.style.display = selectedHead.requires_party ? '' : 'none';

        reference.required = selectedHead.requires_reference;
        referenceHint.textContent = selectedHead.requires_reference
            ? 'Reference is required for this transaction head.'
            : 'Optional unless transaction head requires it.';

        filterParties();
        toggleCashBank();
        schedulePreview();
    }

    function filterParties() {
        const selectedHead = headById(head.value);
        const partyTypeId = selectedHead?.default_party_type_id;

        Array.from(party.options).forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            option.hidden = partyTypeId
                ? String(option.dataset.partyType) !== String(partyTypeId)
                : false;
        });

        if (party.selectedOptions[0]?.hidden) {
            party.value = '';
        }
    }

    function toggleCashBank() {
        const show = isCashBankSettlement();

        cashBankBox.classList.toggle('hidden', !show);
        cashBank.required = show;

        if (!show) {
            cashBank.value = '';
        }
    }

    function formReadyForPreview() {
        if (!date.value || !head.value || !settlement.value || Number(amount.value || 0) <= 0) {
            return false;
        }

        const selectedHead = headById(head.value);

        if (selectedHead?.requires_party && !party.value) {
            return false;
        }

        if (isCashBankSettlement() && !cashBank.value) {
            return false;
        }

        return true;
    }

    function schedulePreview() {
        clearTimeout(previewTimer);

        previewTimer = setTimeout(() => {
            preview();
        }, 250);
    }

    async function preview() {
        toggleCashBank();

        if (!formReadyForPreview()) {
            resetPreview('Complete required fields to generate ledger preview.');
            return;
        }

        const formData = new FormData(form);
        formData.set('status', statusInput.value || 'Posted');

        try {
            const response = await fetch(form.dataset.previewUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw result;
            }

            renderPreview(result.data);
        } catch (error) {
            const message = error?.errors
                ? Object.values(error.errors)[0][0]
                : error?.message || 'Posting blocked. Please configure ledger mapping first.';

            lastPreviewOk = false;

            ledgerRows.innerHTML = `
                <tr>
                    <td colspan="3" style="text-align:center;color:#b42318;padding:22px">${message}</td>
                </tr>
            `;

            mappingStatus.className = 'badge badge-danger';
            mappingStatus.textContent = 'Mapping Missing';

            validationBox.className = 'validation error';
            validationBox.textContent = message;

            voucherNo.textContent = '—';
            nature.textContent = 'Unknown';
            partyEffect.textContent = 'Unknown';
            cashEffect.textContent = 'Unknown';
        }
    }

    function renderPreview(data) {
        lastPreviewOk = true;

        const rows = data.entries.map((entry) => {
            const debitAmount = Number(entry.debit || 0);
            const creditAmount = Number(entry.credit || 0);
            const side = debitAmount > 0 ? 'Debit' : 'Credit';
            const sideAmount = debitAmount > 0 ? debitAmount : creditAmount;

            const accountCode = text(entry.account_code, '');
            const accountName = text(entry.account_name);
            const accountType = text(entry.account_type, 'Account');
            const normalBalance = text(entry.normal_balance, side);
            const postingEffect = text(entry.posting_effect, normalBalance === side ? 'Increase' : 'Decrease');

            const effectClass = postingEffect === 'Increase'
                ? 'ledger-effect-increase'
                : 'ledger-effect-decrease';

            return `
                <tr>
                    <td>
                        <div class="ledger-account-name">
                            ${accountCode ? `${accountCode} - ` : ''}${accountName}
                        </div>

                        <div class="ledger-account-meta">
                            <span>${accountType}</span>
                            <span>Normal: ${normalBalance}</span>
                            <span class="${effectClass}">${postingEffect}</span>
                        </div>

                        <div class="ledger-account-rule">
                            ${side} BDT ${money(sideAmount)} → ${postingEffect} ${accountType}
                        </div>
                    </td>

                    <td>${money(entry.debit)}</td>
                    <td>${money(entry.credit)}</td>
                </tr>
            `;
        }).join('');

        ledgerRows.innerHTML = `
            ${rows}
            <tr class="total-row">
                <td>Total</td>
                <td>${money(data.total_debit)}</td>
                <td>${money(data.total_credit)}</td>
            </tr>
        `;

        mappingStatus.className = 'badge badge-success';
        mappingStatus.textContent = 'Mapping Found';

        validationBox.className = data.balanced ? 'validation' : 'validation error';
        validationBox.textContent = data.balanced
            ? '✓ Accounting check passed. Debit equals Credit and account effects follow normal balance rules.'
            : 'Debit and credit are not balanced. Posting blocked.';

        voucherNo.textContent = data.voucher_number;
        nature.textContent = data.nature;
        partyEffect.textContent = data.party_ledger_effect;
        cashEffect.textContent = data.cash_bank_effect;
    }

    async function submitTransaction(status) {
        statusInput.value = status;
        summaryStatus.textContent = status;
        summaryStatus.className = status === 'Posted'
            ? 'badge badge-success'
            : 'badge badge-warning';

        await preview();

        if (!lastPreviewOk) {
            showToast('Cannot save. Ledger preview is invalid.');
            return;
        }

        const formData = new FormData(form);
        formData.set('status', status);

        try {
            const response = await fetch(form.dataset.storeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
            });

            const result = await response.json();

            if (!response.ok) {
                const message = result.errors
                    ? Object.values(result.errors)[0][0]
                    : result.message || 'Please check validation errors.';

                showToast(message);
                return;
            }

            showToast(result.message || 'Transaction saved.');

            setTimeout(() => {
                window.location.href = result.redirect || window.location.href;
            }, 700);
        } catch (error) {
            console.error(error);
            showToast('Transaction API error. Please check backend code.');
        }
    }

    function clearForm() {
        form.reset();
        statusInput.value = 'Posted';
        summaryStatus.textContent = 'Draft';
        summaryStatus.className = 'badge badge-warning';

        date.value = '{{ now()->toDateString() }}';
        amount.value = '10000';

        populateSettlementOptions();
        resetPreview('Complete required fields to generate ledger preview.');

        showToast('Form cleared.');
    }

    [date, voucherType, head, party, amount, settlement, cashBank, reference, notes].forEach((input) => {
        input.addEventListener('input', schedulePreview);
        input.addEventListener('change', schedulePreview);
    });

    head.addEventListener('change', populateSettlementOptions);
    settlement.addEventListener('change', () => {
        toggleCashBank();
        schedulePreview();
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitTransaction('Posted');
    });

    document.getElementById('draftBtn').addEventListener('click', () => {
        submitTransaction('Draft');
    });

    document.getElementById('clearBtn').addEventListener('click', clearForm);
    document.getElementById('newBtn').addEventListener('click', clearForm);

    document.querySelectorAll('[data-toast]').forEach((button) => {
        button.addEventListener('click', () => showToast(button.dataset.toast));
    });

    populateSettlementOptions();
});
</script>
@endpush
