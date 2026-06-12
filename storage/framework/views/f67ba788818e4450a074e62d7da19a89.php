<?php $__env->startSection('title', 'Ledger Report | HisebGhor'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $money = fn ($value) => 'BDT ' . number_format((float) $value, 2);
    $dateLabel = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d M Y') : '-';
    $selectedGroupId = (string) ($filters['account_group_id'] ?? '');
    $selectedAccountId = (string) ($filters['account_id'] ?? $account?->id ?? '');
?>

<div class="page-title">
    <div>
        <span class="page-label">Financial Report</span>
        <h2>Ledger Report</h2>
        <p>Account-wise debit, credit, and running balance. This report is generated from journal lines based on the selected accounting filters.</p>
    </div>
    <div class="quick-actions">
        <button class="btn-outline" type="button" onclick="window.print()">Print</button>
        <button class="btn-ghost" type="button" data-toast="Excel export can be added after report finalization.">Export</button>
    </div>
</div>

<div class="stats-grid ledger-stats" style="margin-bottom:18px">
    <div class="card stat-card">
        <small>Opening Balance</small>
        <strong class="orange-text"><?php echo e($report['opening_balance_label']); ?></strong>
        <span class="muted">Before <?php echo e($dateLabel($fromDate)); ?></span>
    </div>
    <div class="card stat-card">
        <small>Total Debit</small>
        <strong class="green-text"><?php echo e($money($report['total_debit'])); ?></strong>
        <span class="muted">Within selected period</span>
    </div>
    <div class="card stat-card">
        <small>Total Credit</small>
        <strong class="red-text"><?php echo e($money($report['total_credit'])); ?></strong>
        <span class="muted">Within selected period</span>
    </div>
    <div class="card stat-card closing-balance-card">
        <small>Closing Balance</small>
        <strong><?php echo e($report['closing_balance_label']); ?></strong>
        <span class="muted">Running ending balance</span>
    </div>
    <div class="card stat-card report-summary-card">
        <small>Account Type</small>
        <strong><?php echo e($report['account_type']); ?></strong>
        <span class="muted">Selected ledger group</span>
    </div>
    <div class="card stat-card report-summary-card">
        <small>Normal Balance</small>
        <strong><?php echo e($report['normal_balance']); ?></strong>
        <span class="muted">Accounting side</span>
    </div>
    <div class="card stat-card report-summary-card">
        <small>Total Entries</small>
        <strong><?php echo e($report['total_entries']); ?></strong>
        <span class="muted">Filtered rows shown</span>
    </div>
    <div class="card stat-card report-summary-card">
        <small>Last Voucher</small>
        <strong><?php echo e($report['last_transaction']); ?></strong>
        <span class="muted">Latest matching voucher</span>
    </div>
</div>

<form class="card toolbar ledger-toolbar accounting-filter-sequence" method="GET" action="<?php echo e(route('accounting-reports.ledger-report.index')); ?>">
    <div class="date-range-field">
        <label>Date Range</label>
        <div class="date-range-inputs">
            <input type="date" name="from_date" value="<?php echo e($fromDate); ?>" required aria-label="From Date">
            <input type="date" name="to_date" value="<?php echo e($toDate); ?>" required aria-label="To Date">
        </div>
    </div>

    <div>
        <label>Account Group</label>
        <select name="account_group_id" id="ledgerAccountGroupFilter">
            <option value="">All Account Groups</option>
            <?php $__currentLoopData = $accountGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option
                    value="<?php echo e($group->id); ?>"
                    data-account-type-id="<?php echo e($group->account_type_id); ?>"
                    <?php if($selectedGroupId === (string) $group->id): echo 'selected'; endif; ?>
                >
                    <?php echo e($group->account_code); ?> - <?php echo e($group->account_name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <div>
        <label>Ledger Account</label>
        <select name="account_id" id="ledgerAccountFilter" required>
            <?php $__currentLoopData = $allAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ledgerAccount): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option
                    value="<?php echo e($ledgerAccount->id); ?>"
                    data-parent-id="<?php echo e($ledgerAccount->parent_id); ?>"
                    data-account-type-id="<?php echo e($ledgerAccount->account_type_id); ?>"
                    <?php if($selectedAccountId === (string) $ledgerAccount->id): echo 'selected'; endif; ?>
                >
                    <?php echo e($ledgerAccount->account_code); ?> - <?php echo e($ledgerAccount->account_name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <div>
        <label>Party</label>
        <select name="party_id">
            <option value="">All Parties</option>
            <?php $__currentLoopData = $parties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $party): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($party->id); ?>" <?php if((string) ($filters['party_id'] ?? '') === (string) $party->id): echo 'selected'; endif; ?>>
                    <?php echo e($party->party_code); ?> - <?php echo e($party->party_name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <div>
        <label>Voucher Type</label>
        <select name="voucher_type">
            <option value="All">All Voucher Types</option>
            <?php $__currentLoopData = $voucherTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $voucherType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($voucherType); ?>" <?php if(($filters['voucher_type'] ?? 'All') === $voucherType): echo 'selected'; endif; ?>><?php echo e($voucherType); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <div>
        <label>Transaction Head</label>
        <select name="transaction_head_id">
            <option value="">All Transaction Heads</option>
            <?php $__currentLoopData = $transactionHeads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $head): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($head->id); ?>" <?php if((string) ($filters['transaction_head_id'] ?? '') === (string) $head->id): echo 'selected'; endif; ?>>
                    <?php echo e($head->head_code); ?> - <?php echo e($head->name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <div>
        <label>Status</label>
        <select name="status">
            <?php $__currentLoopData = $statusOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($status); ?>" <?php if(($filters['status'] ?? 'Posted') === $status): echo 'selected'; endif; ?>><?php echo e($status); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>

    <button class="btn-primary" type="submit">Run Report</button>
</form>

<div class="card table-card ledger-report-card ledger-table-full" style="margin-top:18px">
    <div class="card-head">
        <div>
            <h3><?php echo e($account?->display_name ?? 'Ledger Account'); ?></h3>
            <p><?php echo e($dateLabel($fromDate)); ?> to <?php echo e($dateLabel($toDate)); ?>. Status filter: <?php echo e($filters['status'] ?? 'Posted'); ?>.</p>
        </div>
        <span class="badge badge-primary"><?php echo e($report['total_entries']); ?> entries</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher</th>
                    <th>Type</th>
                    <th>Particulars</th>
                    <th>Party</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="opening-row">
                    <td colspan="7" class="strong">Opening Balance</td>
                    <td class="money-cell"><?php echo e($report['opening_debit'] ? $money($report['opening_debit']) : '-'); ?></td>
                    <td class="money-cell"><?php echo e($report['opening_credit'] ? $money($report['opening_credit']) : '-'); ?></td>
                    <td class="money-cell strong"><?php echo e($report['opening_balance_label']); ?></td>
                </tr>
                <?php $__empty_1 = true; $__currentLoopData = $report['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($dateLabel($row['date'])); ?></td>
                        <td class="strong"><?php echo e($row['voucher_number']); ?></td>
                        <td><span class="badge badge-neutral"><?php echo e($row['voucher_type']); ?></span></td>
                        <td>
                            <strong><?php echo e($row['transaction_head'] ?? '-'); ?></strong><br>
                            <span class="muted"><?php echo e($row['settlement_type'] ?? ''); ?> <?php echo e($row['narration'] ? ' | ' . $row['narration'] : ''); ?></span>
                        </td>
                        <td><?php echo e($row['party_name'] ?: '-'); ?></td>
                        <td><?php echo e($row['reference'] ?: '-'); ?></td>
                        <td><span class="badge badge-neutral"><?php echo e($row['status'] ?: '-'); ?></span></td>
                        <td class="money-cell debit-text"><?php echo e($row['debit'] ? $money($row['debit']) : '-'); ?></td>
                        <td class="money-cell credit-text"><?php echo e($row['credit'] ? $money($row['credit']) : '-'); ?></td>
                        <td class="money-cell strong"><?php echo e($row['running_balance_label']); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr data-empty="true">
                        <td colspan="10" class="empty-state">No ledger movement found for this account and filter sequence.</td>
                    </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="7" class="strong">Period Total</td>
                    <td class="money-cell strong"><?php echo e($money($report['total_debit'])); ?></td>
                    <td class="money-cell strong"><?php echo e($money($report['total_credit'])); ?></td>
                    <td></td>
                </tr>
                <tr class="closing-row">
                    <td colspan="9" class="strong">Closing Balance</td>
                    <td class="money-cell strong"><?php echo e($report['closing_balance_label']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Ledger report reads journal_lines with the standard accounting filter sequence.</span>
        <span>Normal Balance: <?php echo e($report['normal_balance']); ?></span>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .ledger-toolbar{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;align-items:end}.ledger-toolbar>*{min-width:0}.ledger-toolbar .date-range-field{grid-column:span 2;min-width:0}.date-range-inputs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;min-width:0}.ledger-toolbar input,.ledger-toolbar select,.ledger-toolbar button{width:100%;min-width:0;max-width:100%;box-sizing:border-box}.ledger-toolbar label{white-space:nowrap}.ledger-stats{grid-template-columns:repeat(4,minmax(0,1fr))}.ledger-stats .stat-card strong{line-height:1.15}.ledger-stats .report-summary-card strong{font-size:20px}.ledger-table-full{width:100%}.ledger-table-full .table-wrap{overflow-x:scroll;scrollbar-gutter:stable both-edges}.ledger-table-full table{min-width:1260px}.money-cell{text-align:right;font-weight:800;white-space:nowrap}.green-text{color:#067647!important}.red-text{color:#dc2626!important}.orange-text{color:#b54708!important}.debit-text{color:#067647}.credit-text{color:#dc2626}.opening-row td{background:#fff7ed}.total-row td{background:#f8fafc}.closing-row td{background:#eef4ff;color:#1d4ed8}@media(max-width:1320px){.ledger-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:880px){.ledger-toolbar{grid-template-columns:1fr}.ledger-toolbar .date-range-field{grid-column:span 1}.date-range-inputs{grid-template-columns:1fr}.ledger-stats{grid-template-columns:1fr}.ledger-table-full table{min-width:1080px}}
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const groupSelect = document.getElementById('ledgerAccountGroupFilter');
    const ledgerSelect = document.getElementById('ledgerAccountFilter');

    if (!groupSelect || !ledgerSelect) return;

    const syncLedgerOptions = () => {
        const selectedOption = groupSelect.selectedOptions[0];
        const groupId = groupSelect.value;
        const accountTypeId = selectedOption?.dataset.accountTypeId || '';
        let hasVisibleSelected = false;
        let firstVisible = null;

        [...ledgerSelect.options].forEach((option) => {
            const parentId = option.dataset.parentId || '';
            const optionAccountType = option.dataset.accountTypeId || '';
            const visible = !groupId || parentId === groupId || (!parentId && optionAccountType === accountTypeId);
            option.hidden = !visible;
            option.disabled = !visible;

            if (visible && !firstVisible) firstVisible = option;
            if (visible && option.selected) hasVisibleSelected = true;
        });

        if (!hasVisibleSelected && firstVisible) {
            firstVisible.selected = true;
        }
    };

    groupSelect.addEventListener('change', syncLedgerOptions);
    syncLedgerOptions();
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/reports/ledger.blade.php ENDPATH**/ ?>