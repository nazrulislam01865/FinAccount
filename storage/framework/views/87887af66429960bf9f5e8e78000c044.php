<?php $__env->startSection('title', 'Cash / Bank Account Setup | HisebGhor'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .setup-full-width-flow {
        grid-template-columns: 1fr !important;
        gap: 18px;
    }

    .setup-full-width-flow > .right-stack {
        order: 1;
        display: grid;
        grid-template-columns: 1fr !important;
        gap: 18px;
        width: 100%;
    }

    .setup-full-width-flow > .left-stack {
        order: 2;
        width: 100%;
    }

    .setup-full-width-flow .form-panel,
    .setup-full-width-flow .form-card,
    .setup-full-width-flow .toolbar,
    .setup-full-width-flow .table-card,
    .setup-full-width-flow .table-info-grid {
        width: 100%;
    }

    .setup-full-width-flow .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 15px;
        align-items: start;
    }

    .setup-full-width-flow .form-grid > .two-col,
    .setup-full-width-flow .form-grid > .hint-box,
    .setup-full-width-flow .form-grid > .form-actions,
    .setup-full-width-flow .form-grid > .actions,
    .setup-full-width-flow .form-grid > .preview-box,
    .setup-full-width-flow .form-grid > #bankFields {
        grid-column: 1 / -1;
    }

    .setup-full-width-flow .form-actions,
    .setup-full-width-flow .actions {
        margin-top: 3px;
    }

    .setup-full-width-flow .toolbar {
        margin-top: 0;
    }

    .setup-full-width-flow .table-card {
        overflow-x: auto;
    }

    .setup-full-width-flow .table-wrap {
        width: 100%;
        overflow-x: scroll;
        scrollbar-gutter: stable both-edges;
    }

    .setup-full-width-flow table {
        min-width: 1100px;
    }

    .setup-full-width-flow #bankFields {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 15px;
    }

    .setup-full-width-flow #bankFields.hidden,
    .setup-full-width-flow #bankFields .hidden {
        display: none !important;
    }

    @media (max-width: 880px) {
        .setup-full-width-flow .form-grid,
        .setup-full-width-flow #bankFields {
            grid-template-columns: 1fr;
        }
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="page-title">
    <div>
        <span class="page-label">Cash / Bank Account Setup</span>
        <h2>Cash / Bank Account Setup</h2>
        <p>Configure cash boxes, bank accounts, and mobile financial accounts used for payment and receipt.</p>
    </div>
</div>

<?php echo $__env->make('partials.setup-progress', ['current' => 3], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="layout setup-full-width-flow">
    <div class="left-stack">
        <div class="card toolbar" data-table-filter="#cashBankTable" data-count-target="#resultCount">
            <div class="field search-field">
                <span>⌕</span>
                <input
                    type="text"
                    placeholder="Search cash/bank accounts..."
                    data-filter-key="text"
                >
            </div>

            <div>
                <label>Type</label>
                <select
                    data-filter-key="type"
                    data-dropdown="/api/dropdowns/cash-bank-account-types"
                    data-placeholder="All Types"
                ></select>
            </div>

            <div>
                <label>Status</label>
                <select data-filter-key="status">
                    <option value="">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <button
                class="btn-primary"
                type="button"
                id="addCashBankBtn"
            >
                + Add Cash / Bank
            </button>
        </div>

        <div class="card table-card">
            <table id="cashBankTable" data-client-pagination="true" data-page-size="10">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Linked Ledger Account</th>
                        <th>Bank / Provider</th>
                        <th>Branch</th>
                        <th>Account / Wallet No.</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr
                            data-id="<?php echo e($account->id); ?>"
                            data-code="<?php echo e(e($account->cash_bank_code)); ?>"
                            data-name="<?php echo e(e($account->cash_bank_name)); ?>"
                            data-type="<?php echo e($account->type); ?>"
                            data-linked-ledger="<?php echo e($account->linked_ledger_account_id); ?>"
                            data-bank-name="<?php echo e(e($account->bank_name ?? $account->bank?->bank_name)); ?>"
                            data-branch="<?php echo e(e($account->branch_name)); ?>"
                            data-account-number="<?php echo e(e($account->account_number)); ?>"
                            data-usage-note="<?php echo e(e($account->usage_note)); ?>"
                            data-status="<?php echo e($account->status); ?>"
                            data-update-url="<?php echo e(url('/api/cash-bank-accounts/' . $account->id)); ?>"
                        >
                            <td class="<?php echo e($account->cash_bank_code ? 'code' : 'muted'); ?>">
                                <?php echo e($account->cash_bank_code ?: '—'); ?>

                            </td>

                            <td class="strong"><?php echo e($account->cash_bank_name); ?></td>

                            <td>
                                <span class="badge badge-blue"><?php echo e($account->type); ?></span>
                            </td>

                            <td>
                                <?php echo e($account->linkedLedger?->display_name ?? '—'); ?>


                                <?php if($account->linkedLedger?->accountType): ?>
                                    <div class="hint" style="margin-top:2px">
                                        <?php echo e($account->linkedLedger->accountType->name); ?>

                                        · <?php echo e($account->linkedLedger->normal_balance ?: $account->linkedLedger->accountType->normal_balance); ?>

                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="<?php echo e(($account->bank_name ?? $account->bank?->bank_name) ? '' : 'muted'); ?>">
                                <?php echo e($account->bank_name ?? $account->bank?->display_name ?? $account->bank?->bank_name ?? '—'); ?>

                            </td>

                            <td class="<?php echo e($account->branch_name ? '' : 'muted'); ?>">
                                <?php echo e($account->branch_name ?: '—'); ?>

                            </td>

                            <td class="<?php echo e($account->account_number ? '' : 'muted'); ?>">
                                <?php echo e($account->account_number ?: '—'); ?>

                            </td>


                            <td>
                                <span class="badge <?php echo e($account->status === 'Active' ? 'badge-success' : 'badge-neutral'); ?>">
                                    <?php echo e($account->status); ?>

                                </span>
                            </td>

                            <td>
                                <div class="action-cell">
                                    <button
                                        class="icon-btn edit-btn"
                                        type="button"
                                        title="Edit"
                                    >
                                        ✎
                                    </button>

                                    <form
                                        method="POST"
                                        data-delete-form
                                        action="<?php echo e(url('/setup/cash-bank-accounts/' . $account->id)); ?>"
                                        onsubmit="return confirm('Delete this cash/bank account?')"
                                    >
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>

                                        <button class="icon-btn delete-btn" type="submit" title="Delete">
                                            🗑
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr data-empty="true">
                            <td colspan="9" class="muted" style="text-align:center;padding:24px">
                                No cash/bank accounts found. Add your first account using the form above.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="table-footer">
                <span id="resultCount">Showing <?php echo e($accounts->count()); ?> of <?php echo e($accounts->count()); ?> entries</span>

                <div class="pagination">
                    <button class="page-btn" type="button">‹</button>
                    <button class="page-btn active" type="button">1</button>
                    <button class="page-btn" type="button">›</button>
                </div>
            </div>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card form-panel">
            <div class="panel-head">
                <h3 id="cashBankFormTitle">Create Cash / Bank Account</h3>
                <span class="muted">Required fields marked *</span>
            </div>

            <form
                class="form-grid"
                id="cashBankForm"
                data-frontend-form
                data-action="<?php echo e(route('api.cash-bank-accounts.store')); ?>"
                data-store-url="<?php echo e(route('api.cash-bank-accounts.store')); ?>"
                data-success="Cash / Bank account saved successfully."
            >
                <?php echo csrf_field(); ?>

                <input type="hidden" name="_method" id="cashBankFormMethod" value="POST">

                <div>
                    <label>Cash/Bank Code</label>
                    <input
                        name="cash_bank_code"
                        maxlength="30"
                        placeholder="Auto generated if blank"
                    >
                    <div class="hint">Example: CB-001, BK-001, MB-001.</div>
                </div>

                <div>
                    <label>Cash/Bank Account Name <span class="required">*</span></label>
                    <input
                        name="cash_bank_name"
                        placeholder="Auto-filled from linked ledger if blank"
                        required
                    >
                    <div class="hint">This name appears in Paid From / Received In dropdowns.</div>
                </div>

                <div>
                    <label>Type <span class="required">*</span></label>
                    <select
                        id="cashBankType"
                        name="type"
                        required
                        data-dropdown="/api/dropdowns/cash-bank-account-types"
                        data-placeholder="Select Type"
                    ></select>
                </div>

                <div>
                    <label>Linked Ledger Account <span class="required">*</span></label>
                    <select
                        id="linkedLedger"
                        name="linked_ledger_account_id"
                        required
                        data-base-url="/api/dropdowns/cash-bank-ledgers"
                        data-dropdown="/api/dropdowns/cash-bank-ledgers"
                        data-label="account"
                        data-placeholder="Select Asset cash/bank ledger"
                    ></select>
                    <div class="hint">
                        Only active Asset ledger accounts marked as Cash/Bank are shown. Each ledger can be linked once.
                    </div>
                </div>

                <div id="bankFields">
                    <div class="bank-detail-row" id="bankNameRow">
                        <label>Bank / Provider Name <span class="required bank-required">*</span></label>
                        <input
                            id="bankId"
                            name="bank_name"
                            placeholder="Example: BRAC Bank PLC / bKash"
                        >
                        <div class="hint">Required for Bank. Optional for Mobile Banking.</div>
                    </div>

                    <div class="bank-detail-row" id="branchRow">
                        <label>Branch Name</label>
                        <input
                            name="branch_name"
                            placeholder="Enter branch name"
                        >
                    </div>

                    <div class="bank-detail-row" id="accountNumberRow">
                        <label>Account / Wallet Number</label>
                        <input
                            name="account_number"
                            type="text"
                            placeholder="Enter account, card, or wallet number"
                        >
                        <div class="hint">Optional text field. Keep exact bank/MFS format.</div>
                    </div>
                </div>


                <div>
                    <label>Usage Note</label>
                    <input
                        name="usage_note"
                        maxlength="255"
                        placeholder="Example: Used for daily cash payments"
                    >
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="hint-box">
                    <strong>Accounting safety rules</strong>
                    Cash/Bank setup only links valid Asset cash/bank ledgers. It does not create debit or credit entries.
                </div>

                <div class="form-actions">
                    <button
                        type="button"
                        class="btn-ghost"
                        id="cancelCashBankBtn"
                    >
                        Cancel
                    </button>

                    <button type="submit" class="btn-primary">
                        Save Cash / Bank
                    </button>
                </div>
            </form>
        </div>
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('cashBankForm');

    if (!form) {
        return;
    }

    const title = document.getElementById('cashBankFormTitle');
    const methodInput = document.getElementById('cashBankFormMethod');
    const addButton = document.getElementById('addCashBankBtn');
    const cancelButton = document.getElementById('cancelCashBankBtn');

    const code = form.querySelector('[name="cash_bank_code"]');
    const name = form.querySelector('[name="cash_bank_name"]');
    const type = document.getElementById('cashBankType');
    const linkedLedger = document.getElementById('linkedLedger');
    const bank = form.querySelector('[name="bank_name"]');
    const branch = form.querySelector('[name="branch_name"]');
    const accountNumber = form.querySelector('[name="account_number"]');
    const usageNote = form.querySelector('[name="usage_note"]');
    const status = form.querySelector('[name="status"]');
    const bankFields = document.getElementById('bankFields');
    const bankDetailRows = form.querySelectorAll('.bank-detail-row');
    const branchRow = document.getElementById('branchRow');

    let isEditing = false;

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        alert(message);
    }

    function selectedLedgerOption() {
        return linkedLedger?.selectedOptions?.[0] || null;
    }

    function setDropdownValue(select, value) {
        if (!select) {
            return Promise.resolve();
        }

        select.dataset.selected = value || '';
        select.value = value || '';

        if (select.dataset.dropdown && window.AccountingUI?.loadSelect) {
            return window.AccountingUI.loadSelect(select).then(() => {
                select.value = value || '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        select.dispatchEvent(new Event('change', { bubbles: true }));
        return Promise.resolve();
    }

    function inferTypeFromLedgerName() {
        const option = selectedLedgerOption();
        const ledgerName = String(option?.dataset.accountName || option?.textContent || '').toLowerCase();

        if (!ledgerName || type.value) {
            return;
        }

        if (
            ledgerName.includes('mobile')
            || ledgerName.includes('bkash')
            || ledgerName.includes('b-kash')
            || ledgerName.includes('nagad')
            || ledgerName.includes('rocket')
            || ledgerName.includes('wallet')
        ) {
            type.value = 'Mobile Banking';
            type.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        if (ledgerName.includes('bank')) {
            type.value = 'Bank';
            type.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        if (ledgerName.includes('cash')) {
            type.value = 'Cash';
            type.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function autofillNameFromLedger() {
        const option = selectedLedgerOption();

        if (!option || name.value.trim() !== '') {
            return;
        }

        name.value = option.dataset.accountName || option.textContent.replace(/^\d+\s*-\s*/, '').trim();
    }

    function syncTypeFields() {
        const selectedType = type.value;
        const isCash = selectedType === 'Cash';
        const isBank = selectedType === 'Bank';
        const isMobile = selectedType === 'Mobile Banking';
        const showBankDetails = isBank || isMobile;

        if (bankFields) {
            bankFields.classList.toggle('hidden', !showBankDetails);
        }

        bankDetailRows.forEach((row) => {
            row.classList.toggle('hidden', !showBankDetails);
        });

        if (branchRow) {
            branchRow.classList.toggle('hidden', isCash || isMobile);
        }

        if (bank) {
            bank.required = isBank;
        }

        form.querySelectorAll('.bank-required').forEach((element) => {
            element.style.display = isBank ? '' : 'none';
        });

        if (isCash) {
            if (bank) {
                bank.value = '';
            }

            if (branch) {
                branch.value = '';
            }

            if (accountNumber) {
                accountNumber.value = '';
            }
        }

        if (isMobile && branch) {
            branch.value = '';
        }
    }

    function reloadAvailableLedgers(selectedValue = '') {
        const baseUrl = linkedLedger.dataset.baseUrl || '/api/dropdowns/cash-bank-ledgers';
        const params = new URLSearchParams();

        if (isEditing && form.dataset.editingId) {
            params.set('exclude_cash_bank_account_id', form.dataset.editingId);
        }

        linkedLedger.dataset.dropdown = params.toString()
            ? `${baseUrl}?${params.toString()}`
            : baseUrl;

        return setDropdownValue(linkedLedger, selectedValue);
    }

    function resetForm() {
        form.reset();

        isEditing = false;
        delete form.dataset.editingId;

        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';
        title.textContent = 'Create Cash / Bank Account';

        [type, linkedLedger].forEach((select) => {
            if (select) {
                select.dataset.selected = '';
            }
        });


        if (status) {
            status.value = 'Active';
        }

        if (code) {
            code.value = '';
        }

        if (usageNote) {
            usageNote.value = '';
        }

        syncTypeFields();
        reloadAvailableLedgers();

        name.focus();
    }

    function loadForEdit(row) {
        isEditing = true;
        form.dataset.editingId = row.dataset.id || '';
        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';
        title.textContent = 'Edit Cash / Bank Account';

        if (code) {
            code.value = row.dataset.code || '';
        }

        name.value = row.dataset.name || '';

        if (bank) {
            bank.value = row.dataset.bankName || '';
        }

        if (branch) {
            branch.value = row.dataset.branch || '';
        }

        if (accountNumber) {
            accountNumber.value = row.dataset.accountNumber || '';
        }


        if (usageNote) {
            usageNote.value = row.dataset.usageNote || '';
        }

        if (status) {
            status.value = row.dataset.status || 'Active';
        }

        setDropdownValue(type, row.dataset.type || '').then(() => {
            syncTypeFields();
        });

        reloadAvailableLedgers(row.dataset.linkedLedger || '');

        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Cash / Bank account loaded for editing.');
    }

    linkedLedger.addEventListener('change', () => {
        inferTypeFromLedgerName();
        autofillNameFromLedger();
    });

    type.addEventListener('change', syncTypeFields);

    document.querySelectorAll('#cashBankTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    addButton.addEventListener('click', () => {
        resetForm();
        showToast('Ready to add a new cash/bank account.');
    });

    cancelButton.addEventListener('click', () => {
        resetForm();
        showToast('Form cleared.');
    });

    syncTypeFields();
});
</script>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/setup/cash-bank-accounts.blade.php ENDPATH**/ ?>