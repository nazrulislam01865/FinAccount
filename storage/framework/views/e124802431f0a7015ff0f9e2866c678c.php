<?php $__env->startSection('title', 'Income Statement'); ?>

<?php $__env->startPush('styles'); ?>
    <?php echo $__env->make('accounting_reports.partials.financial-report-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $moneySigned = fn ($amount) => ((float) $amount < 0 ? '(' . $money(abs((float) $amount)) . ')' : $money($amount));
    $moneyShort = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 0);
    $formatDate = function ($date) {
        try {
            return \Illuminate\Support\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    };

    $sections = ['Revenue', 'Cost of Services', 'Administrative & Selling Expenses', 'Financial Expenses', 'Other Income / Loss', 'Income Tax Expense'];
    $selectedSection = $filters['section'] ?? 'All';
    $visibleSections = $selectedSection === 'All' ? $sections : [$selectedSection];
    $isProfit = (float) $report['net_profit'] >= 0;
    $basis = $filters['basis'] ?? 'Accrual';
    $maxBarBase = max(abs((float) $report['revenue']), 1);
    $revenueBar = abs((float) $report['revenue']) > 0 ? 100 : 0;
    $costBar = min(100, (abs((float) $report['cost']) / $maxBarBase) * 100);
    $expenseBar = min(100, (abs((float) $report['expense']) / $maxBarBase) * 100);
    $periodText = $formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date']);
    $ytdText = 'YTD from ' . $formatDate($report['year_start']);
    $totalRows = $report['rows']->count();

    $reportSummaryRows = [
        ['label' => 'Gross Profit Margin', 'value' => number_format((float) $report['gross_margin'], 2) . '%'],
        ['label' => 'Net Profit Margin', 'value' => number_format((float) $report['net_margin'], 2) . '%'],
        ['label' => 'Expense to Revenue', 'value' => number_format((float) $report['expense_ratio'], 2) . '%'],
        ['label' => 'Report Basis', 'value' => $basis],
    ];

    $ytdRows = [
        ['label' => 'Revenue', 'value' => $money($report['ytd_revenue'])],
        ['label' => 'Cost of Services', 'value' => $money($report['ytd_cost'])],
        ['label' => 'Expenses', 'value' => $money($report['ytd_expense'])],
        ['label' => 'Net Profit / Loss', 'value' => $moneySigned($report['ytd_net_profit'])],
    ];
?>

<div class="financial-report-page income-statement-page">
    <?php if (isset($component)) { $__componentOriginal8a8a8a52f610621907fb177a37bc2450 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8a8a8a52f610621907fb177a37bc2450 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.page-header','data' => ['title' => 'Income Statement','subtitle' => 'Review revenue, cost, expenses, tax, and net profit for the selected period. The report is generated from posted accounting lines only.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Income Statement','subtitle' => 'Review revenue, cost, expenses, tax, and net profit for the selected period. The report is generated from posted accounting lines only.']); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <a class="button btn-outline" href="<?php echo e(route('accounting-reports.income-statement.export', array_merge(request()->query(), ['statement_format' => 'management']))); ?>">⇩ Export CSV</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="<?php echo e(route('accounting-reports.income-statement.index', request()->query())); ?>">Generate Report</a>
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

    <div class="report-summary-grid income-template-stats">
        <?php if (isset($component)) { $__componentOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7dc885a20ace0d52ccfcc55f64d09cb4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Total Revenue','value' => $money($report['revenue']),'note' => 'Income posted in this period','tone' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Total Revenue','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['revenue'])),'note' => 'Income posted in this period','tone' => 'success']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Cost of Services','value' => $money($report['cost']),'note' => 'Direct cost / purchase cost','tone' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Cost of Services','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['cost'])),'note' => 'Direct cost / purchase cost','tone' => 'warning']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Administrative & Selling Expenses','value' => $money($report['expense']),'note' => 'Expense ledgers only','tone' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Administrative & Selling Expenses','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($money($report['expense'])),'note' => 'Expense ledgers only','tone' => 'danger']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.stat-card','data' => ['label' => 'Net Profit / Loss','value' => $moneySigned($report['net_profit']),'note' => $isProfit ? 'After cost and expenses' : 'Loss after cost and expenses','tone' => $isProfit ? 'primary' : 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Net Profit / Loss','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($moneySigned($report['net_profit'])),'note' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($isProfit ? 'After cost and expenses' : 'Loss after cost and expenses'),'tone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($isProfit ? 'primary' : 'danger')]); ?>
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

    <form method="GET" action="<?php echo e(route('accounting-reports.income-statement.index')); ?>" class="card report-toolbar report-toolbar-seven accounting-filter-sequence income-template-filter">
        <div class="date-range-field">
            <label>Date Range</label>
            <div class="date-range-inputs">
                <input type="date" name="from_date" value="<?php echo e($filters['from_date'] ?? $report['from_date']); ?>" aria-label="From Date">
                <input type="date" name="to_date" value="<?php echo e($filters['to_date'] ?? $report['to_date']); ?>" aria-label="To Date">
            </div>
        </div>
        <div>
            <label>Section</label>
            <select name="section">
                <?php $__currentLoopData = ['All', 'Revenue', 'Cost of Services', 'Administrative & Selling Expenses', 'Financial Expenses', 'Other Income / Loss', 'Income Tax Expense']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($section); ?>" <?php if(($filters['section'] ?? 'All') === $section): echo 'selected'; endif; ?>><?php echo e($section); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div>
            <label>Statement Format</label>
            <select name="statement_format">
                <option value="management" <?php if(($filters['statement_format'] ?? 'management') === 'management'): echo 'selected'; endif; ?>>Management View</option>
                <option value="audit" <?php if(($filters['statement_format'] ?? 'management') === 'audit'): echo 'selected'; endif; ?>>Audit Format</option>
            </select>
        </div>
        <div>
            <label>Report Basis</label>
            <select name="basis">
                <option value="Accrual" selected>Accrual Basis</option>
            </select>
        </div>
        <div class="field search-field">
            <label>Search Account</label>
            <span>⌕</span>
            <input type="text" name="q" value="<?php echo e($filters['q'] ?? ''); ?>" placeholder="Search revenue, cost, salary, rent...">
        </div>
        <?php if (isset($component)) { $__componentOriginal4ba59a6be223d1fcb60989e656aa4115 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4ba59a6be223d1fcb60989e656aa4115 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.filter-actions','data' => ['resetRoute' => route('accounting-reports.income-statement.index'),'submitLabel' => 'Run']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.filter-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['reset-route' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('accounting-reports.income-statement.index')),'submit-label' => 'Run']); ?>
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

    <div class="report-grid income-template-layout">
        <?php if (isset($component)) { $__componentOriginal348d767af44a34524c52389edcd7ba09 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal348d767af44a34524c52389edcd7ba09 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.table-card','data' => ['title' => 'Profit & Loss Statement','subtitle' => $periodText . ' · ' . $ytdText,'badge' => $isProfit ? 'Profit Position' : 'Loss Position','badgeClass' => $isProfit ? 'badge-success' : 'badge-warning','class' => 'income-template-card']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.table-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Profit & Loss Statement','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($periodText . ' · ' . $ytdText),'badge' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($isProfit ? 'Profit Position' : 'Loss Position'),'badge-class' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($isProfit ? 'badge-success' : 'badge-warning'),'class' => 'income-template-card']); ?>
            <div class="table-wrap">
                <table id="incomeStatementTable" class="financial-table income-table income-statement-template-table" data-client-pagination="true" data-page-size="10">
                    <thead>
                        <tr>
                            <th>Particulars</th>
                            <th>Account Code</th>
                            <th>Account Type</th>
                            <th class="amount">Amount</th>
                            <th class="amount">YTD Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $visibleSections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sectionName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php ($rows = $report['groups']->get($sectionName, collect())); ?>
                            <tr class="group-row section-row"><td colspan="5"><?php echo e($sectionName); ?></td></tr>
                            <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td class="strong"><?php echo e($row->account_name); ?></td>
                                    <td class="code"><?php echo e($row->account_code); ?></td>
                                    <td>
                                        <span class="badge <?php echo e($row->account_type === 'Income' ? 'badge-success' : ($sectionName === 'Cost of Services' ? 'badge-warning' : 'badge-danger')); ?>">
                                            <?php echo e($sectionName === 'Cost of Services' ? 'Cost of Services' : $row->account_type); ?>

                                        </span>
                                    </td>
                                    <td class="amount"><?php echo e($moneySigned($row->amount)); ?></td>
                                    <td class="amount"><?php echo e($moneySigned($row->ytd_amount)); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr class="empty-row"><td colspan="5">No posted <?php echo e(strtolower($sectionName)); ?> movement found for the selected filter.</td></tr>
                            <?php endif; ?>
                            <tr class="total-row">
                                <td colspan="3">Total <?php echo e($sectionName); ?></td>
                                <td class="amount"><?php echo e($moneySigned($rows->sum('amount'))); ?></td>
                                <td class="amount"><?php echo e($moneySigned($rows->sum('ytd_amount'))); ?></td>
                            </tr>
                            <?php if($sectionName === 'Cost of Services' || ($selectedSection === 'All' && $sectionName === 'Revenue' && ! $report['groups']->has('Cost of Services'))): ?>
                                <tr class="gross-row">
                                    <td colspan="3">Gross Profit</td>
                                    <td class="amount"><?php echo e($moneySigned($report['gross_profit'])); ?></td>
                                    <td class="amount"><?php echo e($moneySigned($report['ytd_gross_profit'])); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                        <?php if($selectedSection === 'All'): ?>
                            <tr class="<?php echo e($isProfit ? 'profit-row' : 'loss-row'); ?>">
                                <td colspan="3">Net <?php echo e($isProfit ? 'Profit' : 'Loss'); ?></td>
                                <td class="amount"><?php echo e($moneySigned($report['net_profit'])); ?></td>
                                <td class="amount"><?php echo e($moneySigned($report['ytd_net_profit'])); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="report-note">
                <strong>Accounting check:</strong> Income Statement includes only Income and Expense class ledgers. Cash, bank, receivable, payable, asset, liability, and equity ledgers are intentionally excluded and remain in Trial Balance / Balance Sheet.
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

        <aside class="report-side-stack">
            <div class="card side-card">
                <h3>Report Summary</h3>
                <?php $__currentLoopData = $reportSummaryRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="ratio-row"><span><?php echo e($row['label']); ?></span><strong><?php echo e($row['value']); ?></strong></div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <div class="mini-chart" aria-label="Income statement amount comparison">
                    <div class="bar-item">
                        <span><b>Revenue</b><em><?php echo e($moneyShort($report['revenue'])); ?></em></span>
                        <div class="bar"><div class="bar-fill green-bg" style="width: <?php echo e($revenueBar); ?>%"></div></div>
                    </div>
                    <div class="bar-item">
                        <span><b>Cost</b><em><?php echo e($moneyShort($report['cost'])); ?></em></span>
                        <div class="bar"><div class="bar-fill orange-bg" style="width: <?php echo e($costBar); ?>%"></div></div>
                    </div>
                    <div class="bar-item">
                        <span><b>Expense</b><em><?php echo e($moneyShort($report['expense'])); ?></em></span>
                        <div class="bar"><div class="bar-fill" style="width: <?php echo e($expenseBar); ?>%"></div></div>
                    </div>
                </div>
            </div>

            <div class="card side-card">
                <h3>YTD Position</h3>
                <?php $__currentLoopData = $ytdRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="ratio-row"><span><?php echo e($row['label']); ?></span><strong><?php echo e($row['value']); ?></strong></div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <div class="card side-card">
                <h3>Accounting Guardrails</h3>
                <div class="guardrail-list">
                    <div class="guardrail-item"><span>✓</span><p>Report reads posted voucher details as journal-line equivalent source records.</p></div>
                    <div class="guardrail-item"><span>✓</span><p>Income is calculated as Credit − Debit; Expense is calculated as Debit − Credit.</p></div>
                    <div class="guardrail-item"><span>✓</span><p>Due collection / supplier payment rules do not recognize income or expense again.</p></div>
                </div>
            </div>

            <div class="card side-card">
                <h3>Quick Notes</h3>
                <div class="insight">
                    <div class="insight-icon">💡</div>
                    <div><strong>Rule-driven posting</strong><p>Users record a transaction head and amount; the accounting rule creates the debit and credit lines used by this report.</p></div>
                </div>
                <div class="insight">
                    <div class="insight-icon">✓</div>
                    <div><strong>Clean report logic</strong><p>Only revenue, cost, and operating expense ledgers appear here. Balance Sheet accounts stay outside the Income Statement.</p></div>
                </div>
            </div>
        </aside>
    </div>

    <div class="print-note">Income Statement report printed from HisebGhor Accounting System. Period: <?php echo e($periodText); ?>.</div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/accounting_reports/income_statement/index.blade.php ENDPATH**/ ?>