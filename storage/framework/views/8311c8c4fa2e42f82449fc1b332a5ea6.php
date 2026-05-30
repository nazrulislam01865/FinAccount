<?php $__env->startSection('title', 'Dashboard | HisebGhor'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $dashboard = $dashboard ?? [];
    $user = auth()->user();
    $setup = $dashboard['setup_completion'] ?? ['percent' => 0, 'completed' => 0, 'total' => 0, 'steps' => []];
    $counts = $dashboard['setup_counts'] ?? [];
    $recentTransactions = $dashboard['recent_transactions'] ?? collect();
    $currency = $currency ?? config('accounting_reports.currency', 'BDT');
    $canAddTransaction = $user?->hasAnyPermission(['transactions.create', 'transactions.draft']) ?? false;
    $canViewReports = $user?->canViewRoute('accounting-reports.index') ?? false;
    $canOpenTransactionList = $user?->canViewRoute('accounting-reports.transactions.index') ?? false;
    $canReviewApprovals = $user?->hasAnyPermission(['approvals.view', 'approvals.manage']) ?? false;
    $money = fn ($value) => $currency.' '.number_format((float) $value, 2);
    $statusClass = fn ($status) => match ($status) {
        \App\Models\VoucherHeader::STATUS_POSTED => 'badge-success',
        \App\Models\VoucherHeader::STATUS_PENDING_REVIEW => 'badge-warning',
        \App\Models\VoucherHeader::STATUS_DRAFT => 'badge-neutral',
        default => 'badge-primary',
    };
?>

<div class="page-title">
    <div>
        <span class="page-label">Dashboard</span>
        <h2>Business Overview</h2>
        <p>Real-time cash, bank, receivable, payable, profit/loss, setup progress, and approval status from posted accounting records.</p>
    </div>
    <?php if($canAddTransaction || $canViewReports): ?>
        <div class="actions" style="border-top:0;padding-top:0">
            <?php if($canAddTransaction): ?>
                <a href="<?php echo e(route('transactions.create')); ?>" class="button btn-primary">Add Transaction</a>
            <?php endif; ?>

            <?php if($canViewReports): ?>
                <a href="<?php echo e(route('accounting-reports.index')); ?>" class="button btn-ghost">View Reports</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="stats-grid">
    <div class="card stat-card"><small>Cash In Hand</small><strong><?php echo e($money($dashboard['cash_in_hand'] ?? 0)); ?></strong></div>
    <div class="card stat-card"><small>Bank Balance</small><strong><?php echo e($money($dashboard['bank_balance'] ?? 0)); ?></strong></div>
    <div class="card stat-card"><small>Total Receivable</small><strong><?php echo e($money($dashboard['total_receivable'] ?? 0)); ?></strong></div>
    <div class="card stat-card"><small>Total Payable</small><strong><?php echo e($money($dashboard['total_payable'] ?? 0)); ?></strong></div>
</div>

<div class="stats-grid" style="margin-top:18px">
    <div class="card stat-card"><small>Monthly Income</small><strong><?php echo e($money($dashboard['monthly_income'] ?? 0)); ?></strong></div>
    <div class="card stat-card"><small>Monthly Expense</small><strong><?php echo e($money($dashboard['monthly_expense'] ?? 0)); ?></strong></div>
    <div class="card stat-card"><small>Net Profit / Loss</small><strong><?php echo e($money($dashboard['net_profit_loss'] ?? 0)); ?></strong></div>
    <div class="card stat-card"><small>Pending Approvals</small><strong><?php echo e(number_format((int) ($dashboard['pending_approvals'] ?? 0))); ?></strong></div>
</div>

<div class="layout" style="margin-top:22px">
    <div class="left-stack">
        <div class="card table-card">
            <div class="panel-head" style="padding:18px 18px 0">
                <div>
                    <h3>Recent Transactions</h3>
                    <p class="hint" style="margin-top:4px">Shows latest drafts, submitted approvals, and posted vouchers. The table shows 15 rows per page.</p>
                </div>
                <?php if($canOpenTransactionList): ?>
                    <a href="<?php echo e(route('accounting-reports.transactions.index')); ?>" class="button btn-soft">Open List</a>
                <?php endif; ?>
            </div>
            <div class="table-wrap">
                <table data-client-pagination="true" data-page-size="15">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Head</th>
                        <th>Party</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $recentTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $voucher): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $voucherDateValue = data_get($voucher, 'voucher_date');
                            try {
                                $voucherDate = $voucherDateValue instanceof \Carbon\CarbonInterface
                                    ? $voucherDateValue->format('d M Y')
                                    : ($voucherDateValue ? \Illuminate\Support\Carbon::parse($voucherDateValue)->format('d M Y') : '—');
                            } catch (\Throwable $exception) {
                                $voucherDate = '—';
                            }

                            $voucherNumber = data_get($voucher, 'voucher_number') ?: (is_string($voucher) ? $voucher : 'Draft');
                            $voucherHead = data_get($voucher, 'transactionHead.name') ?? 'Manual Entry';
                            $voucherParty = data_get($voucher, 'party.party_name') ?? '—';
                            $voucherAmount = data_get($voucher, 'amount', data_get($voucher, 'total_debit', 0));
                            $voucherStatus = data_get($voucher, 'status', 'Draft');
                        ?>
                        <tr>
                            <td><?php echo e($voucherDate); ?></td>
                            <td><span class="code"><?php echo e($voucherNumber); ?></span></td>
                            <td><?php echo e($voucherHead); ?></td>
                            <td><?php echo e($voucherParty); ?></td>
                            <td class="strong"><?php echo e($money($voucherAmount)); ?></td>
                            <td><span class="badge <?php echo e($statusClass($voucherStatus)); ?>"><?php echo e($voucherStatus); ?></span></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr data-empty="true">
                            <td colspan="6" class="muted">No voucher found yet. Use Add Transaction after setup is ready.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card helper-card">
            <h3>Quick Actions</h3>
            <p>Use guided setup first, then let the accounting rule engine create balanced debit/credit entries automatically.</p>
            <div class="step-list">
                <div class="step-row"><div class="nav-icon done-dot">1</div><div><strong>Complete Setup</strong><small>Company, financial year, CoA, cash/bank, parties, transaction heads, rules, opening balances, and voucher numbering.</small></div></div>
                <div class="step-row"><div class="nav-icon done-dot">2</div><div><strong>Post Transactions</strong><small>Users select business transaction details; debit/credit comes from active rules.</small></div></div>
                <div class="step-row"><div class="nav-icon done-dot">3</div><div><strong>Review Reports</strong><small>Validate trial balance, cash/bank book, receivable/payable, and profit/loss after posting.</small></div></div>
            </div>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card progress-card">
            <div class="progress-main">
                <div class="ring" style="--progress: <?php echo e((int) ($setup['percent'] ?? 0)); ?>%"><div class="ring-inner"><strong><?php echo e((int) ($setup['percent'] ?? 0)); ?>%</strong></div></div>
                <div>
                    <h3>Setup Completion</h3>
                    <p class="hint"><?php echo e((int) ($setup['completed'] ?? 0)); ?> of <?php echo e((int) ($setup['total'] ?? 0)); ?> setup groups ready.</p>
                </div>
            </div>
            <div class="step-list">
                <?php $__empty_1 = true; $__currentLoopData = ($setup['steps'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="step-row">
                        <div class="nav-icon <?php echo e(($step['complete'] ?? false) ? 'done-dot' : ''); ?>"><?php echo e(($step['complete'] ?? false) ? '✓' : '•'); ?></div>
                        <div><strong><?php echo e($step['label'] ?? 'Setup Step'); ?></strong><small><?php echo e(($step['complete'] ?? false) ? 'Ready' : 'Pending'); ?></small></div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="hint">Setup progress will appear after the setup service is configured.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card info-card">
            <h3>Setup Data</h3>
            <p class="hint">Counts are used to identify missing setup before transaction posting.</p>
            <div class="step-list">
                <div class="step-row"><div class="nav-icon">FY</div><div><strong><?php echo e(number_format($counts['financial_years'] ?? 0)); ?></strong><small>Open financial years</small></div></div>
                <div class="step-row"><div class="nav-icon">COA</div><div><strong><?php echo e(number_format($counts['posting_ledgers'] ?? 0)); ?></strong><small>Posting ledgers</small></div></div>
                <div class="step-row"><div class="nav-icon">CB</div><div><strong><?php echo e(number_format($counts['cash_bank_accounts'] ?? 0)); ?></strong><small>Cash / bank accounts</small></div></div>
                <div class="step-row"><div class="nav-icon">AR</div><div><strong><?php echo e(number_format($counts['accounting_rules'] ?? 0)); ?></strong><small>Active posting rules</small></div></div>
            </div>
        </div>

        <div class="card info-card">
            <h3>Accounting Control</h3>
            <p>Dashboard totals are calculated from posted voucher lines only. Draft and pending review vouchers are visible but excluded from financial totals.</p>
            <?php if(($dashboard['pending_approvals'] ?? 0) > 0 && $canReviewApprovals): ?>
                <a href="<?php echo e(route('approvals.index')); ?>" class="button btn-outline" style="width:100%;margin-top:12px">Review Pending Approvals</a>
            <?php endif; ?>
        </div>
    </aside>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/dashboard.blade.php ENDPATH**/ ?>