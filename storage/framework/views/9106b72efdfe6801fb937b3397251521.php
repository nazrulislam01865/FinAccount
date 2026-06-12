<?php $__env->startSection('title', 'Due Management | HisebGhor'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $money = fn ($value) => 'BDT ' . number_format((float) $value, 2);
    $dateLabel = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d M Y') : '-';
    $statusClass = fn ($status) => match ($status) {
        'Open' => 'badge-neutral',
        'Partially Paid' => 'badge-warning',
        'Paid / Collected' => 'badge-primary',
        'Overdue' => 'badge-danger',
        default => 'badge-neutral',
    };
    $typeClass = fn ($type) => $type === 'Receivable' ? 'badge-success' : 'badge-danger';
    $currentUser = auth()->user();
    $canSettleDue = $currentUser?->hasAnyPermission(['due-management.manage', 'transactions.create']) ?? false;
    $rulePayload = $settlementRules->values();
?>

<div class="page-title">
    <div>
        <span class="page-label">Accounting Output</span>
        <h2>Due Management</h2>
        <p>Track payable and receivable balances generated from posted accounting vouchers. Settlement posts only AR/AP reduction entries.</p>
    </div>
    <div class="quick-actions">
        <button class="btn-outline" type="button" onclick="window.print()">Print</button>
        <a href="<?php echo e(route('transactions.create')); ?>" class="button btn-primary">+ New Due Entry</a>
    </div>
</div>

<div class="due-page setup-record-flow">
<div class="stats-grid due-stats">
    <div class="card stat-card">
        <small>Total Payable Due</small>
        <strong class="red-text"><?php echo e($money($stats['payable'])); ?></strong>
        <span class="muted">Company needs to pay</span>
    </div>
    <div class="card stat-card">
        <small>Total Receivable Due</small>
        <strong class="green-text"><?php echo e($money($stats['receivable'])); ?></strong>
        <span class="muted">Company needs to collect</span>
    </div>
    <div class="card stat-card">
        <small>Overdue Items</small>
        <strong class="orange-text"><?php echo e($stats['overdue']); ?></strong>
        <span class="muted">Past due date</span>
    </div>
    <div class="card stat-card">
        <small>Partial Items</small>
        <strong><?php echo e($stats['partial']); ?></strong>
        <span class="muted">Payment/collection started</span>
    </div>
</div>

        <form
            class="card form-card"
            id="dueSettlementForm"
            action="<?php echo e(route('api.due-management.settle')); ?>"
            method="POST"
            data-settlement-rules='<?php echo json_encode($rulePayload, 15, 512) ?>'
        >
            <?php echo csrf_field(); ?>
            <h3 class="section-title">Due Payment / Collection</h3>

            <input type="hidden" name="party_id" id="settlePartyId">
            <input type="hidden" name="account_id" id="settleAccountId">
            <input type="hidden" name="due_type" id="settleDueType">

            <div class="form-grid">
                <div>
                    <label>Selected Party</label>
                    <input id="selectedPartyText" value="Select a due row" readonly>
                </div>
                <div>
                    <label>Due Ledger</label>
                    <input id="selectedAccountText" value="-" readonly>
                </div>
                <div class="two-col">
                    <div>
                        <label>Balance Due</label>
                        <input id="selectedBalanceText" value="BDT 0.00" readonly>
                    </div>
                    <div>
                        <label>Pay / Collect <span class="required">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="settleAmount" required>
                    </div>
                </div>
                <div>
                    <label>Payment / Collection Rule <span class="required">*</span></label>
                    <select name="transaction_head_id" id="settleRuleHead" required>
                        <option value="">Select due row first</option>
                    </select>
                    <input type="hidden" name="settlement_type_id" id="settleRuleSettlement">
                    <div class="hint">Rules are loaded from Setup &gt; Accounting Rules Setup.</div>
                </div>
                <div>
                    <label>Cash / Bank Account <span class="required">*</span></label>
                    <select name="cash_bank_account_id" required>
                        <option value="">Select Cash / Bank</option>
                        <?php $__currentLoopData = $cashBankAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($account->id); ?>"><?php echo e($account->cash_bank_name); ?> - <?php echo e($account->linkedLedger?->account_name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <div class="hint">Connected with Cash / Bank Setup.</div>
                </div>
                <div>
                    <label>Payment Date <span class="required">*</span></label>
                    <input type="date" name="voucher_date" value="<?php echo e(now()->toDateString()); ?>" required>
                </div>
                <div>
                    <label>Reference</label>
                    <input name="reference" placeholder="Receipt no, cheque no, note...">
                </div>
                <div>
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Settlement note"></textarea>
                </div>
                <div class="hint-box" id="settlementPreview">
                    <strong>Accounting rule</strong>
                    Select a due row to see whether the system will post payable payment or receivable collection.
                </div>
            </div>

            <div class="actions">
                <button class="btn-ghost" type="reset">Clear</button>
                <?php if($canSettleDue): ?>
                    <button class="btn-primary" type="submit">Post Settlement</button>
                <?php endif; ?>
            </div>
        </form>

<form class="card toolbar due-toolbar" method="GET" action="<?php echo e(route('due-management.index')); ?>" style="margin-top:18px">
    <div class="field search-field">
        <label>Search</label>
        <span>⌕</span>
        <input name="search" value="<?php echo e($filters['search']); ?>" placeholder="Party, account, voucher, status...">
    </div>
    <div>
        <label>Due Type</label>
        <select name="due_type">
            <?php $__currentLoopData = ['All', 'Payable', 'Receivable']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($type); ?>" <?php if($filters['due_type'] === $type): echo 'selected'; endif; ?>><?php echo e($type); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div>
        <label>Status</label>
        <select name="status">
            <?php $__currentLoopData = ['All', 'Open', 'Partially Paid', 'Overdue', 'Paid / Collected']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($status); ?>" <?php if($filters['status'] === $status): echo 'selected'; endif; ?>><?php echo e($status); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div>
        <label>From Date</label>
        <input type="date" name="from_date" value="<?php echo e($filters['from_date']); ?>">
    </div>
    <div>
        <label>To Date</label>
        <input type="date" name="to_date" value="<?php echo e($filters['to_date']); ?>">
    </div>
    <button class="btn-primary" type="submit">Filter</button>
</form>

<div class="layout due-layout" style="margin-top:18px">
    <div class="left-stack">
        <div class="card table-card">
            <div class="card-head">
                <div>
                    <h3>Due List</h3>
                    <p>Balances are calculated from due_register movements created by posted vouchers.</p>
                </div>
                <span class="badge badge-primary"><?php echo e($dueRows->count()); ?> record(s)</span>
            </div>
            <div class="table-wrap">
                <table id="dueManagementTable" data-client-pagination="true" data-page-size="10">
                    <thead>
                        <tr>
                            <th>Due Date</th>
                            <th>Party</th>
                            <th>Account</th>
                            <th>Due Type</th>
                            <th>Original</th>
                            <th>Paid / Collected</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Latest Voucher</th>
                            <th style="text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $dueRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr
                                data-due-row
                                data-party-id="<?php echo e($row['party_id']); ?>"
                                data-party-name="<?php echo e(e($row['party_name'])); ?>"
                                data-account-id="<?php echo e($row['account_id']); ?>"
                                data-account-name="<?php echo e(e($row['account_name'])); ?>"
                                data-due-type="<?php echo e($row['due_type']); ?>"
                                data-balance="<?php echo e($row['balance_due']); ?>"
                                data-original="<?php echo e($row['original_amount']); ?>"
                                data-settled="<?php echo e($row['settled_amount']); ?>"
                                data-status="<?php echo e($row['status']); ?>"
                            >
                                <td><?php echo e($dateLabel($row['due_date'])); ?></td>
                                <td class="strong"><?php echo e($row['party_name']); ?><br><span class="muted"><?php echo e($row['party_type']); ?></span></td>
                                <td><?php echo e($row['account_name']); ?></td>
                                <td><span class="badge <?php echo e($typeClass($row['due_type'])); ?>"><?php echo e($row['due_type']); ?></span></td>
                                <td class="money-cell"><?php echo e($money($row['original_amount'])); ?></td>
                                <td class="money-cell"><?php echo e($money($row['settled_amount'])); ?></td>
                                <td class="money-cell strong"><?php echo e($money($row['balance_due'])); ?></td>
                                <td><span class="badge <?php echo e($statusClass($row['status'])); ?>"><?php echo e($row['status']); ?></span></td>
                                <td><?php echo e($row['latest_voucher'] ?? '-'); ?></td>
                                <td>
                                    <div class="action-cell">
                                        <?php if($canSettleDue && $row['balance_due'] > 0): ?>
                                            <button type="button" class="icon-btn" title="Select due" data-select-due>✓</button>
                                        <?php else: ?>
                                            <span class="muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr data-empty="true">
                                <td colspan="10" class="empty-state">No due balance found for the selected filter.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span>Showing <?php echo e($dueRows->count()); ?> due balance row(s)</span>
                <span>Source: posted journal lines and due register</span>
            </div>
        </div>
    </div>

</div>
        <div class="card helper-card">
            <h3>Accounting Control</h3>
            <p><strong>Payable settlement:</strong> Dr Accounts Payable, Cr Cash/Bank.</p>
            <p><strong>Receivable collection:</strong> Dr Cash/Bank, Cr Accounts Receivable.</p>
            <p class="muted" style="margin-top:10px">Expense and income are not recorded again when a previous due is paid or collected.</p>
        </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .setup-record-flow {
        display: grid;
        gap: 18px;
    }

    .setup-record-flow .stats-grid {
        order: 1;
    }

    .setup-record-flow .due-layout {
        order: 4;
        display: grid;
        grid-template-columns: 1fr !important;
        margin-top: 0 !important;
    }

    .setup-record-flow #dueSettlementForm {
        order: 2;
        width: 100%;
        position: static;
    }

    .setup-record-flow .due-toolbar {
        order: 3;
        margin-top: 0 !important;
        grid-template-columns: minmax(220px, 1fr) 150px 170px 150px 150px 120px;
        width: 100%;
    }

    .setup-record-flow .due-layout > .left-stack {
        width: 100%;
    }

    .setup-record-flow .helper-card {
        order: 5;
        width: 100%;
    }

    .setup-record-flow #dueSettlementForm .form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: start;
    }

    .setup-record-flow #dueSettlementForm .form-grid > .two-col,
    .setup-record-flow #dueSettlementForm .hint-box,
    .setup-record-flow #dueSettlementForm .actions {
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

    .money-cell { text-align: right; font-weight: 800; white-space: nowrap; }
    .red-text { color: #dc2626 !important; }
    .green-text { color: #067647 !important; }
    .orange-text { color: #b54708 !important; }
    .due-stats .stat-card span { display: block; font-size: 12px; }

    @media (max-width: 1320px) {
        .setup-record-flow .due-toolbar { grid-template-columns: 1fr 1fr 1fr; }
    }

    @media (max-width: 880px) {
        .setup-record-flow .due-toolbar,
        .setup-record-flow #dueSettlementForm .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    const form = document.getElementById('dueSettlementForm');
    if (!form) return;

    const money = (value) => 'BDT ' + Number(value || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const rules = JSON.parse(form.dataset.settlementRules || '[]');
    const showToast = (message) => window.AccountingUI?.showToast ? window.AccountingUI.showToast(message) : alert(message);

    function setSelectedRow(row) {
        document.querySelectorAll('[data-due-row]').forEach(item => item.style.background = '');
        row.style.background = '#eef4ff';

        const dueType = row.dataset.dueType;
        const balance = Number(row.dataset.balance || 0);

        document.getElementById('settlePartyId').value = row.dataset.partyId;
        document.getElementById('settleAccountId').value = row.dataset.accountId;
        document.getElementById('settleDueType').value = dueType;
        document.getElementById('selectedPartyText').value = row.dataset.partyName;
        document.getElementById('selectedAccountText').value = row.dataset.accountName;
        document.getElementById('selectedBalanceText').value = money(balance);
        document.getElementById('settleAmount').value = balance.toFixed(2);
        document.getElementById('settleAmount').max = balance.toFixed(2);

        const select = document.getElementById('settleRuleHead');
        const settlement = document.getElementById('settleRuleSettlement');
        select.innerHTML = '';
        settlement.value = '';

        const matchedRules = rules.filter(rule => rule.due_type === dueType);
        if (!matchedRules.length) {
            select.innerHTML = '<option value="">No settlement rule configured</option>';
            document.getElementById('settlementPreview').innerHTML = '<strong>Missing setup</strong>Configure a ' + dueType + ' settlement accounting rule in Accounting Rules Setup first.';
            return;
        }

        matchedRules.forEach((rule, index) => {
            const option = document.createElement('option');
            option.value = rule.transaction_head_id;
            option.dataset.settlementId = rule.settlement_type_id;
            option.dataset.debit = rule.debit_account || '';
            option.dataset.credit = rule.credit_account || '';
            option.textContent = rule.label;
            select.appendChild(option);
            if (index === 0) {
                settlement.value = rule.settlement_type_id;
            }
        });

        updateRulePreview();
    }

    function updateRulePreview() {
        const select = document.getElementById('settleRuleHead');
        const option = select.selectedOptions[0];
        if (!option) return;

        document.getElementById('settleRuleSettlement').value = option.dataset.settlementId || '';
        document.getElementById('settlementPreview').innerHTML = '<strong>Ledger preview</strong>Dr ' + (option.dataset.debit || '-') + '<br>Cr ' + (option.dataset.credit || '-') + '<br><span class="muted">Actual Cash/Bank ledger is taken from selected Cash / Bank account.</span>';
    }

    document.querySelectorAll('[data-select-due]').forEach(button => {
        button.addEventListener('click', () => setSelectedRow(button.closest('[data-due-row]')));
    });

    document.getElementById('settleRuleHead')?.addEventListener('change', updateRulePreview);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!document.getElementById('settlePartyId').value) {
            showToast('Please select a due row first.');
            return;
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
                const message = data.message || Object.values(data.errors || {})?.flat()?.[0] || 'Due settlement failed.';
                throw new Error(message);
            }
            showToast(data.message || 'Posted successfully.');
            window.location.href = data.redirect || window.location.href;
        } catch (error) {
            showToast(error.message || 'Due settlement failed.');
            if (submitter) {
                submitter.disabled = false;
                submitter.textContent = originalText;
            }
        }
    });
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/due-management/index.blade.php ENDPATH**/ ?>