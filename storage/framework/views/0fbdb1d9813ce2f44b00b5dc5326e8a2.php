<?php $__env->startSection('title', 'Cash Flow Statement'); ?>

<?php $__env->startPush('styles'); ?>
    <?php echo $__env->make('accounting_reports.partials.financial-report-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $moneyOrDash = fn ($amount) => abs((float) $amount) >= 0.01 ? $money($amount) : '—';
    $formatDate = function ($date) { try { return \Illuminate\Support\Carbon::parse($date)->format('d M Y'); } catch (\Throwable) { return (string) $date; } };
    $sections = ['Operating Activities', 'Investing Activities', 'Financing Activities'];
?>

<div class="financial-report-page">
    <?php if (isset($component)) { $__componentOriginal8a8a8a52f610621907fb177a37bc2450 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8a8a8a52f610621907fb177a37bc2450 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.page-header','data' => ['title' => 'Cash Flow Statement','subtitle' => 'Cash movement grouped into operating, investing, and financing activities from cash/bank journal line lines.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Cash Flow Statement','subtitle' => 'Cash movement grouped into operating, investing, and financing activities from cash/bank journal line lines.']); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <a class="button btn-outline" href="<?php echo e(route('accounting-reports.cash-flow-statement.export', array_merge(request()->query(), ['format' => 'xlsx']))); ?>">⇩ Export XLSX</a>
            <a class="button btn-outline" href="<?php echo e(route('accounting-reports.cash-flow-statement.export', array_merge(request()->query(), ['format' => 'pdf']))); ?>">⇩ Export PDF</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="<?php echo e(route('accounting-reports.cash-flow-statement.index', request()->query())); ?>">↻ Refresh</a>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Opening Cash','value' => $money($report['opening_cash']),'tone' => 'primary']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Opening Cash','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['opening_cash'])),'tone' => 'primary']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Operating','value' => $money($report['operating_cash_flow']),'tone' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Operating','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['operating_cash_flow'])),'tone' => 'success']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Investing','value' => $money($report['investing_cash_flow']),'tone' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Investing','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['investing_cash_flow'])),'tone' => 'warning']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Financing','value' => $money($report['financing_cash_flow']),'tone' => 'primary']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Financing','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['financing_cash_flow'])),'tone' => 'primary']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Net Cash Flow','value' => $money($report['net_cash_flow']),'tone' => $report['net_cash_flow'] >= 0 ? 'success' : 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Net Cash Flow','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['net_cash_flow'])),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report['net_cash_flow'] >= 0 ? 'success' : 'danger')]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Closing Cash','value' => $money($report['closing_cash']),'tone' => 'primary']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Closing Cash','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['closing_cash'])),'tone' => 'primary']); ?>
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

    <form method="GET" action="<?php echo e(route('accounting-reports.cash-flow-statement.index')); ?>" class="card report-toolbar report-toolbar-seven accounting-filter-sequence">
        <div class="date-range-field">
            <label>Date Range</label>
            <div class="date-range-inputs">
                <input type="date" name="from_date" value="<?php echo e($filters['from_date'] ?? $report['from_date']); ?>">
                <input type="date" name="to_date" value="<?php echo e($filters['to_date'] ?? $report['to_date']); ?>">
            </div>
        </div>
        <div>
            <label>Section</label>
            <select name="section">
                <?php $__currentLoopData = array_merge(['All'], $sections); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($section); ?>" <?php if(($filters['section'] ?? 'All') === $section): echo 'selected'; endif; ?>><?php echo e($section); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <?php if (isset($component)) { $__componentOriginal4ba59a6be223d1fcb60989e656aa4115 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4ba59a6be223d1fcb60989e656aa4115 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.filter-actions','data' => ['resetRoute' => route('accounting-reports.cash-flow-statement.index')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.filter-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['reset-route' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('accounting-reports.cash-flow-statement.index'))]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.table-card','data' => ['title' => 'Cash Flow Detail','subtitle' => $formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date']),'footerLeft' => 'Cash flow is calculated from cash/bank debit and credit detail rows.','footerRight' => 'Classification uses contra account type heuristics.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.table-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Cash Flow Detail','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date'])),'footer-left' => 'Cash flow is calculated from cash/bank debit and credit detail rows.','footer-right' => 'Classification uses contra account type heuristics.']); ?>
        <div class="table-wrap">
            <table class="financial-table">
                <thead><tr><th>Section</th><th>Date</th><th>Voucher</th><th>Cash/Bank Account</th><th>Reference</th><th class="amount">Inflow</th><th class="amount">Outflow</th><th class="amount">Net</th></tr></thead>
                <tbody>
                    <?php $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php $rows = $report['groups']->get($section, collect()); ?>
                        <tr class="group-row"><td colspan="8"><?php echo e($section); ?></td></tr>
                        <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td><?php echo e($row->section); ?></td>
                                <td><?php echo e($formatDate($row->voucher_date)); ?></td>
                                <td class="code"><?php echo e($row->voucher_number); ?></td>
                                <td><?php echo e(trim($row->cash_account_code . ' - ' . $row->cash_account_name)); ?></td>
                                <td><?php echo e($row->reference ?: '—'); ?></td>
                                <td class="amount"><?php echo e($moneyOrDash($row->cash_inflow)); ?></td>
                                <td class="amount"><?php echo e($moneyOrDash($row->cash_outflow)); ?></td>
                                <td class="amount"><?php echo e($money($row->net_cash_flow)); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr data-empty="true"><td colspan="8" class="empty-state">No <?php echo e(strtolower($section)); ?> movement found.</td></tr>
                        <?php endif; ?>
                        <tr class="total-row"><td colspan="7"><?php echo e($section); ?> Total</td><td class="amount"><?php echo e($money($rows->sum('net_cash_flow'))); ?></td></tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <tr class="grand-row"><td colspan="7">Net Cash Flow</td><td class="amount"><?php echo e($money($report['net_cash_flow'])); ?></td></tr>
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/accounting_reports/cash_flow_statement/index.blade.php ENDPATH**/ ?>