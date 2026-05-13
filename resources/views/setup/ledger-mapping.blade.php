@extends('layouts.app')

@section('title', 'Ledger Mapping Setup | Accounting System')

@push('styles')
<style>
    .ledger-mapping-page .table-wrap {
        overflow-x: auto;
    }

    .ledger-mapping-page .panel-head {
        align-items: flex-start;
        gap: 12px;
    }

    .ledger-mapping-page .panel-head h3 {
        margin-bottom: 4px;
    }

    .ledger-side-note {
        display: block;
        margin-top: 4px;
        color: var(--muted);
        font-size: 12px;
        line-height: 1.45;
    }

    .ledger-mapping-page .actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        padding-top: 14px;
        border-top: 1px solid var(--line);
    }

    .ledger-mapping-page .actions .btn-primary {
        grid-column: span 2;
    }

    @media (max-width: 880px) {
        .ledger-mapping-page .actions {
            grid-template-columns: 1fr;
        }

        .ledger-mapping-page .actions .btn-primary {
            grid-column: span 1;
        }
    }
</style>
@endpush

@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Ledger Mapping</span>
        <h2>Ledger Mapping</h2>
        <p>Configure automatic debit and credit rules for each transaction.</p>
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
                        <option value="{{ $transactionHead->id }}">
                            {{ $transactionHead->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Settlement Type</label>
                <select id="settlementFilter">
                    <option value="All">All</option>
                    @foreach($settlementTypes as $settlementType)
                        <option value="{{ $settlementType->id }}">
                            {{ $settlementType->name }}
                        </option>
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

            <button class="btn-primary" type="button" id="addRuleBtn">
                + Add Mapping Rule
            </button>
        </div>

        <div class="card table-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Transaction Head</th>
                            <th>Settlement Type</th>
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
                                <td class="rule-name">
                                    {{ $rule->transactionHead?->name ?? '—' }}
                                </td>

                                <td>
                                    {{ $rule->settlementType?->name ?? '—' }}
                                </td>

                                <td>
                                    {{ $rule->debitAccount?->display_name ?? $rule->debitAccount?->account_name ?? '—' }}
                                </td>

                                <td>
                                    {{ $rule->creditAccount?->display_name ?? $rule->creditAccount?->account_name ?? '—' }}
                                </td>

                                <td>
                                    {{ $rule->party_ledger_effect }}
                                </td>

                                <td>
                                    <span class="switch {{ $rule->auto_post ? 'on' : '' }}"></span>
                                </td>

                                <td>
                                    <span class="badge {{ $rule->status === 'Active' ? 'badge-active' : 'badge-neutral' }}">
                                        {{ $rule->status }}
                                    </span>
                                </td>

                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn edit-btn" type="button" title="Edit">
                                            ✎
                                        </button>

                                        <form
                                            method="POST"
                                            data-delete-form
                                            action="{{ url('/setup/ledger-mapping/' . $rule->id) }}"
                                            onsubmit="return confirm('Delete this mapping rule?')"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button class="icon-btn delete-btn" type="submit" title="Delete">
                                                🗑
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty="true">
                                <td colspan="8" style="text-align:center;padding:24px;color:var(--muted)">
                                    No ledger mapping rules found. Add your first mapping rule.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span id="resultCount">
                    Showing {{ $rules->count() }} of {{ $rules->count() }} entries
                </span>

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
                    <span class="ledger-side-note">Add or update debit and credit posting rules.</span>
                </div>

                <span class="badge badge-primary">Step 6</span>
            </div>

            <form
                class="form-grid"
                id="ruleForm"
                data-frontend-form
                data-accounting-rule-form
                data-action="{{ url('/api/ledger-mapping') }}"
                data-store-url="{{ url('/api/ledger-mapping') }}"
                data-success="Mapping rule saved successfully."
            >
                @csrf

                <input type="hidden" name="_method" id="formMethod" value="POST">

                <div>
                    <label>Transaction Head <span class="required">*</span></label>
                    <select id="head" name="transaction_head_id" required>
                        <option value="">Select Transaction Head</option>
                        @foreach($transactionHeads as $transactionHead)
                            <option value="{{ $transactionHead->id }}">
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
                            <option value="{{ $settlementType->id }}">
                                {{ $settlementType->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Debit Account <span class="required">*</span></label>
                    <select id="debit" name="debit_account_id" required>
                        <option value="">Select Debit Account</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">
                                {{ $account->display_name ?? $account->account_code . ' - ' . $account->account_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Credit Account <span class="required">*</span></label>
                    <select id="credit" name="credit_account_id" required>
                        <option value="">Select Credit Account</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">
                                {{ $account->display_name ?? $account->account_code . ' - ' . $account->account_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Party Ledger Effect</label>
                    <select id="effect" name="party_ledger_effect">
                        @foreach($partyEffects as $effect)
                            <option value="{{ $effect }}">
                                {{ $effect }}
                            </option>
                        @endforeach
                    </select>

                    <div class="inline-help">
                        How this transaction affects the party ledger
                    </div>
                </div>

                <div>
                    <label>Auto Post</label>
                    <br>

                    <input type="hidden" id="autoPostInput" name="auto_post" value="1">
                    <span class="switch on" id="autoPost" data-input="autoPostInput"></span>

                    <div class="inline-help">
                        Automatically post transactions using this rule
                    </div>
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
                    <textarea
                        id="description"
                        name="description"
                        maxlength="250"
                        placeholder="Example: Salary Payment + Cash creates Dr Salary Expense and Cr Cash."
                    ></textarea>

                    <div class="inline-help">
                        <span id="charCount">0</span>/250 characters
                    </div>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost" id="cancelBtn">
                        Cancel
                    </button>

                    <button type="submit" class="btn-outline" id="saveBtn">
                        Save
                    </button>

                    <button type="submit" class="btn-primary" id="saveNextBtn">
                        Save & Next ›
                    </button>
                </div>
            </form>
        </div>

        <div class="card helper-card">
            <h3>How Mapping Works</h3>

            <p>
                Every combination of Transaction Head and Settlement Type maps to a Debit and Credit account.
            </p>

            <div class="flow">
                <div class="flow-row">
                    <span class="flow-chip blue">Transaction Head</span>
                    <strong>+</strong>
                    <span class="flow-chip purple">Settlement Type</span>
                </div>

                <div class="arrow">↓</div>

                <div class="flow-row">
                    <span class="flow-chip green-chip">Debit Account</span>
                    <strong>/</strong>
                    <span class="flow-chip yellow">Credit Account</span>
                </div>
            </div>

            <div class="example">
                <strong>Example</strong><br>
                Salary Payment + Cash → Salary Expense / Cash
            </div>
        </div>

        <div class="card why-card">
            <div class="why-icon">💡</div>

            <div>
                <h3>Why is this important?</h3>
                <p>
                    Accurate mapping ensures every transaction is posted to the right accounts automatically.
                </p>
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

    function resetForm() {
        form.reset();

        form.dataset.action = form.dataset.storeUrl;
        formMethod.value = 'POST';

        setAutoPost(1);
        description.value = '';
        updateCharCount();

        head.focus();
    }

    function loadForEdit(row) {
        form.dataset.action = row.dataset.updateUrl;
        formMethod.value = 'PUT';

        head.value = row.dataset.head || '';
        settlement.value = row.dataset.settlement || '';
        debit.value = row.dataset.debit || '';
        credit.value = row.dataset.credit || '';
        effect.value = row.dataset.effect || 'No Effect';
        status.value = row.dataset.status || 'Active';
        description.value = row.dataset.description || '';

        setAutoPost(row.dataset.auto || 0);
        updateCharCount();

        form.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });

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

            const show =
                (!q || row.innerText.toLowerCase().includes(q)) &&
                (h === 'All' || row.dataset.head === h) &&
                (s === 'All' || row.dataset.settlement === s) &&
                (st === 'All' || row.dataset.status === st);

            row.style.display = show ? '' : 'none';

            if (show) {
                visible++;
            }
        });

        resultCount.textContent = `Showing ${visible} of ${total} entries`;
    }

    Array.from(tbody.querySelectorAll('.edit-btn')).forEach((button) => {
        button.addEventListener('click', () => {
            loadForEdit(button.closest('tr'));
        });
    });

    [ruleSearch, headFilter, settlementFilter, statusFilter].forEach((element) => {
        element.addEventListener('input', filter);
        element.addEventListener('change', filter);
    });

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
        if (debit.value && credit.value && debit.value === credit.value) {
            event.preventDefault();
            credit.focus();
            showToast('Debit account and credit account cannot be same.');
        }
    });

    syncAutoPost();
    updateCharCount();
    filter();
});
</script>
@endsection
