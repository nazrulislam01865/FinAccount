<?php $__env->startSection('title', 'Approvals | HisebGhor'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-title">
    <div>
        <span class="page-label">Approval Workflow</span>
        <h2>Pending Transaction Approvals</h2>
        <p>Review submitted vouchers before they affect ledgers and reports.</p>
    </div>
</div>

<?php if(session('success')): ?>
    <div class="card info-card" style="margin-bottom:16px"><strong><?php echo e(session('success')); ?></strong></div>
<?php endif; ?>

<?php if($errors->any()): ?>
    <div class="card info-card" style="margin-bottom:16px;border-color:#fecaca;background:#fef2f2;color:#991b1b">
        <strong><?php echo e($errors->first()); ?></strong>
    </div>
<?php endif; ?>

<div class="card table-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher</th>
                    <th>Transaction Head</th>
                    <th>Party</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $pendingVouchers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $voucher): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e(optional($voucher->voucher_date)->format('d M Y')); ?></td>
                        <td><span class="code"><?php echo e($voucher->voucher_number); ?></span><br><small class="muted"><?php echo e($voucher->voucher_type); ?></small></td>
                        <td><?php echo e($voucher->transactionHead?->name ?? '—'); ?></td>
                        <td><?php echo e($voucher->party?->party_name ?? '—'); ?></td>
                        <td class="strong"><?php echo e($currency); ?> <?php echo e(number_format((float) $voucher->amount, 2)); ?></td>
                        <td><span class="badge badge-warning"><?php echo e($voucher->status); ?></span></td>
                        <td><?php echo e(optional($voucher->submitted_at)->format('d M Y h:i A')); ?></td>
                        <td>
                            <div class="action-cell">
                                <form method="POST" action="<?php echo e(route('approvals.approve', $voucher)); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="button btn-primary">Approve</button>
                                </form>
                                <form method="POST" action="<?php echo e(route('approvals.reject', $voucher)); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="button btn-ghost">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr data-empty="true"><td colspan="8" class="muted">No transactions are waiting for approval.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer"><?php echo e($pendingVouchers->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/approvals/index.blade.php ENDPATH**/ ?>