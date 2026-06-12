<?php $__env->startSection('title', 'Cash / Bank Book'); ?>

<?php $__env->startPush('styles'); ?>
    <?php echo $__env->make('accounting_reports.partials.financial-report-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $selectedAccount = $filters['account_id'] ?? '';
?>

<div class="financial-report-page">
    <?php if (isset($component)) { $__componentOriginal8a8a8a52f610621907fb177a37bc2450 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8a8a8a52f610621907fb177a37bc2450 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.page-header','data' => ['title' => 'Cash / Bank Book','subtitle' => 'Cash and bank movement generated from posted voucher debit/credit lines.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Cash / Bank Book','subtitle' => 'Cash and bank movement generated from posted voucher debit/credit lines.']); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <a class="button btn-outline" href="<?php echo e(route('accounting-reports.cash-bank-book.export', array_merge(request()->query(), ['format' => 'xlsx']))); ?>">⇩ Export XLSX</a>
            <a class="button btn-outline" href="<?php echo e(route('accounting-reports.cash-bank-book.export', array_merge(request()->query(), ['format' => 'pdf']))); ?>">⇩ Export PDF</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
         <?php $__env->endSlot(); ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8a8a8a52f610621907fb177a37bc2450)): ?>
<?php $attributes = $__attributesOriginal8a8a8a52f610621907fb177a37bc2450; ?>
<?php unset($__attributesOriginal8a8a8a52f610621907fb177a37bc2450); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8a8a8a52f610621907fb177a37bc2450)): ?>
<?php $component = $__componentOriginal8a8a8a52f610621907fb177a37bc2450; ?>
<?php unset($__componentOriginal8a8a8a52f610621907fb177a37bc2450); ?>
<?php endif; ?>

    <form method="GET" action="<?php echo e(route('accounting-reports.cash-bank-book.index')); ?>" class="card report-toolbar report-toolbar-seven accounting-filter-sequence">
        <div class="date-range-field">
            <label>Date Range</label>
            <div class="date-range-inputs">
                <input type="date" name="from_date" value="<?php echo e($filters['from_date'] ?? $report['from_date']); ?>" aria-label="From Date">
                <input type="date" name="to_date" value="<?php echo e($filters['to_date'] ?? $report['to_date']); ?>" aria-label="To Date">
            </div>
        </div>
        <div>
            <label>Ledger Account</label>
            <select name="account_id">
                <option value="">All Cash & Bank</option>
                <?php $__currentLoopData = $report['cash_bank_accounts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($account->account_id); ?>" <?php if((string) $selectedAccount === (string) $account->account_id): echo 'selected'; endif; ?>>
                        <?php echo e(trim(($account->account_code ? $account->account_code . ' - ' : '') . $account->account_name)); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div>
            <label>Book Type</label>
            <select name="book_type">
                <?php $__currentLoopData = ['All' => 'Combined Book', 'Cash Book Only' => 'Cash Book Only', 'Bank Book Only' => 'Bank Book Only']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($value); ?>" <?php if(($filters['book_type'] ?? 'All') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div>
            <label>Transaction Type</label>
            <select name="transaction_type">
                <?php $__currentLoopData = ['All', 'Inflow', 'Outflow']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($type); ?>" <?php if(($filters['transaction_type'] ?? 'All') === $type): echo 'selected'; endif; ?>><?php echo e($type); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <?php if (isset($component)) { $__componentOriginal4ba59a6be223d1fcb60989e656aa4115 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4ba59a6be223d1fcb60989e656aa4115 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.filter-actions','data' => ['resetRoute' => route('accounting-reports.cash-bank-book.index')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.filter-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['reset-route' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('accounting-reports.cash-bank-book.index'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4ba59a6be223d1fcb60989e656aa4115)): ?>
<?php $attributes = $__attributesOriginal4ba59a6be223d1fcb60989e656aa4115; ?>
<?php unset($__attributesOriginal4ba59a6be223d1fcb60989e656aa4115); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4ba59a6be223d1fcb60989e656aa4115)): ?>
<?php $component = $__componentOriginal4ba59a6be223d1fcb60989e656aa4115; ?>
<?php unset($__componentOriginal4ba59a6be223d1fcb60989e656aa4115); ?>
<?php endif; ?>
    </form>

    <div class="stats-grid" style="margin-bottom:18px">
        <?php if (isset($component)) { $__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Opening Balance','value' => $money($report['opening_balance']),'tone' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Opening Balance','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['opening_balance'])),'tone' => 'warning']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4)): ?>
<?php $attributes = $__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4; ?>
<?php unset($__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4)): ?>
<?php $component = $__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4; ?>
<?php unset($__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Total Inflow','value' => $money($report['total_inflow']),'tone' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Total Inflow','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['total_inflow'])),'tone' => 'success']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4)): ?>
<?php $attributes = $__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4; ?>
<?php unset($__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4)): ?>
<?php $component = $__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4; ?>
<?php unset($__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Total Outflow','value' => $money($report['total_outflow']),'tone' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Total Outflow','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['total_outflow'])),'tone' => 'danger']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4)): ?>
<?php $attributes = $__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4; ?>
<?php unset($__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4)): ?>
<?php $component = $__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4; ?>
<?php unset($__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Closing Balance','value' => $money($report['closing_balance']),'tone' => 'primary']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Closing Balance','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['closing_balance'])),'tone' => 'primary']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4)): ?>
<?php $attributes = $__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4; ?>
<?php unset($__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4)): ?>
<?php $component = $__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4; ?>
<?php unset($__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4); ?>
<?php endif; ?>
    </div>

    <div class="card info-card cash-bank-balance-section">
        <div class="cash-bank-balance-section-head">
            <div>
                <h3>Account Balances</h3>
                <p>Current balance by active cash and bank account for the selected period.</p>
            </div>
        </div>
        <div class="cash-bank-balance-grid">
            <?php $__empty_1 = true; $__currentLoopData = $report['account_balances']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $balance): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="cash-bank-balance-card">
                    <div>
                        <strong><?php echo e($balance->account_name); ?></strong>
                        <small><?php echo e($balance->account_code); ?></small>
                    </div>
                    <b><?php echo e($money($balance->balance)); ?></b>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="muted">No cash/bank account balance found.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($component)) { $__componentOriginal348d767af44a34524c52389edcd7ba09 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal348d767af44a34524c52389edcd7ba09 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.table-card','data' => ['title' => 'Cash / Bank Book Statement','subtitle' => 'Debit to cash/bank is inflow. Credit from cash/bank is outflow.','badge' => $report['total_entries'] . ' entries','badgeClass' => 'badge-primary']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.table-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Cash / Bank Book Statement','subtitle' => 'Debit to cash/bank is inflow. Credit from cash/bank is outflow.','badge' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report['total_entries'] . ' entries'),'badge-class' => 'badge-primary']); ?>
        <div class="table-wrap cash-bank-book-table-wrap">
            <table id="cashBankBookTable" data-client-pagination="true" data-page-size="10">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Account</th>
                        <th>Particulars</th>
                        <th>Reference</th>
                        <th style="text-align:right">Inflow</th>
                        <th style="text-align:right">Outflow</th>
                        <th style="text-align:right">Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $report['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e(\Illuminate\Support\Carbon::parse($row->journal_date)->format('d M Y')); ?></td>
                            <td class="code"><?php echo e($row->voucher_no ?: $row->journal_no); ?></td>
                            <td class="strong"><?php echo e(trim(($row->account_code ? $row->account_code . ' - ' : '') . $row->account_name)); ?></td>
                            <td><?php echo e($row->line_description ?: $row->voucher_description ?: '—'); ?></td>
                            <td><?php echo e($row->reference_no ?: '—'); ?></td>
                            <td style="text-align:right;font-weight:850;color:#067647"><?php echo e((float) $row->debit > 0 ? $money($row->debit) : '—'); ?></td>
                            <td style="text-align:right;font-weight:850;color:#dc2626"><?php echo e((float) $row->credit > 0 ? $money($row->credit) : '—'); ?></td>
                            <td style="text-align:right;font-weight:900"><?php echo e($money($row->running_balance)); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr data-empty="true">
                            <td colspan="8" class="empty-state">No cash/bank movement found for the selected filter.</td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="5" class="strong">Period Total</td>
                        <td style="text-align:right;font-weight:900;color:#067647"><?php echo e($money($report['total_inflow'])); ?></td>
                        <td style="text-align:right;font-weight:900;color:#dc2626"><?php echo e($money($report['total_outflow'])); ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="7" class="strong">Closing Balance</td>
                        <td style="text-align:right;font-weight:900"><?php echo e($money($report['closing_balance'])); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal348d767af44a34524c52389edcd7ba09)): ?>
<?php $attributes = $__attributesOriginal348d767af44a34524c52389edcd7ba09; ?>
<?php unset($__attributesOriginal348d767af44a34524c52389edcd7ba09); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal348d767af44a34524c52389edcd7ba09)): ?>
<?php $component = $__componentOriginal348d767af44a34524c52389edcd7ba09; ?>
<?php unset($__componentOriginal348d767af44a34524c52389edcd7ba09); ?>
<?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .cash-bank-balance-section {
        margin-bottom: 18px;
        padding: 20px;
    }

    .cash-bank-balance-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 14px;
    }

    .cash-bank-balance-section h3 {
        margin: 0 0 4px;
        font-size: 18px;
    }

    .cash-bank-balance-section p {
        margin: 0;
        color: var(--muted);
        font-size: 13px;
    }

    .cash-bank-balance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 12px;
    }

    .cash-bank-balance-card {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        align-items: center;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 14px 16px;
        background: #fff;
        min-width: 0;
    }

    .cash-bank-balance-card strong {
        display: block;
        color: #1d2939;
        font-size: 14px;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .cash-bank-balance-card small {
        display: block;
        color: var(--muted);
        font-weight: 750;
        margin-top: 3px;
    }

    .cash-bank-balance-card b {
        color: var(--primary);
        font-size: 15px;
        white-space: nowrap;
    }

    .financial-report-page .cash-bank-book-table-wrap,
    .financial-report-page .cash-bank-book-table-wrap table {
        width: 100%;
    }
</style>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/accounting_reports/cash_bank_book/index.blade.php ENDPATH**/ ?>