<?php $__env->startSection('title', 'Statement of Profit or Loss and Other Comprehensive Income'); ?>

<?php $__env->startPush('styles'); ?>
    <?php echo $__env->make('accounting_reports.partials.financial-report-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <style>
        .audit-income-page .audit-statement-card {
            background: #fff;
            border: 1px solid #d8dee8;
            border-radius: 14px;
            padding: 28px;
            max-width: 980px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        }

        .audit-income-page .audit-header {
            text-align: center;
            margin-bottom: 18px;
            color: #111827;
        }

        .audit-income-page .audit-company-name {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .audit-income-page .audit-title {
            font-size: 14px;
            font-weight: 700;
            margin-top: 4px;
        }

        .audit-income-page .audit-period {
            font-size: 12px;
            font-weight: 600;
            margin-top: 2px;
        }

        .audit-income-page .audit-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            color: #111827;
        }

        .audit-income-page .audit-table th,
        .audit-income-page .audit-table td {
            border: 1px solid #374151;
            padding: 7px 9px;
            vertical-align: middle;
        }

        .audit-income-page .audit-table th {
            text-align: center;
            font-weight: 800;
            background: #f9fafb;
        }

        .audit-income-page .audit-table .amount {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .audit-income-page .audit-table .note-cell {
            text-align: center;
            width: 90px;
        }

        .audit-income-page .audit-table .particular-cell {
            width: 48%;
        }

        .audit-income-page .audit-section-heading td {
            border-top: 0;
            border-bottom: 0;
            font-weight: 800;
            background: #fff;
        }

        .audit-income-page .audit-total-row td {
            font-weight: 800;
            border-top: 2px solid #111827;
        }

        .audit-income-page .audit-note {
            text-align: center;
            font-size: 11px;
            margin: 16px 0 38px;
            color: #374151;
        }

        .audit-income-page .audit-signatures {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 28px;
            margin-top: 34px;
            align-items: end;
            text-align: center;
            font-size: 12px;
        }

        .audit-income-page .signature-line {
            border-top: 1px solid #111827;
            padding-top: 7px;
            font-weight: 700;
        }

        .audit-income-page .audit-footer-meta {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 30px;
            font-size: 11px;
            color: #374151;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .audit-income-page,
            .audit-income-page * {
                visibility: visible;
            }

            .audit-income-page {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
            }

            .audit-income-page .report-toolbar,
            .audit-income-page .report-page-header,
            .audit-income-page .print-hide {
                display: none !important;
            }

            .audit-income-page .audit-statement-card {
                border: none;
                box-shadow: none;
                padding: 10mm;
                max-width: none;
            }
        }
    </style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $formatDate = function ($date) {
        try {
            return \Illuminate\Support\Carbon::parse($date)->format('jS F, Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    };

    $auditAmount = function ($amount) {
        if ($amount === null || $amount === '') {
            return '-';
        }

        $amount = (float) $amount;

        return $amount < 0
            ? '(' . number_format(abs($amount), 2) . ')'
            : number_format($amount, 2);
    };

    $periodTitle = 'For the year ended ' . $formatDate($report['to_date']);
    $companyName = $company?->company_name ?? config('app.name');
?>

<div class="financial-report-page audit-income-page">
    <?php if (isset($component)) { $__componentOriginal8a8a8a52f610621907fb177a37bc2450 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8a8a8a52f610621907fb177a37bc2450 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.page-header','data' => ['title' => 'Income Statement - Audit Format','subtitle' => 'Bangladesh audit-style statement format with notes and comparative year column.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Income Statement - Audit Format','subtitle' => 'Bangladesh audit-style statement format with notes and comparative year column.']); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <a class="button btn-outline" href="<?php echo e(route('accounting-reports.income-statement.export', array_merge(request()->query(), ['statement_format' => 'audit']))); ?>">⇩ Export CSV</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="<?php echo e(route('accounting-reports.income-statement.index', array_merge(request()->query(), ['statement_format' => 'management']))); ?>">Management View</a>
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

    <form method="GET" action="<?php echo e(route('accounting-reports.income-statement.index')); ?>" class="card report-toolbar report-toolbar-seven accounting-filter-sequence income-template-filter print-hide">
        <input type="hidden" name="statement_format" value="audit">
        <div class="date-range-field">
            <label>Date Range</label>
            <div class="date-range-inputs">
                <input type="date" name="from_date" value="<?php echo e($filters['from_date'] ?? $report['from_date']); ?>" aria-label="From Date">
                <input type="date" name="to_date" value="<?php echo e($filters['to_date'] ?? $report['to_date']); ?>" aria-label="To Date">
            </div>
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
            <input type="text" name="q" value="<?php echo e($filters['q'] ?? ''); ?>" placeholder="Search revenue, expenses, tax...">
        </div>
        <?php if (isset($component)) { $__componentOriginal4ba59a6be223d1fcb60989e656aa4115 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4ba59a6be223d1fcb60989e656aa4115 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report.filter-actions','data' => ['resetRoute' => route('accounting-reports.income-statement.index', ['statement_format' => 'audit']),'submitLabel' => 'Run']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report.filter-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['reset-route' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('accounting-reports.income-statement.index', ['statement_format' => 'audit'])),'submit-label' => 'Run']); ?>
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

    <div class="audit-statement-card">
        <div class="audit-header">
            <div class="audit-company-name"><?php echo e($companyName); ?></div>
            <div class="audit-title">Statement of Profit or Loss and Other Comprehensive Income</div>
            <div class="audit-period"><?php echo e($periodTitle); ?></div>
        </div>

        <table class="audit-table">
            <thead>
                <tr>
                    <th rowspan="2" class="particular-cell">Particulars</th>
                    <th rowspan="2" class="note-cell">Notes</th>
                    <th colspan="2">Amount in Taka</th>
                </tr>
                <tr>
                    <th><?php echo e($report['current_period_label']); ?></th>
                    <th><?php echo e($report['previous_period_label']); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $report['audit_statement']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if($row['section_heading']): ?>
                        <tr class="audit-section-heading">
                            <td colspan="4"><?php echo e($row['label']); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr class="<?php echo \Illuminate\Support\Arr::toCssClasses(['audit-total-row' => $row['bold']]); ?>">
                            <td><?php echo e($row['label']); ?></td>
                            <td class="note-cell"><?php echo e($row['note']); ?></td>
                            <td class="amount"><?php echo e($auditAmount($row['current'])); ?></td>
                            <td class="amount"><?php echo e($auditAmount($row['previous'])); ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <div class="audit-note">
            The annexed notes form an integral part of these Financial Statements.
        </div>

        <div class="audit-signatures">
            <div class="signature-line">Managing Director</div>
            <div class="signature-line">Director</div>
            <div class="signature-line">Director</div>
            <div class="signature-line">Auditor</div>
        </div>

        <div class="audit-footer-meta">
            <div>
                <strong>Place:</strong> <?php echo e($company?->address ? 'Dhaka, Bangladesh' : 'Dhaka'); ?><br>
                <strong>Date:</strong> <?php echo e(now()->format('d/m/Y')); ?>

            </div>
            <div>
                Prepared from posted accounting entries of HisebGhor Accounting System.
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/accounting_reports/income_statement/audit.blade.php ENDPATH**/ ?>