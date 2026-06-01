<?php $__env->startSection('title', 'Trial Balance'); ?>

<?php $__env->startPush('styles'); ?>
    <?php echo $__env->make('accounting_reports.partials.financial-report-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $moneyOrDash = fn ($amount) => abs((float) $amount) >= 0.01 ? $money($amount) : '—';
    $formatDate = function ($date) {
        try {
            return \Illuminate\Support\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    };
    $summaryRows = [
        ['label' => 'Period', 'value' => $formatDate($report['from_date']) . ' - ' . $formatDate($report['to_date'])],
        ['label' => 'Basis', 'value' => 'Accrual'],
        ['label' => 'Currency', 'value' => $currency ?? 'BDT'],
        ['label' => 'Ledger Count', 'value' => (string) $report['rows']->count()],
    ];
    $analysisRows = [
        ['label' => 'Highest Debit', 'value' => $report['max_debit'] ? $report['max_debit']->account_name . ' - ' . $money($report['max_debit']->closing_debit) : '—'],
        ['label' => 'Highest Credit', 'value' => $report['max_credit'] ? $report['max_credit']->account_name . ' - ' . $money($report['max_credit']->closing_credit) : '—'],
        ['label' => 'Zero Balance', 'value' => (string) $report['zero_count']],
        ['label' => 'Difference', 'value' => $money(abs($report['difference']))],
    ];
?>

<div class="financial-report-page">
    <?php if (isset($component)) { $__componentOriginal8a8a8a52f610621907fb177a37bc2450 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8a8a8a52f610621907fb177a37bc2450 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.page-header','data' => ['title' => 'Trial Balance','subtitle' => 'Ledger-wise debit and credit balances generated from posted journal line lines.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Trial Balance','subtitle' => 'Ledger-wise debit and credit balances generated from posted journal line lines.']); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <a class="button btn-outline" href="<?php echo e(route('accounting-reports.trial-balance.export', array_merge(request()->query(), ['format' => 'xlsx']))); ?>">⇩ Export XLSX</a>
            <a class="button btn-outline" href="<?php echo e(route('accounting-reports.trial-balance.export', array_merge(request()->query(), ['format' => 'pdf']))); ?>">⇩ Export PDF</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="<?php echo e(route('accounting-reports.trial-balance.index', request()->query())); ?>">↻ Refresh Report</a>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Total Closing Debit','value' => $money($report['total_debit']),'tone' => 'primary']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Total Closing Debit','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['total_debit'])),'tone' => 'primary']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Total Closing Credit','value' => $money($report['total_credit']),'tone' => 'primary']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Total Closing Credit','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['total_credit'])),'tone' => 'primary']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Difference','value' => $money(abs($report['difference'])),'tone' => $report['is_balanced'] ? 'success' : 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Difference','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money(abs($report['difference']))),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report['is_balanced'] ? 'success' : 'danger')]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Report Status','value' => $report['is_balanced'] ? 'Balanced' : 'Unbalanced','tone' => $report['is_balanced'] ? 'success' : 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Report Status','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report['is_balanced'] ? 'Balanced' : 'Unbalanced'),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report['is_balanced'] ? 'success' : 'danger')]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.info-card','data' => ['title' => 'Report Summary','rows' => $summaryRows]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.info-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Report Summary','rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($summaryRows)]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.info-card','data' => ['title' => 'Quick Analysis','rows' => $analysisRows]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.info-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Quick Analysis','rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($analysisRows)]); ?>
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

    <form method="GET" action="<?php echo e(route('accounting-reports.trial-balance.index')); ?>" class="card report-toolbar report-toolbar-seven accounting-filter-sequence">
        <div class="date-range-field">
            <label>Date Range</label>
            <div class="date-range-inputs">
                <input type="date" name="from_date" value="<?php echo e($filters['from_date'] ?? $report['from_date']); ?>" aria-label="From Date">
                <input type="date" name="to_date" value="<?php echo e($filters['to_date'] ?? $report['to_date']); ?>" aria-label="To Date">
            </div>
        </div>
        <div>
            <label>Account Group</label>
            <select name="account_type">
                <option value="All">All</option>
                <?php $__currentLoopData = $report['account_types']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($type); ?>" <?php if(($filters['account_type'] ?? 'All') === $type): echo 'selected'; endif; ?>><?php echo e($type); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div>
            <label>Balance Type</label>
            <select name="balance_type">
                <?php $__currentLoopData = ['All', 'Debit', 'Credit', 'Zero']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($type); ?>" <?php if(($filters['balance_type'] ?? 'All') === $type): echo 'selected'; endif; ?>><?php echo e($type); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div class="field search-field">
            <label>Search Ledger</label>
            <span>⌕</span>
            <input type="text" name="q" value="<?php echo e($filters['q'] ?? ''); ?>" placeholder="Ledger code or account name...">
        </div>
        <?php if (isset($component)) { $__componentOriginal4ba59a6be223d1fcb60989e656aa4115 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4ba59a6be223d1fcb60989e656aa4115 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.filter-actions','data' => ['resetRoute' => route('accounting-reports.trial-balance.index')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.filter-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['reset-route' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('accounting-reports.trial-balance.index'))]); ?>
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

    <div class="report-grid report-grid-full">
        <?php if (isset($component)) { $__componentOriginal348d767af44a34524c52389edcd7ba09 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal348d767af44a34524c52389edcd7ba09 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.table-card','data' => ['title' => 'Ledger Balances','subtitle' => $formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date']) . ' · ' . $report['rows']->count() . ' ledger(s)','badge' => $report['is_balanced'] ? 'Balanced' : 'Difference Found','badgeClass' => $report['is_balanced'] ? 'badge-success' : 'badge-warning','footerLeft' => 'Reports use posted journal_lines only.','footerRight' => 'Draft and cancelled vouchers are excluded.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.table-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Ledger Balances','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date']) . ' · ' . $report['rows']->count() . ' ledger(s)'),'badge' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report['is_balanced'] ? 'Balanced' : 'Difference Found'),'badge-class' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report['is_balanced'] ? 'badge-success' : 'badge-warning'),'footer-left' => 'Reports use posted journal_lines only.','footer-right' => 'Draft and cancelled vouchers are excluded.']); ?>
            <div class="table-wrap">
                <table id="trialBalanceTable" class="financial-table trial-table" data-client-pagination="true" data-page-size="10">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Ledger Account</th>
                            <th>Group</th>
                            <th class="amount">Opening Debit</th>
                            <th class="amount">Opening Credit</th>
                            <th class="amount">Period Debit</th>
                            <th class="amount">Period Credit</th>
                            <th class="amount">Closing Debit</th>
                            <th class="amount">Closing Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $report['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupName => $rows): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php
                                $groupOpeningDebit = $rows->sum('opening_debit');
                                $groupOpeningCredit = $rows->sum('opening_credit');
                                $groupPeriodDebit = $rows->sum('period_debit');
                                $groupPeriodCredit = $rows->sum('period_credit');
                                $groupClosingDebit = $rows->sum('closing_debit');
                                $groupClosingCredit = $rows->sum('closing_credit');
                            ?>
                            <tr class="group-row"><td colspan="9"><?php echo e($groupName); ?></td></tr>
                            <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td class="code"><?php echo e($row->account_code); ?></td>
                                    <td class="strong"><?php echo e($row->account_name); ?></td>
                                    <td><span class="badge badge-primary"><?php echo e($row->account_type); ?></span></td>
                                    <td class="amount"><?php echo e($moneyOrDash($row->opening_debit)); ?></td>
                                    <td class="amount"><?php echo e($moneyOrDash($row->opening_credit)); ?></td>
                                    <td class="amount"><?php echo e($moneyOrDash($row->period_debit)); ?></td>
                                    <td class="amount"><?php echo e($moneyOrDash($row->period_credit)); ?></td>
                                    <td class="amount"><?php echo e($moneyOrDash($row->closing_debit)); ?></td>
                                    <td class="amount"><?php echo e($moneyOrDash($row->closing_credit)); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <tr class="total-row">
                                <td colspan="3"><?php echo e($groupName); ?> Total</td>
                                <td class="amount"><?php echo e($moneyOrDash($groupOpeningDebit)); ?></td>
                                <td class="amount"><?php echo e($moneyOrDash($groupOpeningCredit)); ?></td>
                                <td class="amount"><?php echo e($moneyOrDash($groupPeriodDebit)); ?></td>
                                <td class="amount"><?php echo e($moneyOrDash($groupPeriodCredit)); ?></td>
                                <td class="amount"><?php echo e($moneyOrDash($groupClosingDebit)); ?></td>
                                <td class="amount"><?php echo e($moneyOrDash($groupClosingCredit)); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr data-empty="true"><td colspan="9" class="empty-state">No ledger balance found for the selected filter.</td></tr>
                        <?php endif; ?>
                        <tr class="grand-row">
                            <td colspan="7">Grand Total</td>
                            <td class="amount"><?php echo e($money($report['total_debit'])); ?></td>
                            <td class="amount"><?php echo e($money($report['total_credit'])); ?></td>
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
    <div class="print-note">Trial Balance report printed from HisebGhor Accounting System.</div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/accounting_reports/trial_balance/index.blade.php ENDPATH**/ ?>