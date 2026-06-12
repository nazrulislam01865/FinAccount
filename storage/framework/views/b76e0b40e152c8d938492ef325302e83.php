<?php $__env->startSection('title', 'Transaction Details'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $totalDebit = collect($transaction->journal_lines ?? [])->sum(fn ($line) => (float) $line->debit);
    $totalCredit = collect($transaction->journal_lines ?? [])->sum(fn ($line) => (float) $line->credit);
    $canReverse = auth()->user()?->hasPermission('transactions.reverse') && ! in_array(strtolower((string) $transaction->status), ['reversed', 'cancelled'], true);
?>

<div class="page-title">
    <div>
        <h2><?php echo e($transaction->voucher_no); ?></h2>
        <p>Voucher details and generated accounting debit/credit lines.</p>
    </div>
    <div class="quick-actions" style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="button btn-ghost" href="<?php echo e(route('accounting-reports.transactions.index')); ?>">Back to Transaction List</a>
        <?php if($canReverse): ?>
            <form method="POST" action="<?php echo e(route('accounting-reports.transactions.reverse', $transaction->voucher_id)); ?>" onsubmit="return confirm('Reverse this posted transaction?')">
                <?php echo csrf_field(); ?>
                <button class="btn-outline" type="submit">Reverse Transaction</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if(session('success')): ?>
    <div class="card" style="padding:14px 18px;margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:750">
        <?php echo e(session('success')); ?>

    </div>
<?php endif; ?>

<div class="layout">
    <div class="left-stack">
        <div class="card table-card">
            <div class="card-head">
                <div>
                    <h3>Generated Ledger Entry</h3>
                    <p>Total debit and credit must always match before the voucher affects reports.</p>
                </div>
                <?php if(round($totalDebit, 2) === round($totalCredit, 2)): ?>
                    <span class="badge badge-success">Balanced</span>
                <?php else: ?>
                    <span class="badge badge-danger">Not Balanced</span>
                <?php endif; ?>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Description</th>
                            <th style="text-align:right">Debit</th>
                            <th style="text-align:right">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $transaction->journal_lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td class="strong"><?php echo e(trim(($line->account_code ? $line->account_code . ' - ' : '') . $line->account_name)); ?></td>
                                <td><?php echo e($line->description ?: '—'); ?></td>
                                <td style="text-align:right;font-weight:850"><?php echo e((float) $line->debit > 0 ? $money($line->debit) : '—'); ?></td>
                                <td style="text-align:right;font-weight:850"><?php echo e((float) $line->credit > 0 ? $money($line->credit) : '—'); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr data-empty="true"><td colspan="4" class="empty-state">No debit/credit line found for this voucher.</td></tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="2" class="strong">Total</td>
                            <td style="text-align:right;font-weight:900"><?php echo e($money($totalDebit)); ?></td>
                            <td style="text-align:right;font-weight:900"><?php echo e($money($totalCredit)); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card info-card">
            <h3>Basic Information</h3>
            <div class="form-grid" style="gap:12px">
                <div><label>Date</label><div class="strong"><?php echo e(\Illuminate\Support\Carbon::parse($transaction->voucher_date)->format('d M Y')); ?></div></div>
                <div><label>Head</label><div><?php echo e($transaction->purpose_name ?: $transaction->purpose_code ?: $transaction->voucher_type_code); ?></div></div>
                <div><label>Party</label><div><?php echo e($transaction->party_name ?: '—'); ?></div></div>
                <div><label>Settlement</label><div><?php echo e($transaction->settlement ?: '—'); ?></div></div>
                <div><label>Amount</label><div class="strong"><?php echo e($money($transaction->amount)); ?></div></div>
                <div><label>Status</label><div><?php echo e($transaction->status); ?></div></div>
                <div><label>Reference</label><div><?php echo e($transaction->reference_no ?: '—'); ?></div></div>
                <div><label>Description</label><div><?php echo e($transaction->description ?: '—'); ?></div></div>
            </div>
        </div>

        <div class="card helper-card">
            <h3>Accounting Control</h3>
            <p>Posted vouchers are preserved for audit. Corrections should use reversal entries instead of deleting or directly editing posted accounting data.</p>
        </div>
    </aside>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/accounting_reports/transactions/show.blade.php ENDPATH**/ ?>