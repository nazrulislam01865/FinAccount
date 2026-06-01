@extends('layouts.app')

@section('title', 'Advance Management | HisebGhor')

@section('content')
@php
    $money = fn ($value) => 'BDT ' . number_format((float) $value, 2);
    $dateLabel = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d M Y') : '-';
    $statusClass = fn ($status) => match ($status) {
        'Open' => 'badge-neutral',
        'Partially Adjusted' => 'badge-warning',
        'Fully Adjusted' => 'badge-success',
        default => 'badge-neutral',
    };
    $typeClass = fn ($type) => $type === 'Received' ? 'badge-success' : 'badge-danger';
    $currentUser = auth()->user();
    $canManageAdvance = $currentUser?->hasAnyPermission(['advance-management.manage', 'transactions.create']) ?? false;
@endphp

<div class="page-title">
    <div>
        <span class="page-label">Accounting Output</span>
        <h2>Advance Management</h2>
        <p>Record advance paid or advance received, then adjust it against posted payable or receivable balances using configured accounting rules.</p>
    </div>
    <div class="quick-actions">
        <button class="btn-outline" type="button" onclick="window.print()">Print</button>
        <button class="btn-primary" type="button" id="newAdvanceButton">+ New Advance</button>
    </div>
</div>

<div class="advance-page setup-record-flow">
<div class="stats-grid advance-stats">
    <div class="card stat-card">
        <small>Advance Paid Balance</small>
        <strong class="red-text">{{ $money($stats['paid']) }}</strong>
        <span class="muted">Asset: money given by company</span>
    </div>
    <div class="card stat-card">
        <small>Advance Received Balance</small>
        <strong class="green-text">{{ $money($stats['received']) }}</strong>
        <span class="muted">Liability: money received before service</span>
    </div>
    <div class="card stat-card">
        <small>Partially Adjusted</small>
        <strong class="orange-text">{{ $stats['partial'] }}</strong>
        <span class="muted">Still has remaining balance</span>
    </div>
    <div class="card stat-card">
        <small>Fully Adjusted</small>
        <strong>{{ $stats['closed'] }}</strong>
        <span class="muted">Balance already cleared</span>
    </div>
</div>

        <form
            class="card form-card"
            id="advanceForm"
            action="{{ route('api.advance-management.store') }}"
            method="POST"
            data-new-rules='@json($newAdvanceRules->values())'
            data-adjustment-rules='@json($adjustmentRules->values())'
        >
            @csrf
            <h3 class="section-title">Advance Entry / Adjustment</h3>

            <input type="hidden" name="party_id" id="advancePartyId">
            <input type="hidden" name="account_id" id="advanceAccountId">
            <input type="hidden" name="settlement_type_id" id="advanceSettlementTypeId">

            <div class="form-grid">
                <div>
                    <label>Entry Mode <span class="required">*</span></label>
                    <select name="entry_mode" id="entryMode" required>
                        <option>New Advance</option>
                        <option>Advance Adjustment</option>
                    </select>
                </div>
                <div>
                    <label>Advance Type <span class="required">*</span></label>
                    <select name="advance_type" id="advanceType" required>
                        <option value="Paid">Advance Paid</option>
                        <option value="Received">Advance Received</option>
                    </select>
                </div>
                <div>
                    <label>Party / Person <span class="required">*</span></label>
                    <select id="advancePartySelect" required>
                        <option value="">Select Party</option>
                        @foreach($parties as $party)
                            <option value="{{ $party->id }}">{{ $party->party_name }}{{ $party->partyType?->name ? ' - ' . $party->partyType?->name : '' }}</option>
                        @endforeach
                    </select>
                    <div class="hint">Connected with Party / Person Setup. Select an existing advance row for adjustment.</div>
                </div>
                <div class="two-col">
                    <div>
                        <label>Date <span class="required">*</span></label>
                        <input type="date" name="voucher_date" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div>
                        <label>Available Advance</label>
                        <input id="availableBalanceText" value="BDT 0.00" readonly>
                    </div>
                </div>
                <div id="linkedDueBalanceWrap" style="display:none">
                    <label id="linkedDueBalanceLabel">Current Payable / Receivable Due</label>
                    <input id="linkedDueBalanceText" value="BDT 0.00" readonly>
                    <div class="hint">For adjustment, the allowed amount is the smaller of available advance and current payable/receivable due.</div>
                    <div id="adjustmentLimitNotice" class="advance-limit-notice" style="display:none"></div>
                </div>
                <div class="two-col">
                    <div>
                        <label>Amount <span class="required">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="advanceAmount" required>
                    </div>
                    <div>
                        <label>Cash / Bank Account</label>
                        <select name="cash_bank_account_id" id="cashBankAccountId">
                            <option value="">Select Cash / Bank</option>
                            @foreach($cashBankAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->cash_bank_name }} - {{ $account->linkedLedger?->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label>Accounting Rule <span class="required">*</span></label>
                    <select name="transaction_head_id" id="advanceRuleHead" required>
                        <option value="">Select rule</option>
                    </select>
                    <div class="hint">Rules are loaded from Transaction Head Setup + Accounting Rules Setup.</div>
                </div>
                <div>
                    <label>Reference</label>
                    <input name="reference" id="advanceReference" placeholder="Advance reference, bill no, adjustment note...">
                </div>
                <div>
                    <label>Notes</label>
                    <textarea name="notes" id="advanceNotes" placeholder="Advance paid/received or adjustment note"></textarea>
                </div>
                <div class="ledger-preview-box" id="advanceLedgerPreview">
                    <strong>Accounting rule</strong>
                    Select entry mode and type to see the Dr/Cr preview.
                </div>
            </div>

            <div class="actions">
                <button class="btn-ghost" type="reset">Clear</button>
                @if($canManageAdvance)
                    <button class="btn-primary" type="submit" id="advanceSubmitButton">Post Advance</button>
                @endif
            </div>
        </form>

<form class="card toolbar advance-toolbar" method="GET" action="{{ route('advance-management.index') }}" style="margin-top:18px">
    <div class="field search-field">
        <label>Search</label>
        <span>⌕</span>
        <input name="search" value="{{ $filters['search'] }}" placeholder="Party, account, voucher, status...">
    </div>
    <div>
        <label>Advance Type</label>
        <select name="advance_type">
            @foreach(['All', 'Advance Paid', 'Advance Received'] as $type)
                <option value="{{ $type }}" @selected($filters['advance_type'] === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Status</label>
        <select name="status">
            @foreach(['All', 'Open', 'Partially Adjusted', 'Fully Adjusted'] as $status)
                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>From Date</label>
        <input type="date" name="from_date" value="{{ $filters['from_date'] }}">
    </div>
    <div>
        <label>To Date</label>
        <input type="date" name="to_date" value="{{ $filters['to_date'] }}">
    </div>
    <button class="btn-primary" type="submit">Filter</button>
</form>

<div class="advance-tabs" style="margin-top:18px">
    <button type="button" class="advance-tab active" data-tab="All">All Advances</button>
    <button type="button" class="advance-tab" data-tab="Paid">Advance Paid</button>
    <button type="button" class="advance-tab" data-tab="Received">Advance Received</button>
    <button type="button" class="advance-tab" data-tab="Partially Adjusted">Partial</button>
    <button type="button" class="advance-tab" data-tab="Fully Adjusted">Fully Adjusted</button>
</div>

<div class="layout advance-layout" style="margin-top:18px">
    <div class="left-stack">
        <div class="card table-card">
            <div class="card-head">
                <div>
                    <h3>Advance List</h3>
                    <p>Balances are calculated from advance_register movements created by posted vouchers.</p>
                </div>
                <span class="badge badge-primary">{{ $advanceRows->count() }} record(s)</span>
            </div>
            <div class="table-wrap">
                <table id="advanceManagementTable" data-client-pagination="true" data-page-size="10">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Party</th>
                            <th>Advance Ledger</th>
                            <th>Advance Type</th>
                            <th>Original</th>
                            <th>Adjusted</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Latest Voucher</th>
                            <th style="text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($advanceRows as $row)
                            <tr
                                data-advance-row
                                data-party-id="{{ $row['party_id'] }}"
                                data-party-name="{{ e($row['party_name']) }}"
                                data-account-id="{{ $row['account_id'] }}"
                                data-account-name="{{ e($row['account_name']) }}"
                                data-advance-type="{{ $row['advance_type'] }}"
                                data-advance-type-label="{{ $row['advance_type_label'] }}"
                                data-balance="{{ $row['balance'] }}"
                                data-linked-due-balance="{{ $row['linked_due_balance'] ?? 0 }}"
                                data-linked-due-label="{{ e($row['linked_due_label'] ?? '') }}"
                                data-max-adjustment="{{ $row['max_adjustment'] ?? 0 }}"
                                data-original="{{ $row['original_amount'] }}"
                                data-adjusted="{{ $row['adjusted_amount'] }}"
                                data-status="{{ $row['status'] }}"
                            >
                                <td>{{ $dateLabel($row['advance_date']) }}</td>
                                <td class="strong">{{ $row['party_name'] }}<br><span class="muted">{{ $row['party_type'] }}</span></td>
                                <td>{{ $row['account_name'] }}</td>
                                <td><span class="badge {{ $typeClass($row['advance_type']) }}">{{ $row['advance_type_label'] }}</span></td>
                                <td class="money-cell">{{ $money($row['original_amount']) }}</td>
                                <td class="money-cell">{{ $money($row['adjusted_amount']) }}</td>
                                <td class="money-cell strong">{{ $money($row['balance']) }}</td>
                                <td><span class="badge {{ $statusClass($row['status']) }}">{{ $row['status'] }}</span></td>
                                <td>{{ $row['latest_voucher'] ?? '-' }}</td>
                                <td>
                                    <div class="action-cell">
                                        @if($canManageAdvance && $row['balance'] > 0)
                                            <button type="button" class="icon-btn" title="Select advance" data-select-advance>✓</button>
                                            <button type="button" class="icon-btn" title="Adjust advance" data-adjust-advance>↔</button>
                                        @else
                                            <span class="muted">-</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty="true">
                                <td colspan="10" class="empty-state">No advance balance found for the selected filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span>Showing {{ $advanceRows->count() }} advance balance row(s)</span>
                <span>Source: posted journal lines and advance register</span>
            </div>
        </div>
    </div>

</div>
        <div class="card helper-card">
            <h3>Accounting Control</h3>
            <p><strong>Advance paid:</strong> Dr Advance to Supplier / Employee, Cr Cash/Bank.</p>
            <p><strong>Advance received:</strong> Dr Cash/Bank, Cr Advance from Customer.</p>
            <p><strong>Adjustment:</strong> clears advance and reduces related AP/AR due without re-recording cash movement.</p>
        </div>
</div>
@endsection

@push('styles')
<style>
    .setup-record-flow {
        display: grid;
        gap: 18px;
    }

    .setup-record-flow .stats-grid {
        order: 1;
    }

    .setup-record-flow .advance-layout {
        order: 5;
        display: grid;
        grid-template-columns: 1fr !important;
        margin-top: 0 !important;
    }

    .setup-record-flow #advanceForm {
        order: 2;
        width: 100%;
        position: static;
    }

    .setup-record-flow .advance-toolbar {
        order: 3;
        margin-top: 0 !important;
        grid-template-columns: minmax(220px, 1fr) 170px 185px 150px 150px 120px;
        width: 100%;
    }

    .setup-record-flow .advance-tabs {
        order: 4;
        margin-top: 0 !important;
    }

    .setup-record-flow .advance-layout > .left-stack {
        width: 100%;
    }

    .setup-record-flow .helper-card {
        order: 6;
        width: 100%;
    }

    .setup-record-flow #advanceForm .form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: start;
    }

    .setup-record-flow #advanceForm .form-grid > .two-col,
    .setup-record-flow #advanceForm .ledger-preview-box,
    .setup-record-flow #advanceForm .actions {
        grid-column: 1 / -1;
    }

    .setup-record-flow .table-card,
    .setup-record-flow .table-wrap {
        width: 100%;
    }

    .setup-record-flow .table-wrap {
        overflow-x: scroll;
        scrollbar-gutter: stable both-edges;
    }

    .advance-tabs { display: flex; gap: 8px; padding: 10px; background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 8px 24px rgba(16,24,40,.04); flex-wrap: wrap; }
    .advance-tab { padding: 10px 14px; border-radius: 12px; background: #fff; color: #475467; border: 0; font-size: 13px; font-weight: 850; min-height: 38px; }
    .advance-tab.active { background: var(--primary); color: #fff; }
    .money-cell { text-align: right; font-weight: 800; white-space: nowrap; }
    .red-text { color: #dc2626 !important; }
    .green-text { color: #067647 !important; }
    .orange-text { color: #b54708 !important; }
    .advance-stats .stat-card span { display: block; font-size: 12px; }
    .ledger-preview-box { border: 1px solid var(--line); border-radius: 14px; background: #fbfcfd; padding: 14px; font-size: 13px; line-height: 1.55; }
    .ledger-preview-box strong { display: block; margin-bottom: 6px; }
    .advance-limit-notice { margin-top: 10px; padding: 10px 12px; border-radius: 12px; background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; font-size: 12px; line-height: 1.45; }
    .advance-limit-notice.success { background: #ecfdf3; color: #067647; border-color: #bbf7d0; }
    .advance-layout .helper-card p { font-size: 13px; line-height: 1.45; color: #475467; }

    @media (max-width: 1320px) {
        .setup-record-flow .advance-toolbar { grid-template-columns: 1fr 1fr 1fr; }
    }

    @media (max-width: 880px) {
        .setup-record-flow .advance-toolbar,
        .setup-record-flow #advanceForm .form-grid {
            grid-template-columns: 1fr;
        }

        .advance-tabs { display: grid; }
        .advance-tab { text-align: left; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const form = document.getElementById('advanceForm');
    if (!form) return;

    const money = (value) => 'BDT ' + Number(value || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const newRules = JSON.parse(form.dataset.newRules || '[]');
    const adjustmentRules = JSON.parse(form.dataset.adjustmentRules || '[]');
    const showToast = (message) => window.AccountingUI?.showToast ? window.AccountingUI.showToast(message) : alert(message);

    const entryMode = document.getElementById('entryMode');
    const advanceType = document.getElementById('advanceType');
    const partySelect = document.getElementById('advancePartySelect');
    const partyId = document.getElementById('advancePartyId');
    const accountId = document.getElementById('advanceAccountId');
    const settlementId = document.getElementById('advanceSettlementTypeId');
    const ruleSelect = document.getElementById('advanceRuleHead');
    const amount = document.getElementById('advanceAmount');
    const cashBank = document.getElementById('cashBankAccountId');
    const availableBalanceText = document.getElementById('availableBalanceText');
    const linkedDueBalanceWrap = document.getElementById('linkedDueBalanceWrap');
    const linkedDueBalanceLabel = document.getElementById('linkedDueBalanceLabel');
    const linkedDueBalanceText = document.getElementById('linkedDueBalanceText');
    const preview = document.getElementById('advanceLedgerPreview');
    const adjustmentLimitNotice = document.getElementById('adjustmentLimitNotice');
    const submitButton = document.getElementById('advanceSubmitButton');

    const cents = (value) => Math.round(Number(value || 0) * 100);

    function setAdjustmentLimitNotice(message, isOk = false) {
        if (!adjustmentLimitNotice) return;
        if (!message) {
            adjustmentLimitNotice.style.display = 'none';
            adjustmentLimitNotice.textContent = '';
            adjustmentLimitNotice.classList.remove('success');
            return;
        }
        adjustmentLimitNotice.style.display = '';
        adjustmentLimitNotice.textContent = message;
        adjustmentLimitNotice.classList.toggle('success', Boolean(isOk));
    }

    function setSubmitAvailability(enabled) {
        if (submitButton) {
            submitButton.disabled = !enabled;
            submitButton.title = enabled ? '' : 'This advance cannot be adjusted until a matching payable/receivable due exists.';
        }
    }

    function currentRules() {
        const source = entryMode.value === 'New Advance' ? newRules : adjustmentRules;
        return source.filter(rule => rule.advance_type === advanceType.value && rule.entry_mode === entryMode.value);
    }

    function renderRules() {
        const rules = currentRules();
        ruleSelect.innerHTML = '';
        settlementId.value = '';

        if (!rules.length) {
            ruleSelect.innerHTML = '<option value="">No rule configured</option>';
            preview.innerHTML = '<strong>Missing setup</strong>Configure the related Advance accounting rule in Accounting Rules Setup first.';
            return;
        }

        rules.forEach((rule, index) => {
            const option = document.createElement('option');
            option.value = rule.transaction_head_id;
            option.dataset.settlementId = rule.settlement_type_id;
            option.dataset.debit = rule.debit_account || '';
            option.dataset.credit = rule.credit_account || '';
            option.dataset.requiresCashBank = rule.requires_cash_bank ? '1' : '0';
            option.textContent = rule.label;
            ruleSelect.appendChild(option);
            if (index === 0) settlementId.value = rule.settlement_type_id;
        });

        updateRulePreview();
    }

    function updateRulePreview() {
        const option = ruleSelect.selectedOptions[0];
        if (!option) return;

        settlementId.value = option.dataset.settlementId || '';
        cashBank.required = option.dataset.requiresCashBank === '1' && entryMode.value === 'New Advance';
        cashBank.disabled = entryMode.value === 'Advance Adjustment';
        if (cashBank.disabled) cashBank.value = '';

        const adjustmentNote = entryMode.value === 'Advance Adjustment'
            ? '<br><span class="muted">Adjustment also reduces the matching payable/receivable due balance when the rule uses AP/AR.</span>'
            : '<br><span class="muted">Actual Cash/Bank ledger is taken from selected Cash / Bank account.</span>';

        preview.innerHTML = '<strong>Ledger preview</strong>Dr ' + (option.dataset.debit || '-') + '<br>Cr ' + (option.dataset.credit || '-') + adjustmentNote;
    }

    function resetForNewAdvance() {
        entryMode.value = 'New Advance';
        partyId.value = partySelect.value || '';
        accountId.value = '';
        availableBalanceText.value = 'BDT 0.00';
        linkedDueBalanceWrap.style.display = 'none';
        linkedDueBalanceText.value = 'BDT 0.00';
        amount.max = '';
        amount.disabled = false;
        amount.value = amount.value || '0.00';
        setAdjustmentLimitNotice('');
        setSubmitAvailability(true);
        cashBank.disabled = false;
        renderRules();
    }

    function selectAdvance(row, forceAdjustment = false) {
        document.querySelectorAll('[data-advance-row]').forEach(item => item.style.background = '');
        row.style.background = '#eef4ff';

        partyId.value = row.dataset.partyId;
        partySelect.value = row.dataset.partyId;
        accountId.value = row.dataset.accountId;
        advanceType.value = row.dataset.advanceType;
        const advanceBalance = Number(row.dataset.balance || 0);
        const dueBalance = Number(row.dataset.linkedDueBalance || 0);
        const maxAdjustment = Number(row.dataset.maxAdjustment || 0);
        availableBalanceText.value = money(advanceBalance);

        if (forceAdjustment) {
            entryMode.value = 'Advance Adjustment';
        }

        if (entryMode.value === 'Advance Adjustment') {
            linkedDueBalanceWrap.style.display = '';
            linkedDueBalanceLabel.textContent = advanceType.value === 'Paid'
                ? 'Current Supplier Payable Due'
                : 'Current Customer Receivable Due';
            linkedDueBalanceText.value = money(dueBalance);

            if (cents(maxAdjustment) <= 0) {
                amount.value = '';
                amount.removeAttribute('max');
                amount.disabled = true;
                setSubmitAvailability(false);
                setAdjustmentLimitNotice(
                    advanceType.value === 'Paid'
                        ? 'This supplier has advance balance, but no payable due is available to adjust. Post a supplier bill/due first, then adjust the advance.'
                        : 'This customer has advance balance, but no receivable due is available to adjust. Post a customer invoice/due first, then adjust the advance.'
                );
            } else {
                amount.disabled = false;
                amount.value = Number(maxAdjustment || 0).toFixed(2);
                amount.max = Number(maxAdjustment || 0).toFixed(2);
                setSubmitAvailability(true);
                setAdjustmentLimitNotice('Maximum adjustment allowed now: ' + money(maxAdjustment) + '.', true);
            }
        } else {
            linkedDueBalanceWrap.style.display = 'none';
            linkedDueBalanceText.value = 'BDT 0.00';
            amount.disabled = false;
            amount.value = advanceBalance.toFixed(2);
            amount.max = '';
            setAdjustmentLimitNotice('');
            setSubmitAvailability(true);
        }

        renderRules();
        showToast(forceAdjustment ? 'Ready to adjust selected advance.' : 'Advance selected.');
    }

    document.querySelectorAll('[data-select-advance]').forEach(button => {
        button.addEventListener('click', () => selectAdvance(button.closest('[data-advance-row]'), false));
    });

    document.querySelectorAll('[data-adjust-advance]').forEach(button => {
        button.addEventListener('click', () => selectAdvance(button.closest('[data-advance-row]'), true));
    });

    document.getElementById('newAdvanceButton')?.addEventListener('click', () => {
        form.reset();
        resetForNewAdvance();
        showToast('Ready for new advance entry.');
    });

    partySelect.addEventListener('change', () => {
        if (entryMode.value === 'New Advance') {
            partyId.value = partySelect.value || '';
        }
    });

    [entryMode, advanceType].forEach(element => {
        element.addEventListener('change', () => {
            if (entryMode.value === 'New Advance') {
                accountId.value = '';
                availableBalanceText.value = 'BDT 0.00';
                linkedDueBalanceWrap.style.display = 'none';
                linkedDueBalanceText.value = 'BDT 0.00';
                amount.disabled = false;
                amount.removeAttribute('max');
                setAdjustmentLimitNotice('');
                setSubmitAvailability(true);
            } else {
                linkedDueBalanceWrap.style.display = '';
                amount.disabled = false;
                amount.removeAttribute('max');
                amount.value = '';
                setAdjustmentLimitNotice('Select an open advance row to load the available advance and matching due balance.');
                setSubmitAvailability(false);
            }
            renderRules();
        });
    });

    ruleSelect.addEventListener('change', updateRulePreview);

    document.querySelectorAll('.advance-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.advance-tab').forEach(item => item.classList.remove('active'));
            tab.classList.add('active');
            const filter = tab.dataset.tab;
            document.querySelectorAll('[data-advance-row]').forEach(row => {
                const show = filter === 'All'
                    || row.dataset.advanceType === filter
                    || row.dataset.status === filter;
                row.style.display = show ? '' : 'none';
            });
        });
    });

    form.addEventListener('reset', () => {
        setTimeout(resetForNewAdvance, 0);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!partyId.value) {
            showToast(entryMode.value === 'New Advance' ? 'Please select a party first.' : 'Please select an advance row first.');
            return;
        }

        if (entryMode.value === 'Advance Adjustment' && !accountId.value) {
            showToast('Please select an open advance row before adjustment.');
            return;
        }

        if (entryMode.value === 'Advance Adjustment') {
            const maxAllowed = Number(amount.max || 0);
            const requested = Number(amount.value || 0);
            if (cents(maxAllowed) <= 0) {
                showToast(advanceType.value === 'Paid'
                    ? 'This supplier has advance balance, but no payable due is available to adjust. Post a supplier bill/due first.'
                    : 'This customer has advance balance, but no receivable due is available to adjust. Post a customer invoice/due first.');
                return;
            }
            if (cents(requested) > cents(maxAllowed)) {
                showToast('Adjustment amount cannot be greater than the smaller of available advance and linked due. Maximum allowed: ' + money(maxAllowed));
                return;
            }
        }

        const submitter = event.submitter || form.querySelector('button[type="submit"]');
        const originalText = submitter?.textContent;
        if (submitter) {
            submitter.disabled = true;
            submitter.textContent = 'Posting...';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: new FormData(form)
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success === false) {
                const message = data.message || Object.values(data.errors || {})?.flat()?.[0] || 'Advance posting failed.';
                throw new Error(message);
            }
            showToast(data.message || 'Posted successfully.');
            window.location.href = data.redirect || window.location.href;
        } catch (error) {
            showToast(error.message || 'Advance posting failed.');
            if (submitter) {
                submitter.disabled = false;
                submitter.textContent = originalText;
            }
        }
    });

    resetForNewAdvance();
})();
</script>
@endpush
