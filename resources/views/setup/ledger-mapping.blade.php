@extends('layouts.app')

@section('title', 'Ledger Mapping Setup | Accounting System')

@push('styles')
<style>
    .ledger-mapping-page .table-wrap { overflow-x: auto; }
    .ledger-mapping-page .panel-head { align-items: flex-start; gap: 12px; }
    .ledger-mapping-page .panel-head h3 { margin-bottom: 4px; }
    .ledger-side-note { display: block; margin-top: 4px; color: var(--muted); font-size: 12px; line-height: 1.45; }
    .ledger-mapping-page .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding-top: 14px; border-top: 1px solid var(--line); }
    .ledger-mapping-page .actions .btn-primary { grid-column: span 2; }
    .mapping-preview { border: 1px dashed var(--line); border-radius: 14px; padding: 12px; background: #f8fafc; font-size: 13px; line-height: 1.55; }
    .mapping-preview strong { display: block; margin-bottom: 4px; }
    .mapping-warning { color: #b45309; font-weight: 600; }
    .option-hidden { display: none; }
    @media (max-width: 880px) { .ledger-mapping-page .actions { grid-template-columns: 1fr; } .ledger-mapping-page .actions .btn-primary { grid-column: span 1; } }
</style>
@endpush

@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Ledger Mapping</span>
        <h2>Ledger Mapping</h2>
        <p>Configure automatic debit and credit rules for each transaction head and settlement type.</p>
    </div>
</div>

@include('partials.setup-progress', ['current' => 6])

<div class="layout ledger-mapping-page">
    <div class="left-stack">
        <div class="card toolbar five">
            <div class="field search-field">
                <span>⌕</span>
                <input id="ruleSearch" type="text" placeholder="Search by transaction head...">
            </div>

            <div>
                <label>Transaction Head</label>
                <select id="headFilter">
                    <option value="All">All</option>
                    @foreach($transactionHeads as $transactionHead)
                        <option value="{{ $transactionHead->id }}">{{ $transactionHead->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Settlement Type</label>
                <select id="settlementFilter">
                    <option value="All">All</option>
                    @foreach($settlementTypes as $settlementType)
                        <option value="{{ $settlementType->id }}">{{ $settlementType->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Status</label>
                <select id="statusFilter">
                    <option value="All">All</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <button class="btn-primary" type="button" id="addRuleBtn">+ Add Mapping Rule</button>
        </div>

        <div class="card table-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Transaction Head</th>
                            <th>Settlement</th>
                            <th>Debit Account</th>
                            <th>Credit Account</th>
                            <th>Party Effect</th>
                            <th>Auto Post</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>

                    <tbody id="ruleTable">
                        @forelse($rules as $rule)
                            <tr
                                data-id="{{ $rule->id }}"
                                data-code="{{ e($rule->rule_code) }}"
                                data-head="{{ $rule->transaction_head_id }}"
                                data-settlement="{{ $rule->settlement_type_id }}"
                                data-status="{{ $rule->status }}"
                                data-debit="{{ $rule->debit_account_id }}"
                                data-credit="{{ $rule->credit_account_id }}"
                                data-effect="{{ $rule->party_ledger_effect }}"
                                data-auto="{{ $rule->auto_post ? 1 : 0 }}"
                                data-description="{{ e($rule->description) }}"
                                data-update-url="{{ url('/api/ledger-mapping/' . $rule->id) }}"
                            >
                                <td class="code">{{ $rule->rule_code ?? '—' }}</td>
                                <td class="rule-name">{{ $rule->transactionHead?->name ?? '—' }}</td>
                                <td>{{ $rule->settlementType?->name ?? '—' }}</td>
                                <td>
                                    {{ $rule->debitAccount?->display_name ?? $rule->debitAccount?->account_name ?? '—' }}
                                    @if($rule->debitAccount?->accountType)
                                        <div class="hint">{{ $rule->debitAccount->accountType->name }} · {{ $rule->debitAccount->normal_balance ?: $rule->debitAccount->accountType->normal_balance }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $rule->creditAccount?->display_name ?? $rule->creditAccount?->account_name ?? '—' }}
                                    @if($rule->creditAccount?->accountType)
                                        <div class="hint">{{ $rule->creditAccount->accountType->name }} · {{ $rule->creditAccount->normal_balance ?: $rule->creditAccount->accountType->normal_balance }}</div>
                                    @endif
                                </td>
                                <td>{{ $rule->party_ledger_effect }}</td>
                                <td><span class="switch {{ $rule->auto_post ? 'on' : '' }}"></span></td>
                                <td><span class="badge {{ $rule->status === 'Active' ? 'badge-active' : 'badge-neutral' }}">{{ $rule->status }}</span></td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn edit-btn" type="button" title="Edit">✎</button>
                                        <form method="POST" data-delete-form action="{{ url('/setup/ledger-mapping/' . $rule->id) }}" onsubmit="return confirm('Delete this mapping rule?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty="true">
                                <td colspan="9" style="text-align:center;padding:24px;color:var(--muted)">No ledger mapping rules found. Add your first mapping rule.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span id="resultCount">Showing {{ $rules->count() }} of {{ $rules->count() }} entries</span>
                <div class="pagination">
                    <button class="page-btn" type="button">‹</button>
                    <button class="page-btn active" type="button">1</button>
                    <button class="page-btn" type="button">›</button>
                </div>
            </div>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card form-panel ledger-form-card">
            <div class="panel-head">
                <div>
                    <h3>Create / Edit Mapping Rule</h3>
                    <span class="ledger-side-note">Choose a business action and settlement type. The form guides the debit/credit side.</span>
                </div>
                <span class="badge badge-primary">Step 6</span>
            </div>

            <form class="form-grid" id="ruleForm" data-frontend-form data-accounting-rule-form data-action="{{ url('/api/ledger-mapping') }}" data-store-url="{{ url('/api/ledger-mapping') }}" data-success="Mapping rule saved successfully.">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">

                <div>
                    <label>Transaction Head <span class="required">*</span></label>
                    <select id="head" name="transaction_head_id" required>
                        <option value="">Select Transaction Head</option>
                        @foreach($transactionHeads as $transactionHead)
                            <option
                                value="{{ $transactionHead->id }}"
                                data-name="{{ e($transactionHead->name) }}"
                                data-nature="{{ e($transactionHead->nature) }}"
                                data-settlements='@json($transactionHead->settlementTypes->pluck("id")->map(fn ($id) => (int) $id)->values())'
                            >
                                {{ $transactionHead->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Settlement Type <span class="required">*</span></label>
                    <select id="settlement" name="settlement_type_id" required>
                        <option value="">Select Settlement Type</option>
                        @foreach($settlementTypes as $settlementType)
                            <option value="{{ $settlementType->id }}" data-code="{{ e($settlementType->code) }}" data-name="{{ e($settlementType->name) }}">
                                {{ $settlementType->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="inline-help">Only settlement types allowed by the selected Transaction Head remain selectable.</div>
                </div>

                <div>
                    <label>Debit Account <span class="required">*</span></label>
                    <select id="debit" name="debit_account_id" required>
                        <option value="">Select Debit Account</option>
                        @foreach($accounts as $account)
                            <option
                                value="{{ $account->id }}"
                                data-account-type="{{ $account->accountType?->name }}"
                                data-normal-balance="{{ $account->normal_balance ?: $account->accountType?->normal_balance }}"
                                data-is-cash-bank="{{ $account->is_cash_bank ? 1 : 0 }}"
                                data-account-name="{{ e($account->account_name) }}"
                            >
                                {{ $account->display_name ?? $account->account_code . ' - ' . $account->account_name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="inline-help" id="debitHelp">Debit increases Asset/Expense and decreases Liability/Income/Equity.</div>
                </div>

                <div>
                    <label>Credit Account <span class="required">*</span></label>
                    <select id="credit" name="credit_account_id" required>
                        <option value="">Select Credit Account</option>
                        @foreach($accounts as $account)
                            <option
                                value="{{ $account->id }}"
                                data-account-type="{{ $account->accountType?->name }}"
                                data-normal-balance="{{ $account->normal_balance ?: $account->accountType?->normal_balance }}"
                                data-is-cash-bank="{{ $account->is_cash_bank ? 1 : 0 }}"
                                data-account-name="{{ e($account->account_name) }}"
                            >
                                {{ $account->display_name ?? $account->account_code . ' - ' . $account->account_name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="inline-help" id="creditHelp">Credit increases Liability/Income/Equity and decreases Asset/Expense.</div>
                </div>

                <div>
                    <label>Party Ledger Effect</label>
                    <select id="effect" name="party_ledger_effect">
                        <option value="">Auto - system decides</option>
                        @foreach($partyEffects as $effect)
                            <option value="{{ $effect }}">{{ $effect }}</option>
                        @endforeach
                    </select>
                    <div class="inline-help">Leave Auto unless this rule should update party due or advance in a specific way.</div>
                </div>

                <div>
                    <label>Auto Post</label><br>
                    <input type="hidden" id="autoPostInput" name="auto_post" value="1">
                    <span class="switch on" id="autoPost" data-input="autoPostInput"></span>
                    <div class="inline-help">Automatically post transactions using this rule after validation.</div>
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select id="status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div>
                    <label>Description</label>
                    <textarea id="description" name="description" maxlength="250" placeholder="Example: Supplier Payment + Bank creates Dr Accounts Payable and Cr Bank."></textarea>
                    <div class="inline-help"><span id="charCount">0</span>/250 characters</div>
                </div>

                <div class="mapping-preview" id="mappingPreview">
                    <strong>Rule preview</strong>
                    Select Transaction Head, Settlement Type, Debit Account, and Credit Account to preview the accounting effect.
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn-outline" id="saveBtn">Save</button>
                    <button type="submit" class="btn-primary" id="saveNextBtn">Save & Next ›</button>
                </div>
            </form>
        </div>

        <div class="card helper-card">
            <h3>How Mapping Works</h3>
            <p>Every Transaction Head + Settlement Type maps to one Debit account and one Credit account.</p>
            <div class="flow">
                <div class="flow-row"><span class="flow-chip blue">Transaction Head</span><strong>+</strong><span class="flow-chip purple">Settlement Type</span></div>
                <div class="arrow">↓</div>
                <div class="flow-row"><span class="flow-chip green-chip">Debit Account</span><strong>/</strong><span class="flow-chip yellow">Credit Account</span></div>
            </div>
            <div class="example"><strong>Example</strong><br>Supplier Payment + Bank → Dr Accounts Payable / Cr Bank</div>
        </div>

        <div class="card why-card">
            <div class="why-icon">💡</div>
            <div>
                <h3>Accounting principle</h3>
                <p>Cash received debits Cash/Bank. Cash paid credits Cash/Bank. Due entries must not touch Cash/Bank.</p>
            </div>
        </div>
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('ruleTable');
    const form = document.getElementById('ruleForm');

    const ruleSearch = document.getElementById('ruleSearch');
    const headFilter = document.getElementById('headFilter');
    const settlementFilter = document.getElementById('settlementFilter');
    const statusFilter = document.getElementById('statusFilter');
    const resultCount = document.getElementById('resultCount');

    const head = document.getElementById('head');
    const settlement = document.getElementById('settlement');
    const debit = document.getElementById('debit');
    const credit = document.getElementById('credit');
    const effect = document.getElementById('effect');
    const status = document.getElementById('status');
    const description = document.getElementById('description');
    const charCount = document.getElementById('charCount');
    const mappingPreview = document.getElementById('mappingPreview');

    const autoPost = document.getElementById('autoPost');
    const autoPostInput = document.getElementById('autoPostInput');
    const formMethod = document.getElementById('formMethod');

    const addRuleBtn = document.getElementById('addRuleBtn');
    const cancelBtn = document.getElementById('cancelBtn');

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        alert(message);
    }

    function selectedOption(select) {
        return select?.selectedOptions?.[0] || null;
    }

    function optionText(option) {
        return option ? option.textContent.trim() : '';
    }

    function settlementKey() {
        const option = selectedOption(settlement);
        const value = `${option?.dataset.code || ''} ${option?.dataset.name || ''}`.toUpperCase();

        if (value.includes('ADVANCE_PAID') || value.includes('ADVANCE PAID')) return 'advance_paid';
        if (value.includes('ADVANCE_RECEIVED') || value.includes('ADVANCE RECEIVED')) return 'advance_received';
        if (value.includes('CASH')) return 'cash';
        if (value.includes('BANK')) return 'bank';
        if (value.includes('DUE')) return 'due';
        if (value.includes('ADJUST')) return 'adjustment';
        return 'other';
    }

    function headText() {
        const option = selectedOption(head);
        return `${option?.dataset.nature || ''} ${option?.dataset.name || ''}`.toUpperCase();
    }

    function expectedCashBankSide() {
        const key = settlementKey();
        const text = headText();

        if (key === 'advance_received') return 'Debit';
        if (key === 'advance_paid') return 'Credit';

        if (
            text.includes('RECEIPT')
            || text.includes('RECEIVED')
            || text.includes('COLLECTION')
            || text.includes('INCOME')
            || text.includes('CAPITAL')
        ) {
            return 'Debit';
        }

        return 'Credit';
    }

    function accountMeta(select) {
        const option = selectedOption(select);

        return {
            id: option?.value || '',
            label: optionText(option),
            type: option?.dataset.accountType || '',
            normal: option?.dataset.normalBalance || '',
            isCashBank: option?.dataset.isCashBank === '1',
        };
    }

    function syncAutoPost() {
        autoPostInput.value = autoPost.classList.contains('on') ? '1' : '0';
    }

    function setAutoPost(value) {
        autoPost.classList.toggle('on', Number(value) === 1);
        syncAutoPost();
    }

    function updateCharCount() {
        charCount.textContent = String(description.value.length);
    }

    function syncAllowedSettlements() {
        const selectedHead = selectedOption(head);
        let allowed = [];

        try {
            allowed = JSON.parse(selectedHead?.dataset.settlements || '[]').map(String);
        } catch (error) {
            allowed = [];
        }

        Array.from(settlement.options).forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const allowedForHead = allowed.length === 0 || allowed.includes(String(option.value));
            option.hidden = !allowedForHead;
            option.disabled = !allowedForHead;
        });

        if (settlement.value && settlement.selectedOptions[0]?.disabled) {
            settlement.value = '';
        }
    }

    function suggestEffect() {
        if (effect.value) {
            return;
        }

        const key = settlementKey();
        const text = headText();
        const dr = accountMeta(debit);
        const cr = accountMeta(credit);

        if (key === 'due') {
            if (dr.type === 'Asset' && cr.type === 'Income') {
                effect.value = 'Increase Receivable';
                return;
            }

            if (cr.type === 'Liability') {
                effect.value = 'Increase Liability';
                return;
            }
        }

        if ((key === 'cash' || key === 'bank') && dr.isCashBank && cr.type === 'Asset' && (text.includes('CUSTOMER') || text.includes('RECEIVED') || text.includes('COLLECTION'))) {
            effect.value = 'Decrease Receivable';
            return;
        }

        if ((key === 'cash' || key === 'bank') && dr.type === 'Liability' && cr.isCashBank) {
            effect.value = 'Decrease Liability';
            return;
        }

        if ((key === 'cash' || key === 'bank') && dr.type === 'Asset' && cr.isCashBank && text.includes('ADVANCE')) {
            effect.value = 'Increase Advance Asset';
            return;
        }

        if ((key === 'cash' || key === 'bank') && dr.isCashBank && cr.type === 'Liability' && text.includes('ADVANCE')) {
            effect.value = 'Increase Advance Liability';
            return;
        }

        if (key === 'advance_paid') {
            effect.value = 'Increase Advance Asset';
        }

        if (key === 'advance_received') {
            effect.value = 'Increase Advance Liability';
        }
    }

    function updatePreview() {
        const dr = accountMeta(debit);
        const cr = accountMeta(credit);
        const key = settlementKey();

        if (!head.value || !settlement.value || !dr.id || !cr.id) {
            mappingPreview.innerHTML = '<strong>Rule preview</strong>Select Transaction Head, Settlement Type, Debit Account, and Credit Account to preview the accounting effect.';
            return;
        }

        const warnings = [];
        const cashBankCount = Number(dr.isCashBank) + Number(cr.isCashBank);

        if (['cash', 'bank', 'advance_paid', 'advance_received'].includes(key)) {
            if (cashBankCount !== 1) {
                warnings.push('Cash/Bank movement must have exactly one Cash/Bank side.');
            }

            const side = expectedCashBankSide();
            if (side === 'Debit' && !dr.isCashBank) warnings.push('This receipt-style rule should debit Cash/Bank.');
            if (side === 'Credit' && !cr.isCashBank) warnings.push('This payment-style rule should credit Cash/Bank.');
        }

        if (['due', 'adjustment'].includes(key) && cashBankCount > 0) {
            warnings.push('Due/Adjustment mapping should not touch Cash/Bank directly.');
        }

        if (dr.id === cr.id) {
            warnings.push('Debit and Credit accounts cannot be the same.');
        }

        mappingPreview.innerHTML = `
            <strong>Rule preview</strong>
            Dr ${dr.label}<br>
            Cr ${cr.label}<br>
            Party effect: ${effect.value || 'Auto - system decides'}
            ${warnings.length ? `<div class="mapping-warning">${warnings.join('<br>')}</div>` : ''}
        `;
    }

    function syncSmartGuidance() {
        syncAllowedSettlements();
        suggestEffect();
        updatePreview();
    }

    function resetForm() {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        formMethod.value = 'POST';
        setAutoPost(1);
        description.value = '';
        effect.value = '';
        updateCharCount();
        syncSmartGuidance();
        head.focus();
    }

    function loadForEdit(row) {
        form.dataset.action = row.dataset.updateUrl;
        formMethod.value = 'PUT';

        head.value = row.dataset.head || '';
        syncAllowedSettlements();
        settlement.value = row.dataset.settlement || '';
        debit.value = row.dataset.debit || '';
        credit.value = row.dataset.credit || '';
        effect.value = row.dataset.effect || '';
        status.value = row.dataset.status || 'Active';
        description.value = row.dataset.description || '';

        setAutoPost(row.dataset.auto || 0);
        updateCharCount();
        updatePreview();

        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Mapping rule loaded for editing.');
    }

    function filter() {
        const q = ruleSearch.value.toLowerCase().trim();
        const h = headFilter.value;
        const s = settlementFilter.value;
        const st = statusFilter.value;
        let visible = 0;
        let total = 0;

        Array.from(tbody.rows).forEach((row) => {
            if (row.dataset.empty === 'true') {
                row.style.display = tbody.rows.length === 1 ? '' : 'none';
                return;
            }

            total++;
            const show = (!q || row.innerText.toLowerCase().includes(q))
                && (h === 'All' || row.dataset.head === h)
                && (s === 'All' || row.dataset.settlement === s)
                && (st === 'All' || row.dataset.status === st);

            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        resultCount.textContent = `Showing ${visible} of ${total} entries`;
    }

    Array.from(tbody.querySelectorAll('.edit-btn')).forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    [ruleSearch, headFilter, settlementFilter, statusFilter].forEach((element) => {
        element.addEventListener('input', filter);
        element.addEventListener('change', filter);
    });

    [head, settlement, debit, credit].forEach((element) => {
        element.addEventListener('change', syncSmartGuidance);
    });

    effect.addEventListener('change', updatePreview);

    addRuleBtn.addEventListener('click', () => {
        resetForm();
        showToast('Ready to add a new mapping rule.');
    });

    cancelBtn.addEventListener('click', () => {
        resetForm();
        showToast('Form cleared.');
    });

    autoPost.addEventListener('click', () => {
        autoPost.classList.toggle('on');
        syncAutoPost();
    });

    description.addEventListener('input', updateCharCount);

    form.addEventListener('submit', (event) => {
        const dr = accountMeta(debit);
        const cr = accountMeta(credit);
        const key = settlementKey();
        const cashBankCount = Number(dr.isCashBank) + Number(cr.isCashBank);

        if (dr.id && cr.id && dr.id === cr.id) {
            event.preventDefault();
            event.stopImmediatePropagation();
            credit.focus();
            showToast('Debit account and credit account cannot be same.');
            return;
        }

        if (['cash', 'bank', 'advance_paid', 'advance_received'].includes(key) && cashBankCount !== 1) {
            event.preventDefault();
            event.stopImmediatePropagation();
            showToast('Cash/Bank movement must include exactly one Cash/Bank ledger side.');
            return;
        }

        if (['due', 'adjustment'].includes(key) && cashBankCount > 0) {
            event.preventDefault();
            event.stopImmediatePropagation();
            showToast('Due and adjustment mapping must not affect Cash/Bank directly.');
        }
    }, true);

    syncAutoPost();
    updateCharCount();
    syncSmartGuidance();
    filter();
});
</script>
@endsection
