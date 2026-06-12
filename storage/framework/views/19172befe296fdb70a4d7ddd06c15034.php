<?php $__env->startSection('title', 'Manual Journal Entry | HisebGhor'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $currentUser = auth()->user();
    $canPostManualJournal = $currentUser?->hasPermission('transactions.journal.create') ?? false;
    $money = fn ($amount) => number_format((float) $amount, 2);
?>

<div class="page-header">
    <div>
        <p class="eyebrow">Phase 2 Accounting Engine</p>
        <h2>Manual Journal Entry</h2>
        <span>Accountant/Admin-only JV screen for balanced debit-credit adjustments.</span>
    </div>
    <a href="<?php echo e(route('transactions.create')); ?>" class="button btn-secondary">Back to Transaction Entry</a>
</div>

<div class="summary-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="card prototype-stat"><span>Current Financial Year</span><strong><?php echo e($currentFinancialYear?->display_name ?? 'Not configured'); ?></strong><small>Posting date must be open/unlocked</small></div>
    <div class="card prototype-stat"><span>Posting Ledgers</span><strong><?php echo e($ledgers->count()); ?></strong><small>Only active Level 4 ledgers are shown</small></div>
    <div class="card prototype-stat"><span>Recent Manual JVs</span><strong><?php echo e($recentJournals->count()); ?></strong><small>Latest journal vouchers</small></div>
</div>

<div class="content-grid setup-grid" style="grid-template-columns:minmax(0, 1.35fr) minmax(320px, .65fr);gap:18px;align-items:start;">
    <form class="card form-card" id="manualJournalForm" data-frontend-form data-action="<?php echo e(route('api.manual-journals.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="section-heading">
            <h3>Journal Information</h3>
            <span>Debit and credit totals must match before posting.</span>
        </div>

        <div class="form-grid two-cols">
            <div>
                <label>Journal Date <span>*</span></label>
                <input type="date" name="journal_date" value="<?php echo e($defaultJournalDate); ?>" required>
            </div>
            <div>
                <label>Status <span>*</span></label>
                <select name="status" required>
                    <option value="Posted">Post Immediately</option>
                    <option value="Draft">Save Draft</option>
                </select>
            </div>
            <div>
                <label>Reference</label>
                <input type="text" name="reference" placeholder="Adjustment reference">
            </div>
            <div>
                <label>Narration</label>
                <input type="text" name="narration" placeholder="Reason for manual journal">
            </div>
        </div>

        <div class="section-heading" style="margin-top:18px">
            <h3>Debit / Credit Lines</h3>
            <span>Use party only when the selected ledger is party-control.</span>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="journalLinesTable">
                <thead>
                    <tr>
                        <th style="min-width:260px">Ledger</th>
                        <th style="min-width:180px">Party/Sub-ledger</th>
                        <th style="min-width:130px">Debit</th>
                        <th style="min-width:130px">Credit</th>
                        <th style="min-width:190px">Line Narration</th>
                        <th style="width:70px">Action</th>
                    </tr>
                </thead>
                <tbody id="journalLinesBody">
                    <?php for($i = 0; $i < 2; $i++): ?>
                        <tr data-line-row>
                            <td>
                                <select name="lines[<?php echo e($i); ?>][ledger_id]" data-ledger-select required>
                                    <option value="">Select Level 4 ledger</option>
                                    <?php $__currentLoopData = $ledgers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ledger): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($ledger->id); ?>" data-party-control="<?php echo e($ledger->is_party_control || $ledger->ledger_type === 'Party Control' ? 1 : 0); ?>">
                                            <?php echo e($ledger->account_code); ?> - <?php echo e($ledger->account_name); ?>

                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </td>
                            <td>
                                <select name="lines[<?php echo e($i); ?>][party_id]" data-party-select>
                                    <option value="">No party</option>
                                    <?php $__currentLoopData = $parties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $party): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($party->id); ?>"><?php echo e($party->party_code); ?> - <?php echo e($party->party_name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </td>
                            <td><input type="number" min="0" step="0.01" name="lines[<?php echo e($i); ?>][debit_amount]" value="0.00" data-debit></td>
                            <td><input type="number" min="0" step="0.01" name="lines[<?php echo e($i); ?>][credit_amount]" value="0.00" data-credit></td>
                            <td><input type="text" name="lines[<?php echo e($i); ?>][line_narration]" placeholder="Optional"></td>
                            <td><button type="button" class="btn-ghost" data-remove-line>Remove</button></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" style="text-align:right">Total</th>
                        <th id="totalDebit">0.00</th>
                        <th id="totalCredit">0.00</th>
                        <th colspan="2" id="balanceStatus">Not balanced</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="form-actions" style="margin-top:16px">
            <button type="button" class="btn-secondary" id="addLineButton">+ Add Line</button>
            <?php if($canPostManualJournal): ?>
                <button type="submit" class="btn-primary">Save Manual Journal</button>
            <?php endif; ?>
        </div>
    </form>

    <aside class="card help-panel">
        <h3>SRS Guardrails</h3>
        <p>Manual Journal is only for accountant/admin adjustments. Normal transaction users should continue using Add Transaction.</p>
        <ul class="check-list">
            <li>Only Level 4 posting ledgers are selectable.</li>
            <li>Debit and credit totals must be equal.</li>
            <li>Party is required for party-control ledgers.</li>
            <li>JV voucher and journal lines are generated together.</li>
        </ul>

        <h3 style="margin-top:18px">Recent Manual Journals</h3>
        <div class="mini-list">
            <?php $__empty_1 = true; $__currentLoopData = $recentJournals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $journal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="mini-list-item">
                    <strong><?php echo e($journal->journal_no); ?></strong>
                    <span><?php echo e($journal->journal_date?->format('d M Y')); ?> · <?php echo e($journal->status); ?> · BDT <?php echo e($money($journal->total_debit)); ?></span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p>No manual journal posted yet.</p>
            <?php endif; ?>
        </div>
    </aside>
</div>

<template id="journalLineTemplate">
    <tr data-line-row>
        <td>
            <select data-ledger-select required>
                <option value="">Select Level 4 ledger</option>
                <?php $__currentLoopData = $ledgers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ledger): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($ledger->id); ?>" data-party-control="<?php echo e($ledger->is_party_control || $ledger->ledger_type === 'Party Control' ? 1 : 0); ?>">
                        <?php echo e($ledger->account_code); ?> - <?php echo e($ledger->account_name); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </td>
        <td>
            <select data-party-select>
                <option value="">No party</option>
                <?php $__currentLoopData = $parties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $party): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($party->id); ?>"><?php echo e($party->party_code); ?> - <?php echo e($party->party_name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </td>
        <td><input type="number" min="0" step="0.01" value="0.00" data-debit></td>
        <td><input type="number" min="0" step="0.01" value="0.00" data-credit></td>
        <td><input type="text" placeholder="Optional" data-line-narration></td>
        <td><button type="button" class="btn-ghost" data-remove-line>Remove</button></td>
    </tr>
</template>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    const form = document.getElementById('manualJournalForm');
    const body = document.getElementById('journalLinesBody');
    const template = document.getElementById('journalLineTemplate');
    const totalDebit = document.getElementById('totalDebit');
    const totalCredit = document.getElementById('totalCredit');
    const balanceStatus = document.getElementById('balanceStatus');

    function money(value) {
        return Number(value || 0).toFixed(2);
    }

    function reindex() {
        [...body.querySelectorAll('[data-line-row]')].forEach((row, index) => {
            row.querySelector('[data-ledger-select]').name = `lines[${index}][ledger_id]`;
            row.querySelector('[data-party-select]').name = `lines[${index}][party_id]`;
            row.querySelector('[data-debit]').name = `lines[${index}][debit_amount]`;
            row.querySelector('[data-credit]').name = `lines[${index}][credit_amount]`;
            const narration = row.querySelector('[data-line-narration]') || row.querySelector('input[name$="[line_narration]"]');
            narration.name = `lines[${index}][line_narration]`;
        });
    }

    function calculate() {
        let debit = 0;
        let credit = 0;

        body.querySelectorAll('[data-line-row]').forEach(row => {
            debit += Number(row.querySelector('[data-debit]')?.value || 0);
            credit += Number(row.querySelector('[data-credit]')?.value || 0);
        });

        totalDebit.textContent = money(debit);
        totalCredit.textContent = money(credit);
        const balanced = debit > 0 && debit.toFixed(2) === credit.toFixed(2);
        balanceStatus.textContent = balanced ? 'Balanced' : 'Not balanced';
        balanceStatus.className = balanced ? 'text-success' : 'text-danger';
    }

    document.getElementById('addLineButton')?.addEventListener('click', () => {
        body.appendChild(template.content.firstElementChild.cloneNode(true));
        reindex();
        calculate();
    });

    document.addEventListener('click', event => {
        if (!event.target.matches('[data-remove-line]')) return;
        const rows = body.querySelectorAll('[data-line-row]');
        if (rows.length <= 2) {
            window.AccountingUI?.showToast?.('Manual Journal needs at least two lines.');
            return;
        }
        event.target.closest('[data-line-row]').remove();
        reindex();
        calculate();
    });

    document.addEventListener('input', event => {
        if (event.target.matches('[data-debit]') && Number(event.target.value || 0) > 0) {
            event.target.closest('[data-line-row]').querySelector('[data-credit]').value = '0.00';
        }
        if (event.target.matches('[data-credit]') && Number(event.target.value || 0) > 0) {
            event.target.closest('[data-line-row]').querySelector('[data-debit]').value = '0.00';
        }
        calculate();
    });

    form?.addEventListener('submit', async event => {
        event.preventDefault();
        reindex();
        calculate();

        const button = event.submitter || form.querySelector('button[type="submit"]');
        const originalText = button?.textContent;
        if (button) {
            button.disabled = true;
            button.textContent = 'Saving...';
        }

        try {
            const response = await fetch(form.dataset.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: new FormData(form),
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                const message = data.message || Object.values(data.errors || {})?.flat()?.[0] || 'Manual Journal save failed.';
                throw new Error(message);
            }

            window.AccountingUI?.showToast?.(data.message || 'Manual Journal saved.');
            window.location.href = data.redirect || window.location.href;
        } catch (error) {
            window.AccountingUI?.showToast?.(error.message || 'Manual Journal save failed.');
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    });

    reindex();
    calculate();
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/manual-journals/index.blade.php ENDPATH**/ ?>