@extends('layouts.app')

@section('title', 'Transaction Head Setup | Accounting System')

@section('content')
@php
    $activeHeads = $transactionHeads->where('status', 'Active')->count();
    $systemHeads = $transactionHeads->where('is_system_default', true)->count();
    $partyHeads = $transactionHeads->filter(fn ($head) => ($head->party_required_mode ?: ($head->requires_party ? 'Required' : 'No')) !== 'No')->count();
    $paymentHeads = $transactionHeads->where('payment_method_required', true)->count();
    $salesHeads = $transactionHeads->filter(fn ($head) => str_contains(strtolower((string) ($head->category ?: $head->nature ?: $head->name)), 'sales'))->count();
    $expenseHeads = $transactionHeads->filter(fn ($head) => str_contains(strtolower((string) ($head->category ?: $head->nature ?: $head->name)), 'expense'))->count();
    $screenCount = $transactionHeads->pluck('transaction_screen')->filter()->unique()->count();
@endphp

<div class="prototype-page">
    <div class="prototype-hero">
        <div>
            <span class="page-label">Transaction Head Setup</span>
            <h2>Transaction Head Setup</h2>
            <p>Create business activities, not manual journal rules. Accounting rules will convert these heads into debit and credit postings.</p>
        </div>

        <div class="prototype-actions">
            <button class="btn-outline" type="button" id="addHeadBtn">+ Add New Transaction Head</button>
            <button class="btn-ghost" type="button" data-scroll-target="#headListCard">View All Heads</button>
            <button class="btn-ghost" type="button" data-scroll-target="#mappingPreview">Rule Mapping Preview</button>
            <button class="btn-ghost" type="button" data-scroll-target="#headHelpCard">Help</button>
        </div>
    </div>

    @include('partials.setup-progress', ['current' => 5])

    <div class="prototype-stats six">
        <div class="card prototype-stat"><span>Total Transaction Heads</span><strong>{{ $transactionHeads->count() }}</strong><small>Business activities</small></div>
        <div class="card prototype-stat"><span>Payment Required</span><strong>{{ $paymentHeads }}</strong><small>Needs cash/bank input</small></div>
        <div class="card prototype-stat"><span>Party Required</span><strong>{{ $partyHeads }}</strong><small>Customer / supplier / employee</small></div>
        <div class="card prototype-stat"><span>Sales Heads</span><strong>{{ $salesHeads }}</strong><small>Sales / income activity</small></div>
        <div class="card prototype-stat"><span>Expense Heads</span><strong>{{ $expenseHeads }}</strong><small>Payment / expense activity</small></div>
        <div class="card prototype-stat"><span>Active Heads</span><strong>{{ $activeHeads }}</strong><small>Visible in transaction entry</small></div>
    </div>

    <div class="prototype-grid transaction-head-redesign-grid">
        <div class="card prototype-card">
            <div class="prototype-card-header">
                <div>
                    <h3>Guided Transaction Head Setup</h3>
                    <p>Create business activities, not manual journal rules.</p>
                </div>
                <span class="badge badge-primary">SRS Setup</span>
            </div>

            <div class="prototype-card-body">
                <form
                    id="headForm"
                    class="prototype-form"
                    data-frontend-form
                    data-action="{{ route('api.transaction-heads.store') }}"
                    data-store-url="{{ route('api.transaction-heads.store') }}"
                    data-success="Transaction head saved successfully."
                >
                    @csrf
                    <input type="hidden" name="_method" id="headFormMethod" value="POST">
                    <input type="hidden" name="nature" id="headNature" value="Payment">
                    <input type="hidden" name="requires_party" id="requiresParty" value="0">
                    <input type="hidden" name="requires_reference" id="requiresReference" value="0">
                    <div id="settlementHiddenInputs"></div>

                    <div class="prototype-form-grid two">
                        <div class="prototype-field">
                            <label for="headCode">Transaction Head Code</label>
                            <input id="headCode" name="head_code" placeholder="Auto: TH-001">
                            <div class="hint">Leave blank to auto-generate.</div>
                        </div>

                        <div class="prototype-field">
                            <label for="headName">What business activity are you creating? <span class="required">*</span></label>
                            <input id="headName" name="name" placeholder="Example: Customer Collection" required>
                        </div>

                        <div class="prototype-field">
                            <label for="headCategory">Which category does it belong to? <span class="required">*</span></label>
                            <select id="headCategory" name="category" required>
                                <option value="">Select category</option>
                                <option value="Sales">Sales</option>
                                <option value="Purchase">Purchase</option>
                                <option value="Receipt">Receipt</option>
                                <option value="Payment">Payment</option>
                                <option value="Expense Payment">Expense Payment</option>
                                <option value="Due">Due</option>
                                <option value="Advance">Advance</option>
                                <option value="Adjustment">Adjustment</option>
                                <option value="Other">Other / Journal</option>
                            </select>
                            <div class="hint">Used by the accounting engine and transaction screen filter.</div>
                        </div>

                        <div class="prototype-field">
                            <label for="transactionScreen">Which transaction screen will use this?</label>
                            <input id="transactionScreen" name="transaction_screen" placeholder="Daily Transaction Entry">
                            <div class="hint">Example: Sales Entry, Payment Entry, Transaction Entry.</div>
                        </div>

                        <div class="prototype-field">
                            <label for="defaultLedger">Which ledger is mainly affected?</label>
                            <select id="defaultLedger" name="default_primary_ledger_id">
                                <option value="">Rule-selected / user-selected</option>
                                @foreach($postingLedgers as $ledger)
                                    <option value="{{ $ledger->id }}">{{ $ledger->display_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="defaultMovement">Does this transaction increase or decrease the selected ledger? <span class="required">*</span></label>
                            <select id="defaultMovement" name="default_movement" required>
                                <option value="Increase">Increase</option>
                                <option value="Decrease">Decrease</option>
                                <option value="No Movement">No Movement</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="paymentRequired">Does the user need to select cash or bank? <span class="required">*</span></label>
                            <select id="paymentRequired" name="payment_method_required" required>
                                <option value="1">Yes</option>
                                <option value="0" selected>No</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="partyRequiredMode">Does the user need to select a party? <span class="required">*</span></label>
                            <select id="partyRequiredMode" name="party_required_mode" required>
                                <option value="No">No</option>
                                <option value="Optional">Optional</option>
                                <option value="Required">Required</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="partyType">Party type</label>
                            <select id="partyType" name="default_party_type_id" data-dropdown="/api/dropdowns/party-types" data-placeholder="Select Party Type"></select>
                            <div class="hint">Restricts party list in transaction entry.</div>
                        </div>

                        <div class="prototype-field">
                            <label for="headStatus">Is Active?</label>
                            <select id="headStatus" name="status" required>
                                <option value="Active">Yes - Active</option>
                                <option value="Inactive">No - Inactive</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="systemDefault">Is System Default?</label>
                            <select id="systemDefault" name="is_system_default">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="userSelectable">Allow user selection in transaction entry?</label>
                            <select id="userSelectable" name="is_user_selectable">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="sortOrder">Sort order</label>
                            <input id="sortOrder" name="sort_order" type="number" min="0" step="1" placeholder="Auto">
                        </div>

                        <div class="prototype-field">
                            <label for="linkedRuleCode">Linked Accounting Rule Code</label>
                            <input id="linkedRuleCode" name="linked_accounting_rule_code" placeholder="Example: AR-001">
                        </div>

                        <div class="prototype-field full">
                            <label>Allowed Settlement Types <span class="required">*</span></label>
                            <div class="multi-select prototype-chip-box" id="settlementTypeChips" data-selected-count="0">
                                @foreach($settlementTypes as $settlementType)
                                    <span class="select-chip" tabindex="0" role="button" data-id="{{ $settlementType->id }}" data-value="{{ $settlementType->id }}">
                                        {{ $settlementType->name }}
                                    </span>
                                @endforeach
                            </div>
                            <div class="hint">Choose the payment/settlement modes that can be used with this head.</div>
                        </div>

                        <div class="prototype-field full">
                            <label for="description">Short explanation for users</label>
                            <textarea id="description" name="description" placeholder="Explain when users should select this transaction head."></textarea>
                        </div>

                        <div class="prototype-field full">
                            <label for="helpText">Help text for transaction entry</label>
                            <textarea id="helpText" name="help_text" placeholder="Guidance shown to the transaction entry user."></textarea>
                        </div>

                        <div class="prototype-field full">
                            <label for="developerNote">Notes for developer</label>
                            <textarea id="developerNote" name="developer_note" placeholder="Internal implementation note or rule dependency."></textarea>
                        </div>
                    </div>

                    <div class="prototype-form-actions">
                        <button type="button" class="btn-ghost" id="cancelHeadBtn">Clear</button>
                        <button type="submit" class="btn-primary">Save Transaction Head</button>
                    </div>
                </form>
            </div>
        </div>

        <aside class="prototype-side-stack">
            <div class="card prototype-guidance-card" id="headHelpCard">
                <div class="prototype-guidance-icon">💡</div>
                <strong>Quick Help</strong>
                <p>Transaction Head is the user-facing business activity. The Accounting Rule decides which ledger will be debited and credited.</p>
                <ul>
                    <li>Use simple names like Customer Collection or Supplier Payment.</li>
                    <li>Keep party mode aligned with Customer/Supplier/Employee logic.</li>
                    <li>Use settlement types that have accounting rules configured.</li>
                </ul>
            </div>

            <div class="card prototype-preview-card" id="mappingPreview">
                <h3>Current Form Meaning</h3>
                <div class="prototype-preview-list">
                    <div><span>Category</span><strong id="previewCategory">—</strong></div>
                    <div><span>Nature</span><strong id="previewNature">—</strong></div>
                    <div><span>Party</span><strong id="previewParty">No</strong></div>
                    <div><span>Payment</span><strong id="previewPayment">No</strong></div>
                    <div><span>Selectable</span><strong id="previewSelectable">Yes</strong></div>
                </div>
            </div>
        </aside>
    </div>

    <section class="card prototype-card prototype-section" id="headListCard">
        <div class="prototype-card-header">
            <div>
                <h3>Transaction Head List</h3>
                <p>Search, filter, edit, and review available business activities.</p>
            </div>
            <span class="badge badge-neutral" id="resultCount">Showing {{ $transactionHeads->count() }} of {{ $transactionHeads->count() }} entries</span>
        </div>

        <div class="prototype-card-body">
            <div class="prototype-filter-grid" data-table-filter="#headTable" data-count-target="#resultCount">
                <div class="field search-field">
                    <span>⌕</span>
                    <input placeholder="Search transaction heads..." data-filter-key="text">
                </div>
                <div>
                    <label>Category</label>
                    <select data-filter-key="category">
                        <option value="">All Categories</option>
                        <option value="Sales">Sales</option>
                        <option value="Purchase">Purchase</option>
                        <option value="Receipt">Receipt</option>
                        <option value="Payment">Payment</option>
                        <option value="Expense Payment">Expense Payment</option>
                        <option value="Due">Due</option>
                        <option value="Advance">Advance</option>
                        <option value="Adjustment">Adjustment</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select data-filter-key="status">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="table-wrap prototype-table-wrap always-scroll">
                <table id="headTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Transaction Head</th>
                            <th>Category</th>
                            <th>Main Ledger / CoA Code</th>
                            <th>Movement</th>
                            <th>Payment Required</th>
                            <th>Party Required</th>
                            <th>Party Type</th>
                            <th>Transaction Screen</th>
                            <th>Status</th>
                            <th style="text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactionHeads as $head)
                            @php
                                $partyMode = $head->party_required_mode ?: ($head->requires_party ? 'Required' : 'No');
                                $category = $head->category ?: $head->nature;
                                $settlementIds = $head->settlementTypes->pluck('id')->map(fn ($id) => (string) $id)->values();
                            @endphp
                            <tr
                                data-id="{{ $head->id }}"
                                data-text="{{ e(trim(($head->head_code ? $head->head_code . ' ' : '') . $head->name . ' ' . $category . ' ' . ($head->transaction_screen ?: ''))) }}"
                                data-head-code="{{ e($head->head_code) }}"
                                data-name="{{ e($head->name) }}"
                                data-nature="{{ e($head->nature) }}"
                                data-category="{{ e($category) }}"
                                data-default-primary-ledger-id="{{ $head->default_primary_ledger_id }}"
                                data-default-movement="{{ e($head->default_movement ?: 'Increase') }}"
                                data-payment-method-required="{{ $head->payment_method_required ? 1 : 0 }}"
                                data-party-required-mode="{{ e($partyMode) }}"
                                data-default-party-type-id="{{ $head->default_party_type_id }}"
                                data-requires-reference="{{ $head->requires_reference ? 1 : 0 }}"
                                data-transaction-screen="{{ e($head->transaction_screen) }}"
                                data-description="{{ e($head->description) }}"
                                data-help-text="{{ e($head->help_text) }}"
                                data-developer-note="{{ e($head->developer_note) }}"
                                data-is-system-default="{{ $head->is_system_default ? 1 : 0 }}"
                                data-is-user-selectable="{{ $head->is_user_selectable ? 1 : 0 }}"
                                data-sort-order="{{ $head->sort_order }}"
                                data-linked-accounting-rule-code="{{ e($head->linked_accounting_rule_code) }}"
                                data-settlement-ids='@json($settlementIds)'
                                data-status="{{ e($head->status) }}"
                                data-update-url="{{ route('api.transaction-heads.update', $head) }}"
                            >
                                <td class="code">{{ $head->head_code ?: '—' }}</td>
                                <td class="strong">{{ $head->name }}</td>
                                <td><span class="badge badge-primary">{{ $category }}</span></td>
                                <td class="{{ $head->defaultPrimaryLedger ? '' : 'muted' }}">
                                    {{ $head->defaultPrimaryLedger?->display_name ?? 'Rule-selected' }}
                                </td>
                                <td>{{ $head->default_movement ?: 'Increase' }}</td>
                                <td>{{ $head->payment_method_required ? 'Yes' : 'No' }}</td>
                                <td>{{ $partyMode }}</td>
                                <td>{{ $head->defaultPartyType?->name ?? '—' }}</td>
                                <td>{{ $head->transaction_screen ?: 'Transaction Entry' }}</td>
                                <td><span class="badge {{ $head->status === 'Active' ? 'badge-success' : 'badge-neutral' }}">{{ $head->status }}</span></td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn edit-btn" type="button" title="Edit">✎</button>
                                        <form method="POST" data-delete-form action="{{ route('setup.transaction-heads.destroy', $head) }}" onsubmit="return confirm('Delete this transaction head?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty="true"><td colspan="11" class="muted" style="text-align:center;padding:24px">No transaction heads found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('headForm');
    if (!form) return;

    const methodInput = document.getElementById('headFormMethod');
    const natureInput = document.getElementById('headNature');
    const settlementHidden = document.getElementById('settlementHiddenInputs');
    const settlementBox = document.getElementById('settlementTypeChips');
    const addButton = document.getElementById('addHeadBtn');
    const cancelButton = document.getElementById('cancelHeadBtn');

    const fields = {
        head_code: form.querySelector('[name="head_code"]'),
        name: form.querySelector('[name="name"]'),
        category: form.querySelector('[name="category"]'),
        default_primary_ledger_id: form.querySelector('[name="default_primary_ledger_id"]'),
        default_movement: form.querySelector('[name="default_movement"]'),
        payment_method_required: form.querySelector('[name="payment_method_required"]'),
        party_required_mode: form.querySelector('[name="party_required_mode"]'),
        default_party_type_id: form.querySelector('[name="default_party_type_id"]'),
        transaction_screen: form.querySelector('[name="transaction_screen"]'),
        status: form.querySelector('[name="status"]'),
        is_system_default: form.querySelector('[name="is_system_default"]'),
        is_user_selectable: form.querySelector('[name="is_user_selectable"]'),
        sort_order: form.querySelector('[name="sort_order"]'),
        linked_accounting_rule_code: form.querySelector('[name="linked_accounting_rule_code"]'),
        description: form.querySelector('[name="description"]'),
        help_text: form.querySelector('[name="help_text"]'),
        developer_note: form.querySelector('[name="developer_note"]'),
    };

    const preview = {
        category: document.getElementById('previewCategory'),
        nature: document.getElementById('previewNature'),
        party: document.getElementById('previewParty'),
        payment: document.getElementById('previewPayment'),
        selectable: document.getElementById('previewSelectable'),
    };

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }
        alert(message);
    }

    function natureFromCategory(category) {
        switch (category) {
            case 'Sales':
            case 'Receipt':
                return 'Receipt';
            case 'Purchase':
            case 'Due':
                return 'Due';
            case 'Expense Payment':
                return 'Expense';
            case 'Advance':
                return 'Advance';
            case 'Adjustment':
                return 'Adjustment';
            case 'Other':
                return 'Journal';
            default:
                return 'Payment';
        }
    }

    function selectedSettlementIds() {
        return Array.from(settlementBox.querySelectorAll('.select-chip.selected'))
            .map((chip) => chip.dataset.id || chip.dataset.value)
            .filter(Boolean);
    }

    function syncSettlementHidden() {
        const ids = selectedSettlementIds();
        settlementHidden.innerHTML = '';
        ids.forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'settlement_type_ids[]';
            input.value = id;
            settlementHidden.appendChild(input);
        });
        settlementBox.dataset.selectedCount = String(ids.length);
    }

    function setSettlementIds(ids) {
        const selected = (Array.isArray(ids) ? ids : []).map(String);
        settlementBox.querySelectorAll('.select-chip').forEach((chip) => {
            chip.classList.toggle('selected', selected.includes(String(chip.dataset.id || chip.dataset.value)));
        });
        syncSettlementHidden();
    }

    function syncDerivedFields() {
        const category = fields.category.value || '';
        const nature = natureFromCategory(category);
        natureInput.value = nature;
        document.getElementById('requiresParty').value = fields.party_required_mode.value === 'No' ? '0' : '1';
        preview.category.textContent = category || '—';
        preview.nature.textContent = nature;
        preview.party.textContent = fields.party_required_mode.value || 'No';
        preview.payment.textContent = fields.payment_method_required.value === '1' ? 'Yes' : 'No';
        preview.selectable.textContent = fields.is_user_selectable.value === '1' ? 'Yes' : 'No';
    }

    function setDropdownValue(select, value) {
        if (!select) return;
        select.dataset.selected = value || '';
        select.value = value || '';
        if (select.dataset.dropdown && window.AccountingUI?.loadSelect) {
            window.AccountingUI.loadSelect(select).then(() => {
                select.value = value || '';
            });
        }
    }

    function resetForm() {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';
        fields.payment_method_required.value = '0';
        fields.party_required_mode.value = 'No';
        fields.default_movement.value = 'Increase';
        fields.status.value = 'Active';
        fields.is_system_default.value = '0';
        fields.is_user_selectable.value = '1';
        setDropdownValue(fields.default_party_type_id, '');
        setSettlementIds([]);
        syncDerivedFields();
        fields.name.focus();
    }

    function loadForEdit(row) {
        let settlementIds = [];
        try { settlementIds = JSON.parse(row.dataset.settlementIds || '[]'); } catch (error) { settlementIds = []; }

        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';
        fields.head_code.value = row.dataset.headCode || '';
        fields.name.value = row.dataset.name || '';
        fields.category.value = row.dataset.category || '';
        fields.default_primary_ledger_id.value = row.dataset.defaultPrimaryLedgerId || '';
        fields.default_movement.value = row.dataset.defaultMovement || 'Increase';
        fields.payment_method_required.value = row.dataset.paymentMethodRequired || '0';
        fields.party_required_mode.value = row.dataset.partyRequiredMode || 'No';
        setDropdownValue(fields.default_party_type_id, row.dataset.defaultPartyTypeId || '');
        fields.transaction_screen.value = row.dataset.transactionScreen || '';
        fields.status.value = row.dataset.status || 'Active';
        fields.is_system_default.value = row.dataset.isSystemDefault || '0';
        fields.is_user_selectable.value = row.dataset.isUserSelectable || '1';
        fields.sort_order.value = row.dataset.sortOrder || '';
        fields.linked_accounting_rule_code.value = row.dataset.linkedAccountingRuleCode || '';
        fields.description.value = row.dataset.description || '';
        fields.help_text.value = row.dataset.helpText || '';
        fields.developer_note.value = row.dataset.developerNote || '';
        setSettlementIds(settlementIds);
        syncDerivedFields();
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        showToast('Transaction head loaded for editing.');
    }

    settlementBox.querySelectorAll('.select-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            chip.classList.toggle('selected');
            syncSettlementHidden();
        });
        chip.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                chip.click();
            }
        });
    });

    ['change', 'input'].forEach((eventName) => {
        [fields.category, fields.party_required_mode, fields.payment_method_required, fields.is_user_selectable].forEach((field) => {
            field.addEventListener(eventName, syncDerivedFields);
        });
    });

    document.querySelectorAll('#headTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });


    form.addEventListener('submit', (event) => {
        syncSettlementHidden();
        if (selectedSettlementIds().length === 0) {
            event.preventDefault();
            event.stopImmediatePropagation();
            showToast('Select at least one allowed settlement type.');
            settlementBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, true);


    document.querySelectorAll('[data-scroll-target]').forEach((button) => {
        button.addEventListener('click', () => document.querySelector(button.dataset.scrollTarget)?.scrollIntoView({ behavior: 'smooth' }));
    });

    addButton?.addEventListener('click', () => {
        resetForm();
        showToast('Ready to add a new transaction head.');
    });

    cancelButton?.addEventListener('click', resetForm);

    syncSettlementHidden();
    syncDerivedFields();
});
</script>
@endpush
