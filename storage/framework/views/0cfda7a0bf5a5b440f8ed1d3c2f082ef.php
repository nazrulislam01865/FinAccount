<?php $__env->startSection('title', 'Expense Report'); ?>

<?php $__env->startPush('styles'); ?>
    <?php echo $__env->make('accounting_reports.partials.financial-report-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $moneyOrDash = fn ($amount) => abs((float) $amount) >= 0.01 ? $money($amount) : '—';
    $formatDate = function ($date) { try { return \Illuminate\Support\Carbon::parse($date)->format('d M Y'); } catch (\Throwable) { return (string) $date; } };
?>

<div class="financial-report-page">
    <?php if (isset($component)) { $__componentOriginal8a8a8a52f610621907fb177a37bc2450 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8a8a8a52f610621907fb177a37bc2450 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.page-header','data' => ['title' => 'Expense Report','subtitle' => 'Expense movements from posted journal lines.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Expense Report','subtitle' => 'Expense movements from posted journal lines.']); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <a class="button btn-outline" href="<?php echo e(route('accounting-reports.expense-report.export', array_merge(request()->query(), ['format' => 'xlsx']))); ?>">⇩ Export XLSX</a>
            <a class="button btn-outline" href="<?php echo e(route('accounting-reports.expense-report.export', array_merge(request()->query(), ['format' => 'pdf']))); ?>">⇩ Export PDF</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="<?php echo e(route('accounting-reports.expense-report.index', request()->query())); ?>">↻ Refresh</a>
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

    <div class="report-summary-grid report-summary-grid-six">
        <?php if (isset($component)) { $__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Total Expense','value' => $money($report['total_amount']),'tone' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Total Expense','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['total_amount'])),'tone' => 'danger']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Entries','value' => $report['entry_count'],'tone' => 'muted']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Entries','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report['entry_count']),'tone' => 'muted']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Expense Ledgers','value' => $report['by_account']->count(),'tone' => 'muted']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Expense Ledgers','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report['by_account']->count()),'tone' => 'muted']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Average Entry','value' => $money($report['entry_count'] ? $report['total_amount'] / max($report['entry_count'], 1) : 0),'tone' => 'primary']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Average Entry','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['entry_count'] ? $report['total_amount'] / max($report['entry_count'], 1) : 0)),'tone' => 'primary']); ?>
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
        <?php if (isset($component)) { $__componentOriginal297a03ff20958767b597c6bde6cdb262 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal297a03ff20958767b597c6bde6cdb262 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.info-card','data' => ['title' => 'Period','rows' => [['label' => 'From', 'value' => $formatDate($report['from_date'])], ['label' => 'To', 'value' => $formatDate($report['to_date'])]]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.info-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Period','rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([['label' => 'From', 'value' => $formatDate($report['from_date'])], ['label' => 'To', 'value' => $formatDate($report['to_date'])]])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal297a03ff20958767b597c6bde6cdb262)): ?>
<?php $attributes = $__attributesOriginal297a03ff20958767b597c6bde6cdb262; ?>
<?php unset($__attributesOriginal297a03ff20958767b597c6bde6cdb262); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal297a03ff20958767b597c6bde6cdb262)): ?>
<?php $component = $__componentOriginal297a03ff20958767b597c6bde6cdb262; ?>
<?php unset($__componentOriginal297a03ff20958767b597c6bde6cdb262); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal297a03ff20958767b597c6bde6cdb262 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal297a03ff20958767b597c6bde6cdb262 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.info-card','data' => ['title' => 'Source','rows' => [['label' => 'Basis', 'value' => 'Accrual'], ['label' => 'Truth', 'value' => 'journal_lines']]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.info-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Source','rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([['label' => 'Basis', 'value' => 'Accrual'], ['label' => 'Truth', 'value' => 'journal_lines']])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal297a03ff20958767b597c6bde6cdb262)): ?>
<?php $attributes = $__attributesOriginal297a03ff20958767b597c6bde6cdb262; ?>
<?php unset($__attributesOriginal297a03ff20958767b597c6bde6cdb262); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal297a03ff20958767b597c6bde6cdb262)): ?>
<?php $component = $__componentOriginal297a03ff20958767b597c6bde6cdb262; ?>
<?php unset($__componentOriginal297a03ff20958767b597c6bde6cdb262); ?>
<?php endif; ?>
    </div>

    <form method="GET" action="<?php echo e(route('accounting-reports.expense-report.index')); ?>" class="card report-toolbar report-toolbar-seven accounting-filter-sequence">
        <div class="date-range-field"><label>Date Range</label><div class="date-range-inputs"><input type="date" name="from_date" value="<?php echo e($filters['from_date'] ?? $report['from_date']); ?>"><input type="date" name="to_date" value="<?php echo e($filters['to_date'] ?? $report['to_date']); ?>"></div></div>
        <div><label>Expense Ledger</label><select name="account_id"><option value="">All Expense Ledgers</option><?php $__currentLoopData = $report['accounts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($account->id); ?>" <?php if((string)($filters['account_id'] ?? '') === (string)$account->id): echo 'selected'; endif; ?>><?php echo e($account->account_code); ?> - <?php echo e($account->account_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
        <div><label>Transaction Head</label><select name="transaction_head_id"><option value="">All Heads</option><?php $__currentLoopData = $report['transaction_heads']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $head): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($head->id); ?>" <?php if((string)($filters['transaction_head_id'] ?? '') === (string)$head->id): echo 'selected'; endif; ?>><?php echo e($head->head_code); ?> - <?php echo e($head->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
        <div><label>Party</label><select name="party_id"><option value="">All Parties</option><?php $__currentLoopData = $report['parties']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $party): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($party->id); ?>" <?php if((string)($filters['party_id'] ?? '') === (string)$party->id): echo 'selected'; endif; ?>><?php echo e($party->party_code); ?> - <?php echo e($party->party_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
        <div class="field search-field"><label>Search</label><span>⌕</span><input type="text" name="q" value="<?php echo e($filters['q'] ?? ''); ?>" placeholder="Voucher, ledger, party..."></div>
        <?php if (isset($component)) { $__componentOriginal4ba59a6be223d1fcb60989e656aa4115 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4ba59a6be223d1fcb60989e656aa4115 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.filter-actions','data' => ['resetRoute' => route('accounting-reports.expense-report.index')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.filter-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['reset-route' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('accounting-reports.expense-report.index'))]); ?>
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

    <?php if (isset($component)) { $__componentOriginal348d767af44a34524c52389edcd7ba09 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal348d767af44a34524c52389edcd7ba09 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.table-card','data' => ['title' => 'Expense Detail','subtitle' => $formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date']),'footerLeft' => 'Expense report includes Expense account lines only.','footerRight' => 'Amount = debit minus credit.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.table-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Expense Detail','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date'])),'footer-left' => 'Expense report includes Expense account lines only.','footer-right' => 'Amount = debit minus credit.']); ?>
        <div class="table-wrap"><table class="financial-table"><thead><tr><th>Date</th><th>Voucher</th><th>Head</th><th>Party</th><th>Ledger</th><th>Reference</th><th class="amount">Debit</th><th class="amount">Credit</th><th class="amount">Expense Amount</th></tr></thead><tbody>
            <?php $__empty_1 = true; $__currentLoopData = $report['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr><td><?php echo e($formatDate($row->voucher_date)); ?></td><td class="code"><?php echo e($row->voucher_number); ?></td><td><?php echo e($row->transaction_head ?: '—'); ?></td><td><?php echo e($row->party_name ?: '—'); ?></td><td><?php echo e(trim($row->account_code . ' - ' . $row->account_name)); ?></td><td><?php echo e($row->reference ?: '—'); ?></td><td class="amount"><?php echo e($moneyOrDash($row->debit)); ?></td><td class="amount"><?php echo e($moneyOrDash($row->credit)); ?></td><td class="amount"><?php echo e($money($row->amount)); ?></td></tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr data-empty="true"><td colspan="9" class="empty-state">No expense movement found.</td></tr>
            <?php endif; ?>
            <tr class="grand-row"><td colspan="8">Total Expense</td><td class="amount"><?php echo e($money($report['total_amount'])); ?></td></tr>
        </tbody></table></div>
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/accounting_reports/expense_report/index.blade.php ENDPATH**/ ?>