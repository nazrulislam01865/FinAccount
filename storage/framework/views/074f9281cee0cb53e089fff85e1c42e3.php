<?php $__env->startSection('title', 'Transaction Entry | HisebGhor'); ?>

<?php $__env->startSection('content'); ?>
<?php
    use App\Models\TransactionHead;

    $money = fn ($value) => number_format((float) $value, 2);

    $transactionCategories = collect(TransactionHead::transactionCategories())->map(fn ($category) => [
        'name' => $category,
        'example' => match ($category) {
            'Sales' => 'Cash Sales, Credit Sales',
            'Purchase' => 'Cash Purchase, Credit Purchase',
            'Receipt' => 'Customer Collection, Advance Received',
            'Payment' => 'Supplier Payment, Rent Payment, Salary Payment',
            'Banking' => 'Bank Interest Income, Bank Charge, Bank Transfer',
            'Expense' => 'Rent, Utility, Office Expense',
            'Income' => 'Service Income, Other Income',
            'Owner / Equity' => 'Owner Investment, Owner Withdrawal',
            'Asset' => 'Asset Purchase, Asset Sale',
            'Loan' => 'Loan Received, Loan Repayment',
            'Employee' => 'Salary Payment, Advance to Employee',
            'Opening' => 'Opening Balance Entry',
            'Adjustment' => 'Journal Adjustment',
            default => 'General Transaction',
        },
    ])->values();

    $headPayload = $transactionHeads->map(function ($head) use ($transactionHeadProfiles) {
        $profile = $transactionHeadProfiles[$head->id] ?? [];

        return [
            'id' => $head->id,
            'head_code' => $head->head_code,
            'name' => $head->name,
            'display_name' => trim(($head->head_code ? $head->head_code . ' - ' : '') . $head->name),
            'nature' => TransactionHead::natureFromCategory($head->category),
            'category' => TransactionHead::normaliseCategory($head->category),
            'raw_category' => $head->category,
            'transaction_screen' => $profile['transaction_screen'] ?? 'Transaction Entry',
            'default_party_type_id' => $profile['party_type_id'] ?? null,
            'default_party_type_name' => $profile['party_type_name'] ?? null,
            'payment_method_required' => (bool) ($profile['payment_method_required'] ?? false),
            'party_required_mode' => $profile['party_required_mode'] ?? 'No',
            'help_text' => $head->help_text,
            'requires_party' => (bool) ($profile['party_required'] ?? false),
            'requires_reference' => (bool) ($profile['requires_reference'] ?? false),
            'settlements' => collect($profile['settlements'] ?? [])->map(fn ($settlement) => [
                'id' => $settlement->id,
                'name' => $settlement->name,
                'code' => $settlement->code,
                'display_name' => $settlement->name,
            ])->values(),
        ];
    })->values();

    $partyPayload = $parties->map(fn ($party) => [
        'id' => $party->id,
        'party_name' => $party->party_name,
        'party_code' => $party->party_code,
        'party_type_id' => $party->party_type_id,
        'party_type_name' => $party->partyType?->name,
        'linked_ledger_account_id' => $party->linked_ledger_account_id,
        'display_name' => trim(($party->party_code ? $party->party_code . ' - ' : '') . $party->party_name),
    ])->values();

    $cashBankPayload = $cashBankAccounts->map(fn ($account) => [
        'id' => $account->id,
        'cash_bank_code' => $account->cash_bank_code,
        'cash_bank_name' => $account->cash_bank_name,
        'type' => $account->type,
        'linked_ledger_account_id' => $account->linked_ledger_account_id,
        'linked_ledger_name' => $account->linkedLedger?->display_name,
        'display_name' => trim(($account->cash_bank_code ? $account->cash_bank_code . ' - ' : '') . $account->cash_bank_name),
    ])->values();

    $currentUser = auth()->user();
    $canPostTransaction = $currentUser?->hasPermission('transactions.create') ?? false;
    $canSaveDraftTransaction = $currentUser?->hasAnyPermission(['transactions.create', 'transactions.draft']) ?? false;
    $isDraftOnlyTransactionUser = !$canPostTransaction && $canSaveDraftTransaction;
?>

<div class="prototype-page transaction-entry-page">
    <div class="prototype-hero transaction-entry-hero">
        <div>
            <span class="page-label">Transaction Entry</span>
            <h2>Transaction Entry</h2>
            <p>Record business transactions without accounting complexity. The Accounting Engine prepares debit and credit automatically from setup rules.</p>
        </div>
        <div class="prototype-actions">
            <button class="btn-outline" id="newBtn" type="button">+ New Transaction</button>
            <button class="btn-ghost" type="button" data-scroll-target="#recentTransactions">Recent Transactions</button>
        </div>
    </div>

    <div class="prototype-stats four transaction-stats-redesign">
        <div class="card prototype-stat"><span>Today Cash In</span><strong class="green">BDT <?php echo e($money($todayCashIn)); ?></strong><small>Receipt and collections</small></div>
        <div class="card prototype-stat"><span>Today Cash Out</span><strong class="red">BDT <?php echo e($money($todayCashOut)); ?></strong><small>Payments and expenses</small></div>
        <div class="card prototype-stat"><span>Due Payable</span><strong class="orange">BDT <?php echo e($money($duePayable)); ?></strong><small>We need to pay</small></div>
        <div class="card prototype-stat"><span>Due Receivable</span><strong class="blue">BDT <?php echo e($money($dueReceivable)); ?></strong><small>We need to collect</small></div>
    </div>

    <div class="prototype-grid transaction-entry-grid">
        <section class="card prototype-preview-card transaction-summary-card">
            <div class="prototype-card-header transaction-summary-header">
                <div>
                    <h3>Transaction Summary</h3>
                    <p>Live business summary for the transaction you are preparing.</p>
                </div>
            </div>
            <div class="prototype-preview-list transaction-summary-list">
                <div><span>Voucher No.</span><strong id="voucherNo">—</strong></div>
                <div><span>Voucher Type</span><strong id="voucherTypeSummary">—</strong></div>
                <div><span>Nature</span><strong id="nature">—</strong></div>
                <div><span>Party Effect</span><strong id="partyEffect">—</strong></div>
                <div><span>Cash/Bank Effect</span><strong id="cashEffect">—</strong></div>
                <div><span>Status</span><strong><span class="badge badge-warning" id="summaryStatus">Draft</span></strong></div>
            </div>
        </section>

        <div class="card prototype-card transaction-form-card">
            <div class="prototype-card-header">
                <div>
                    <h3>Record a Transaction</h3>
                    <p>First choose the business category, then choose the exact transaction head under that category.</p>
                </div>
                <span class="badge badge-primary">Rule Based</span>
            </div>

            <div class="prototype-card-body">
                <form
                    id="transactionForm"
                    class="prototype-form"
                    data-preview-url="<?php echo e(route('api.transactions.preview')); ?>"
                    data-store-url="<?php echo e(route('api.transactions.store')); ?>"
                    data-heads-url="<?php echo e(route('api.dropdowns.transaction-heads')); ?>"
                    data-settlements-url="<?php echo e(route('api.dropdowns.settlement-types')); ?>"
                    data-parties-url="<?php echo e(route('api.dropdowns.parties')); ?>"
                    data-cash-bank-url="<?php echo e(route('api.dropdowns.cash-bank-accounts')); ?>"
                    data-can-post="<?php echo e($canPostTransaction ? '1' : '0'); ?>"
                    data-can-draft="<?php echo e($canSaveDraftTransaction ? '1' : '0'); ?>"
                >
                    <?php echo csrf_field(); ?>
                    <input type="hidden" id="statusInput" name="status" value="<?php echo e($isDraftOnlyTransactionUser ? 'Draft' : 'Posted'); ?>">
                    <input type="hidden" id="transactionCategory" name="transaction_category" value="">
                    <input type="hidden" id="screen" value="">

                    <div class="prototype-guidance inline-guidance">
                        <div class="prototype-guidance-icon">💡</div>
                        <div>
                            <strong>Simple entry. Accounting happens behind the screen.</strong>
                            <p id="guidanceText">Select a Transaction Category to load matching Transaction Heads only.</p>
                        </div>
                    </div>

                    <div class="prototype-form-grid two">
                        <div class="prototype-field">
                            <label for="date">Date <span class="required">*</span></label>
                            <input type="date" id="date" name="voucher_date" value="<?php echo e($defaultVoucherDate ?? now()->toDateString()); ?>" required>
                        </div>

                        <div class="prototype-field full transaction-category-field">
                            <label>Transaction Category <span class="required">*</span></label>
                            <div class="tx-category-selector" id="transactionCategorySelector" role="group" aria-label="Transaction Category">
                                <?php $__currentLoopData = $transactionCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <button
                                        type="button"
                                        class="tx-category-option"
                                        data-category="<?php echo e($category['name']); ?>"
                                        title="<?php echo e($category['example']); ?>"
                                    >
                                        <span><?php echo e($category['name']); ?></span>
                                    </button>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                            <div class="hint" id="categoryHint">This is the high-level grouping, for example: Sales, Purchase, Receipt, Payment, Banking, Expense, Income, Owner / Equity, Asset, Loan, Employee, Opening, and Adjustment.</div>
                        </div>

                        <div class="prototype-field full">
                            <label for="headToggle">Transaction Head <span class="required">*</span></label>
                            <div class="tx-head-combobox" id="headCombobox">
                                <button
                                    type="button"
                                    id="headToggle"
                                    class="tx-head-toggle"
                                    aria-haspopup="listbox"
                                    aria-expanded="false"
                                    aria-controls="headSuggestionPanel"
                                    disabled
                                >
                                    <span id="headSelectedText">Select transaction category first</span>
                                    <span class="tx-head-toggle-arrow" aria-hidden="true">▾</span>
                                </button>
                                <div class="tx-head-panel tx-hidden" id="headSuggestionPanel">
                                    <input
                                        type="search"
                                        id="headSearch"
                                        class="tx-head-search"
                                        placeholder="Search transaction head"
                                        autocomplete="off"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-expanded="false"
                                        aria-controls="headSuggestionList"
                                        disabled
                                    >
                                    <div class="tx-head-suggestions" id="headSuggestionList" role="listbox" aria-label="Transaction Head suggestions"></div>
                                </div>
                            </div>
                            <select id="head" name="transaction_head_id" class="tx-head-value-select" aria-hidden="true" tabindex="-1" disabled>
                                <option value="">Select Transaction Category first</option>
                            </select>
                            <div class="hint" id="headHint">Only transaction heads mapped under the selected category will be shown.</div>
                        </div>

                        <div class="prototype-field money-wrap">
                            <label for="amount">Amount <span class="required">*</span></label>
                            <span>BDT</span>
                            <input type="number" id="amount" name="amount" value="10000" min="0.01" step="0.01" required>
                        </div>

                        <div class="prototype-field tx-dynamic-field tx-hidden" id="partyTypeWrap">
                            <label for="partyType">Person / party type</label>
                            <input type="text" id="partyType" readonly class="readonly-field" placeholder="Auto from selected head and settlement rule">
                        </div>

                        <div class="prototype-field tx-dynamic-field tx-hidden" id="partyWrap">
                            <label for="party" id="partyLabel">Select person / business <span class="required" id="partyRequired">*</span></label>
                            <select id="party" name="party_id">
                                <option value="">Select saved Party / Person</option>
                            </select>
                            <div class="hint" id="partyHint">Shown only when the selected setup rule needs a customer, supplier, employee, owner, or other party.</div>
                        </div>

                        <div class="prototype-field tx-dynamic-field tx-hidden" id="paymentWrap">
                            <label for="settlement">Payment / Settlement Method <span class="required">*</span></label>
                            <select id="settlement" name="settlement_type_id" required>
                                <option value="">Select transaction head first</option>
                            </select>
                            <div class="hint" id="settlementHint">Only settlement types mapped with the selected Transaction Head are shown.</div>
                        </div>

                        <div class="prototype-field tx-dynamic-field tx-hidden" id="cashBankWrap">
                            <label for="cashBank">Cash / Bank Account</label>
                            <select id="cashBank" name="cash_bank_account_id">
                                <option value="">Select cash/bank account</option>
                            </select>
                            <div class="hint">Shown only when the selected settlement needs a cash or bank account.</div>
                        </div>

                        <div class="prototype-field tx-dynamic-field tx-hidden" id="referenceWrap">
                            <label for="reference">Reference number <span class="required" id="referenceRequired" style="display:none">*</span></label>
                            <input id="reference" name="reference" placeholder="Example: BILL-1029, CHQ-889, INV-1005">
                            <div class="hint" id="referenceHint">Optional unless the selected Transaction Head requires it.</div>
                        </div>

                        <div class="prototype-field full tx-dynamic-field tx-hidden" id="notesWrap">
                            <label for="notes">Narration</label>
                            <textarea id="notes" name="notes" placeholder="Write what happened in normal business language"></textarea>
                        </div>

                        <div class="prototype-field full tx-dynamic-field tx-hidden" id="attachmentWrap">
                            <label for="attachment">Attach document / receipt</label>
                            <input type="file" id="attachment" name="attachment">
                            <div class="hint">Receipt, bill, invoice, or proof. Maximum 5 MB.</div>
                        </div>
                    </div>

                    <?php if($isDraftOnlyTransactionUser): ?>
                        <div class="validation warn" style="display:block;margin-top:12px">
                            Your role can enter transactions as draft only. Final posting is locked until an authorized user reviews/posts it.
                        </div>
                    <?php endif; ?>

                    <div class="prototype-form-actions">
                        <button type="button" class="btn-ghost" id="clearBtn">Clear</button>
                        <?php if($canSaveDraftTransaction): ?>
                            <button type="button" class="btn-outline" id="draftBtn">Save Draft</button>
                        <?php endif; ?>
                        <?php if($canPostTransaction): ?>
                            <button type="submit" class="btn-primary" id="postBtn">Post Transaction</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <section class="card prototype-preview-card transaction-ledger-preview-card" id="transactionAccountingPreview">
            <div class="prototype-card-header transaction-ledger-preview-header">
                <div><h3>Accounting Preview</h3><p>Generated by rules before saving. Full-width preview keeps Ledger Account, Debit, and Credit readable.</p></div>
                <span class="badge badge-warning" id="mappingStatus">Waiting</span>
            </div>
            <div class="prototype-card-body transaction-ledger-preview-body">
                <div class="table-wrap ledger-preview-wrap transaction-ledger-preview-table-wrap">
                    <table class="ledger-table transaction-ledger-preview-table">
                        <thead><tr><th>Ledger Account</th><th>Debit</th><th>Credit</th></tr></thead>
                        <tbody id="ledgerRows">
                            <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:22px">Select transaction information to preview ledger entries.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="transaction-ledger-validation-wrap">
                    <div class="validation warn" id="validationBox">Select transaction information to generate preview.</div>
                </div>
            </div>
        </section>
    </div>

    <section class="card prototype-card prototype-section" id="recentTransactions">
        <div class="prototype-card-header">
            <div>
                <h3>Recent Transactions</h3>
                <p>Latest posted or draft vouchers created through the transaction engine.</p>
            </div>
            <button class="btn-ghost" type="button" onclick="window.location.reload()">Refresh</button>
        </div>
        <div class="prototype-card-body">
            <div class="table-wrap prototype-table-wrap always-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transaction Head</th>
                            <th>Party</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $recentTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td><?php echo e(optional($transaction->voucher_date)->format('Y-m-d')); ?></td>
                                <td class="strong"><?php echo e($transaction->transactionHead?->name ?? 'Transaction'); ?></td>
                                <td><?php echo e($transaction->party?->party_name ?? 'No Party'); ?></td>
                                <td class="amount">BDT <?php echo e($money($transaction->amount)); ?></td>
                                <td><?php echo e($transaction->settlementType?->name ?? '—'); ?></td>
                                <td class="amount">BDT <?php echo e($money($transaction->total_debit ?: $transaction->details->sum('debit'))); ?></td>
                                <td class="amount">BDT <?php echo e($money($transaction->total_credit ?: $transaction->details->sum('credit'))); ?></td>
                                <td><span class="badge <?php echo e($transaction->status === 'Posted' ? 'badge-success' : 'badge-warning'); ?>"><?php echo e($transaction->status); ?></span></td>
                                <td><span class="badge badge-neutral"><?php echo e($transaction->voucher_number); ?></span></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="9" class="muted" style="text-align:center;padding:24px">No transactions yet. Post your first transaction to see it here.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .transaction-entry-page .transaction-entry-grid {
        grid-template-columns: 1fr !important;
        gap: 18px;
        align-items: start;
    }
    .transaction-entry-page .transaction-summary-card,
    .transaction-entry-page .transaction-form-card,
    .transaction-entry-page .transaction-ledger-preview-card {
        grid-column: 1 / -1;
        min-width: 0;
    }
    .transaction-entry-page .transaction-summary-card {
        padding: 22px !important;
    }
    .transaction-entry-page .transaction-summary-header {
        margin-bottom: 16px;
        align-items: center;
    }
    .transaction-entry-page .transaction-summary-list {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 14px;
    }
    .transaction-entry-page .transaction-summary-list > div {
        min-height: 78px;
        padding: 14px 16px;
        border: 1px solid #dbe4f0;
        border-radius: 16px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .transaction-entry-page .transaction-summary-list span {
        color: var(--muted);
        font-size: 13px;
        font-weight: 800;
        letter-spacing: .02em;
    }
    .transaction-entry-page .transaction-summary-list strong {
        color: #1f2937;
        font-size: 18px;
        font-weight: 900;
        text-align: right;
        white-space: nowrap;
    }
    .transaction-entry-page .transaction-form-card {
        width: 100%;
    }
    .transaction-entry-page .transaction-ledger-preview-card {
        padding: 0 !important;
        overflow: hidden !important;
    }
    .transaction-entry-page .transaction-ledger-preview-header {
        align-items: center;
    }
    .transaction-entry-page .transaction-ledger-preview-body {
        padding: 0 !important;
    }
    .transaction-entry-page .transaction-ledger-preview-table-wrap {
        width: 100%;
        max-height: none;
        border: 0 !important;
        border-radius: 0 !important;
        overflow-x: auto !important;
        overflow-y: visible !important;
    }
    .transaction-entry-page .transaction-ledger-preview-table {
        width: 100% !important;
        min-width: 760px !important;
        margin: 0;
        table-layout: fixed;
    }
    .transaction-entry-page .transaction-ledger-preview-table th:first-child,
    .transaction-entry-page .transaction-ledger-preview-table td:first-child {
        width: 58%;
    }
    .transaction-entry-page .transaction-ledger-preview-table th:nth-child(2),
    .transaction-entry-page .transaction-ledger-preview-table th:nth-child(3),
    .transaction-entry-page .transaction-ledger-preview-table td:nth-child(2),
    .transaction-entry-page .transaction-ledger-preview-table td:nth-child(3) {
        width: 21%;
        text-align: right;
        white-space: nowrap;
    }
    .transaction-entry-page .transaction-ledger-preview-table .total-row td {
        font-size: 16px;
    }
    .transaction-entry-page .transaction-ledger-validation-wrap {
        padding: 0 22px 22px;
        background: #fff;
    }
    .transaction-entry-page .transaction-ledger-validation-wrap .validation {
        margin-top: 16px;
    }
    .transaction-entry-page .ledger-account-name {
        min-width: 0 !important;
        overflow-wrap: anywhere;
    }
    .transaction-entry-page .ledger-account-rule {
        overflow-wrap: anywhere;
    }
    .transaction-category-field label { margin-bottom: 10px; }
    .tx-category-selector {
        border: 1px solid #cfd9e8;
        border-radius: 18px;
        padding: 14px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        background: #fff;
        box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.03);
    }
    .tx-category-option {
        border: 1px solid #d7e2f1;
        border-radius: 10px;
        background: #f8fbff;
        color: #43546a;
        font-weight: 800;
        padding: 12px 16px;
        min-height: 44px;
        cursor: pointer;
        transition: all .16s ease;
    }
    .tx-category-option:hover {
        border-color: #94b5e8;
        background: #eff6ff;
        color: #1d4ed8;
    }
    .tx-category-option.is-active {
        border-color: #2563eb;
        background: #2563eb;
        color: #fff;
        box-shadow: 0 10px 18px rgba(37, 99, 235, .18);
    }
    .tx-head-combobox {
        position: relative;
        width: 100%;
    }
    .tx-head-toggle {
        width: 100%;
        min-height: 58px;
        border: 1px solid #cfd9e8;
        border-radius: 14px;
        background: #fff;
        color: #0f172a;
        font: inherit;
        font-weight: 800;
        text-align: left;
        padding: 14px 48px 14px 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
    }
    .tx-head-toggle:hover,
    .tx-head-toggle:focus,
    .tx-head-toggle.is-open {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, .10);
        outline: none;
    }
    .tx-head-toggle:disabled {
        cursor: not-allowed;
        background: #f8fafc;
        color: #94a3b8;
        box-shadow: none;
        border-color: #d7e2f1;
    }
    .tx-head-toggle-arrow {
        position: absolute;
        right: 18px;
        color: #2563eb;
        font-size: 24px;
        line-height: 1;
        transition: transform .16s ease;
    }
    .tx-head-toggle.is-open .tx-head-toggle-arrow {
        transform: rotate(180deg);
    }
    .tx-head-panel {
        position: absolute;
        z-index: 60;
        top: calc(100% + 2px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #9ca3af;
        border-radius: 4px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .18);
        overflow: hidden;
    }
    .tx-head-search {
        width: calc(100% - 16px) !important;
        min-height: 42px !important;
        margin: 8px !important;
        border: 1px solid #94a3b8 !important;
        border-radius: 4px !important;
        padding: 8px 10px !important;
        font-size: 15px !important;
        font-weight: 600 !important;
        box-shadow: none !important;
    }
    .tx-head-search:focus {
        border-color: #2563eb !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .12) !important;
    }
    .tx-head-value-select {
        display: none !important;
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }
    .tx-head-suggestions {
        max-height: 280px;
        overflow-y: auto;
        background: #fff;
        padding: 0;
    }
    .tx-head-suggestion {
        width: 100%;
        border: 0;
        border-radius: 0;
        background: #fff;
        text-align: left;
        cursor: pointer;
        padding: 10px 12px;
        color: #111827;
        font-weight: 600;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }
    .tx-head-suggestion:hover,
    .tx-head-suggestion.is-active {
        background: #3b82f6;
        color: #fff;
    }
    .tx-head-suggestion.is-selected {
        background: #eff6ff;
        color: #1d4ed8;
    }
    .tx-head-suggestion.is-selected:hover,
    .tx-head-suggestion.is-selected.is-active {
        background: #3b82f6;
        color: #fff;
    }
    .tx-head-suggestion-code {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }
    .tx-head-suggestion:hover .tx-head-suggestion-code,
    .tx-head-suggestion.is-active .tx-head-suggestion-code {
        color: #eff6ff;
    }
    .tx-head-no-result {
        padding: 12px;
        color: #64748b;
        font-weight: 700;
    }
    .tx-hidden { display: none !important; }
    .prototype-field.soft-required label { color: #b45309; }
    @media (max-width: 1320px) {
        .transaction-entry-page .transaction-entry-grid { grid-template-columns: 1fr !important; }
        .transaction-entry-page .transaction-summary-list { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 768px) {
        .tx-category-option { width: 100%; text-align: left; }
        .transaction-entry-page .transaction-summary-card { padding: 16px !important; }
        .transaction-entry-page .transaction-summary-list { grid-template-columns: 1fr; }
        .transaction-entry-page .transaction-summary-list > div { min-height: 64px; }
        .transaction-entry-page .transaction-ledger-preview-header {
            display: grid;
            gap: 12px;
        }
        .transaction-entry-page .transaction-ledger-preview-table {
            min-width: 680px !important;
        }
        .transaction-entry-page .transaction-ledger-preview-table th:first-child,
        .transaction-entry-page .transaction-ledger-preview-table td:first-child {
            width: 54%;
        }
        .transaction-entry-page .transaction-ledger-preview-table th:nth-child(2),
        .transaction-entry-page .transaction-ledger-preview-table th:nth-child(3),
        .transaction-entry-page .transaction-ledger-preview-table td:nth-child(2),
        .transaction-entry-page .transaction-ledger-preview-table td:nth-child(3) {
            width: 23%;
        }
        .transaction-entry-page .transaction-ledger-validation-wrap {
            padding: 0 16px 16px;
        }
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const categoryDefinitions = <?php echo json_encode($transactionCategories, 15, 512) ?>;
    const fallbackHeads = <?php echo json_encode($headPayload, 15, 512) ?>;
    const fallbackParties = <?php echo json_encode($partyPayload, 15, 512) ?>;
    const fallbackCashBanks = <?php echo json_encode($cashBankPayload, 15, 512) ?>;
    const form = document.getElementById('transactionForm');
    if (!form) return;

    const canPostTransaction = form.dataset.canPost === '1';
    const canSaveDraftTransaction = form.dataset.canDraft === '1';

    const date = document.getElementById('date');
    const screen = document.getElementById('screen');
    const transactionCategory = document.getElementById('transactionCategory');
    const categorySelector = document.getElementById('transactionCategorySelector');
    const categoryHint = document.getElementById('categoryHint');
    const headToggle = document.getElementById('headToggle');
    const headSelectedText = document.getElementById('headSelectedText');
    const headSuggestionPanel = document.getElementById('headSuggestionPanel');
    const headSearch = document.getElementById('headSearch');
    const head = document.getElementById('head');
    const headHint = document.getElementById('headHint');
    const headSuggestionList = document.getElementById('headSuggestionList');
    const partyType = document.getElementById('partyType');
    const party = document.getElementById('party');
    const partyLabel = document.getElementById('partyLabel');
    const partyRequiredElement = () => document.getElementById('partyRequired');
    const amount = document.getElementById('amount');
    const settlement = document.getElementById('settlement');
    const settlementHint = document.getElementById('settlementHint');
    const cashBank = document.getElementById('cashBank');
    const reference = document.getElementById('reference');
    const referenceRequired = document.getElementById('referenceRequired');
    const referenceHint = document.getElementById('referenceHint');
    const notes = document.getElementById('notes');
    const attachment = document.getElementById('attachment');
    const statusInput = document.getElementById('statusInput');
    const guidanceText = document.getElementById('guidanceText');

    const ledgerRows = document.getElementById('ledgerRows');
    const mappingStatus = document.getElementById('mappingStatus');
    const validationBox = document.getElementById('validationBox');
    const voucherNo = document.getElementById('voucherNo');
    const voucherTypeSummary = document.getElementById('voucherTypeSummary');
    const nature = document.getElementById('nature');
    const partyEffect = document.getElementById('partyEffect');
    const cashEffect = document.getElementById('cashEffect');
    const summaryStatus = document.getElementById('summaryStatus');

    const dynamicWraps = {
        partyType: document.getElementById('partyTypeWrap'),
        party: document.getElementById('partyWrap'),
        payment: document.getElementById('paymentWrap'),
        cashBank: document.getElementById('cashBankWrap'),
        reference: document.getElementById('referenceWrap'),
        notes: document.getElementById('notesWrap'),
        attachment: document.getElementById('attachmentWrap'),
    };

    let heads = [];
    let cashBanks = fallbackCashBanks;
    let previewTimer = null;
    let lastPreviewOk = false;
    let isBooting = true;

    function csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; }

    async function parseJsonResponse(response) {
        const raw = await response.text();
        if (!raw) return {};
        try { return JSON.parse(raw); }
        catch (error) {
            throw { message: response.ok ? 'Unexpected invalid server response.' : `Server returned ${response.status}. Check storage/logs/laravel.log.`, raw };
        }
    }

    function showToast(message) {
        if (window.AccountingUI?.showToast) { window.AccountingUI.showToast(message); return; }
        alert(message);
    }

    function money(value) {
        return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function text(value, fallback = '—') {
        const stringValue = String(value ?? '').trim();
        return stringValue === '' ? fallback : stringValue;
    }

    function bool(value) {
        return value === true || value === 1 || value === '1' || value === 'true' || value === 'Yes' || value === 'Required';
    }

    function normalisePartyMode(value) {
        const mode = String(value || '').toLowerCase();
        if (['yes', 'required', 'require', 'always'].includes(mode)) return 'Required';
        if (mode === 'optional') return 'Optional';
        return 'No';
    }

    function normaliseCategory(category, name = '', natureValue = '') {
        const raw = String(category || natureValue || '').trim();
        const haystack = `${raw} ${name || ''} ${natureValue || ''}`.toLowerCase();
        const allowed = categoryDefinitions.map((item) => item.name);
        const exact = allowed.find((item) => item.toLowerCase() === raw.toLowerCase());
        if (exact) return exact;
        if (haystack.includes('opening')) return 'Opening';
        if (haystack.includes('employee') || haystack.includes('salary')) return 'Employee';
        if (haystack.includes('loan')) return 'Loan';
        if (haystack.includes('owner') || haystack.includes('equity') || haystack.includes('capital') || haystack.includes('withdrawal') || haystack.includes('drawing')) return 'Owner / Equity';
        if (haystack.includes('asset')) return 'Asset';
        if (haystack.includes('bank') || haystack.includes('transfer') || haystack.includes('charge') || haystack.includes('interest')) return 'Banking';
        if (haystack.includes('income') || haystack.includes('service')) return 'Income';
        if (haystack.includes('expense') || haystack.includes('rent') || haystack.includes('utility') || haystack.includes('office')) return 'Expense';
        if (haystack.includes('purchase') || haystack.includes('supplier due') || haystack.includes('payable')) return 'Purchase';
        if (haystack.includes('sales') || haystack.includes('sale') || haystack.includes('customer due') || haystack.includes('receivable')) return 'Sales';
        if (haystack.includes('receipt') || haystack.includes('collection') || haystack.includes('received')) return 'Receipt';
        if (haystack.includes('payment') || haystack.includes('paid')) return 'Payment';
        if (haystack.includes('adjust') || haystack.includes('journal') || haystack.includes('other')) return 'Adjustment';
        return 'Payment';
    }

    function endpoint(baseUrl, params = {}) {
        const query = new URLSearchParams();
        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null && String(value).trim() !== '') query.set(key, value);
        });
        return query.toString() ? `${baseUrl}?${query.toString()}` : baseUrl;
    }

    async function getRows(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        const result = await parseJsonResponse(response);
        if (!response.ok) throw new Error(result.message || `Dropdown request failed (${response.status}): ${url}`);
        return Array.isArray(result.data) ? result.data : [];
    }

    function normaliseHead(item) {
        const settlements = item.settlement_types || item.settlements || [];
        const category = normaliseCategory(item.category || item.raw_category, item.name || item.display_name, item.nature);
        return {
            id: item.id,
            head_code: item.head_code || '',
            name: item.name || item.display_name,
            display_name: item.display_name || [item.head_code, item.name].filter(Boolean).join(' - '),
            nature: item.nature,
            category,
            raw_category: item.raw_category || item.category || item.nature,
            transaction_screen: item.transaction_screen || 'Transaction Entry',
            default_party_type_id: item.default_party_type_id || null,
            default_party_type_name: item.default_party_type_name || '',
            party_required_mode: normalisePartyMode(item.party_required_mode || (item.requires_party ? 'Required' : 'No')),
            payment_method_required: Boolean(Number(item.payment_method_required) || item.payment_method_required === true),
            help_text: item.help_text || '',
            requires_party: bool(item.requires_party) || normalisePartyMode(item.party_required_mode) === 'Required',
            requires_reference: Boolean(Number(item.requires_reference) || item.requires_reference === true),
            settlements: settlements.map((settlementItem) => ({ id: settlementItem.id, name: settlementItem.name || settlementItem.display_name, code: settlementItem.code || '', display_name: settlementItem.display_name || settlementItem.name })),
        };
    }

    function normaliseCashBank(item) {
        return { id: item.id, name: item.display_name || [item.cash_bank_code, item.cash_bank_name || item.name].filter(Boolean).join(' - '), type: item.type || '', ledger: item.linked_ledger_name || '' };
    }

    function headById(id) { return heads.find((item) => String(item.id) === String(id)); }
    function selectedSettlement() { return settlement.selectedOptions[0] || null; }
    function settlementText() { const option = selectedSettlement(); return `${option?.dataset.code || ''} ${option?.textContent || ''}`.toUpperCase(); }

    function selectedRequirement() {
        const selectedHead = headById(head.value);
        const option = selectedSettlement();
        const partyMode = normalisePartyMode(option?.dataset.partyMode || selectedHead?.party_required_mode || 'No');
        const partyTypeId = option?.dataset.partyTypeId || selectedHead?.default_party_type_id || '';
        const partyTypeName = option?.dataset.partyTypeName || selectedHead?.default_party_type_name || '';
        const paymentRequired = bool(option?.dataset.paymentRequired) || bool(selectedHead?.payment_method_required);
        const cashBankRequired = bool(option?.dataset.cashBankRequired) || paymentRequired || isCashBankSettlement();

        return {
            partyMode,
            partyRequired: partyMode === 'Required',
            partyOptional: partyMode === 'Optional',
            partyTypeId,
            partyTypeName,
            paymentRequired,
            cashBankRequired,
            source: option?.dataset.requirementSource || 'transaction_head',
        };
    }

    function isCashBankSettlement() {
        const value = settlementText();
        return value.includes('CASH') || value.includes('BANK') || value.includes('ADVANCE_PAID') || value.includes('ADVANCE PAID') || value.includes('ADVANCE_RECEIVED') || value.includes('ADVANCE RECEIVED');
    }

    function selectedHeadRequiresCashBank() {
        return selectedRequirement().cashBankRequired;
    }

    function cashBankDirectionLabel() {
        const selectedHead = headById(head.value);
        const value = `${selectedHead?.nature || ''} ${selectedHead?.category || ''} ${selectedHead?.name || ''} ${settlementText()}`.toUpperCase();
        if (value.includes('RECEIPT') || value.includes('RECEIVED') || value.includes('COLLECTION') || value.includes('INCOME') || value.includes('CAPITAL') || value.includes('SALES') || value.includes('ADVANCE_RECEIVED') || value.includes('ADVANCE RECEIVED')) return 'received in';
        return 'paid from';
    }

    function updateSettlementHint() {
        settlementHint.textContent = selectedHeadRequiresCashBank()
            ? `Select the Cash/Bank account where money will be ${cashBankDirectionLabel()}. The accounting rule still decides Debit/Credit.`
            : 'Only settlement types mapped with the selected Transaction Head are shown. Cash/Bank may stay blank for due, opening, or journal transactions.';
    }

    function resetPreview(message = 'Select transaction information to generate preview.') {
        lastPreviewOk = false;
        ledgerRows.innerHTML = `<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:22px">${message}</td></tr>`;
        mappingStatus.className = 'badge badge-warning';
        mappingStatus.textContent = 'Waiting';
        validationBox.className = 'validation warn';
        validationBox.textContent = message;
        voucherNo.textContent = '—';
        voucherTypeSummary.textContent = '—';
        nature.textContent = '—';
        partyEffect.textContent = '—';
        cashEffect.textContent = '—';
    }

    function renderOptions(select, rows, placeholder, labelCallback, extraCallback = null) {
        const previousValue = select.value;
        select.innerHTML = '';
        select.appendChild(new Option(placeholder, ''));
        rows.forEach((row) => {
            const option = new Option(labelCallback(row), row.id);
            if (extraCallback) extraCallback(option, row);
            select.appendChild(option);
        });
        if (rows.some((row) => String(row.id) === String(previousValue))) select.value = previousValue;
    }

    function setWrapVisibility(wrap, visible) {
        wrap?.classList.toggle('tx-hidden', !visible);
    }

    function hideDynamicFields(clearValues = false) {
        Object.values(dynamicWraps).forEach((wrap) => setWrapVisibility(wrap, false));
        settlement.required = false;
        party.required = false;
        cashBank.required = false;
        reference.required = false;
        if (partyRequiredElement()) partyRequiredElement().style.display = 'none';
        referenceRequired.style.display = 'none';
        if (clearValues) {
            party.value = '';
            settlement.value = '';
            cashBank.value = '';
            reference.value = '';
            notes.value = '';
            if (attachment) attachment.value = '';
        }
    }

    function referenceVisibleFor(selectedHead) {
        if (!selectedHead) return false;
        if (selectedHead.requires_reference) return true;
        return ['Sales', 'Purchase', 'Banking', 'Expense', 'Income', 'Asset', 'Loan', 'Employee'].includes(selectedHead.category);
    }

    function attachmentVisibleFor(selectedHead) {
        if (!selectedHead) return false;
        return !['Opening', 'Adjustment'].includes(selectedHead.category);
    }

    function updatePartyLabel(labelText, required) {
        partyLabel.textContent = `Select ${labelText} `;
        const star = document.createElement('span');
        star.className = 'required';
        star.id = 'partyRequired';
        star.textContent = '*';
        star.style.display = required ? '' : 'none';
        partyLabel.appendChild(star);
    }

    function updateDynamicFieldVisibility() {
        const selectedHead = headById(head.value);
        if (!selectedHead) {
            hideDynamicFields(false);
            updateSettlementHint();
            return;
        }

        const requirement = selectedRequirement();
        const showParty = requirement.partyMode !== 'No';
        const partyMustBeSelected = requirement.partyRequired && !hasDefaultNotApplicableParty();
        const showCashBank = selectedHeadRequiresCashBank();
        const showReference = referenceVisibleFor(selectedHead);
        const showAttachment = attachmentVisibleFor(selectedHead);

        setWrapVisibility(dynamicWraps.payment, true);
        settlement.required = true;
        setWrapVisibility(dynamicWraps.partyType, showParty);
        setWrapVisibility(dynamicWraps.party, showParty);
        setWrapVisibility(dynamicWraps.cashBank, showCashBank);
        setWrapVisibility(dynamicWraps.reference, showReference);
        setWrapVisibility(dynamicWraps.notes, true);
        setWrapVisibility(dynamicWraps.attachment, showAttachment);

        if (!showParty) party.value = '';
        if (!showCashBank) cashBank.value = '';
        if (!showReference) reference.value = '';

        const partyLabelText = requirement.partyTypeName || (selectedHead.category === 'Employee' ? 'Employee' : 'Party / Person');
        updatePartyLabel(partyLabelText, partyMustBeSelected);
        party.required = partyMustBeSelected;
        dynamicWraps.party?.classList.toggle('soft-required', partyMustBeSelected);

        cashBank.required = showCashBank && cashBanks.length > 0;
        dynamicWraps.cashBank?.classList.toggle('soft-required', cashBank.required);

        reference.required = Boolean(selectedHead.requires_reference);
        referenceRequired.style.display = reference.required ? '' : 'none';
        referenceHint.textContent = reference.required ? 'Reference is required for this Transaction Head.' : 'Optional reference for invoice, bill, cheque, bank slip, or internal memo.';
        updateSettlementHint();
    }

    async function loadTransactionHeads() {
        const useRows = (rows) => {
            heads = rows.map(normaliseHead);
            renderHeadsForSelectedCategory();
        };

        useRows(fallbackHeads);

        try {
            const apiHeads = await getRows(form.dataset.headsUrl);
            if (apiHeads.length > 0) useRows(apiHeads);
        } catch (error) {
            console.warn('Transaction Heads API fallback used:', error);
        }
    }

    function headSearchTarget(item) {
        return `${item.head_code || ''} ${item.name || ''} ${item.display_name || ''}`.toLowerCase();
    }

    function filteredHeadsForSelectedCategory() {
        const category = transactionCategory.value;
        const search = headSearch.value.trim().toLowerCase();

        if (!category) return [];

        return heads.filter((item) => {
            if (item.category !== category) return false;
            if (!search) return true;
            return headSearchTarget(item).includes(search);
        });
    }

    function setHeadDisplay(label) {
        headSelectedText.textContent = label;
    }

    function openHeadSuggestions(focusSearch = true) {
        if (headToggle.disabled || headSearch.disabled) return;
        headSuggestionPanel.classList.remove('tx-hidden');
        headToggle.classList.add('is-open');
        headToggle.setAttribute('aria-expanded', 'true');
        headSearch.setAttribute('aria-expanded', 'true');
        renderHeadSuggestionList(filteredHeadsForSelectedCategory(), true);
        if (focusSearch) setTimeout(() => headSearch.focus(), 0);
    }

    function hideHeadSuggestions() {
        headSuggestionPanel.classList.add('tx-hidden');
        headToggle.classList.remove('is-open');
        headToggle.setAttribute('aria-expanded', 'false');
        headSearch.setAttribute('aria-expanded', 'false');
    }

    function visibleSuggestionButtons() {
        return Array.from(headSuggestionList.querySelectorAll('.tx-head-suggestion'));
    }

    function setActiveSuggestion(index) {
        const buttons = visibleSuggestionButtons();
        buttons.forEach((button, buttonIndex) => button.classList.toggle('is-active', buttonIndex === index));
        if (buttons[index]) buttons[index].scrollIntoView({ block: 'nearest' });
    }

    function renderHeadSuggestionList(rows, shouldOpen = false) {
        headSuggestionList.innerHTML = '';

        if (!shouldOpen || headSearch.disabled) {
            hideHeadSuggestions();
            return;
        }

        if (rows.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'tx-head-no-result';
            empty.textContent = transactionCategory.value
                ? 'No transaction head found under this category.'
                : 'Select transaction category first.';
            headSuggestionList.appendChild(empty);
            headSuggestionPanel.classList.remove('tx-hidden');
            headToggle.classList.add('is-open');
            headToggle.setAttribute('aria-expanded', 'true');
            headSearch.setAttribute('aria-expanded', 'true');
            return;
        }

        rows.slice(0, 50).forEach((row) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'tx-head-suggestion';
            if (String(head.value) === String(row.id)) button.classList.add('is-selected');
            button.dataset.headId = row.id;
            button.setAttribute('role', 'option');

            const name = document.createElement('span');
            name.textContent = row.display_name || row.name;
            button.appendChild(name);

            if (row.head_code) {
                const code = document.createElement('span');
                code.className = 'tx-head-suggestion-code';
                code.textContent = row.head_code;
                button.appendChild(code);
            }

            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => selectTransactionHead(row));
            headSuggestionList.appendChild(button);
        });

        headSuggestionPanel.classList.remove('tx-hidden');
        headToggle.classList.add('is-open');
        headToggle.setAttribute('aria-expanded', 'true');
        headSearch.setAttribute('aria-expanded', 'true');
    }

    function selectTransactionHead(row) {
        head.value = String(row.id);
        setHeadDisplay(row.display_name || [row.head_code, row.name].filter(Boolean).join(' - '));
        headSearch.value = '';
        hideHeadSuggestions();
        headHint.textContent = `${row.display_name || row.name} selected under ${row.category}.`;
        refreshForSelectedHead();
    }

    function renderHeadsForSelectedCategory(showSuggestions = false) {
        const category = transactionCategory.value;
        head.innerHTML = '';

        if (!category) {
            head.disabled = true;
            headToggle.disabled = true;
            headSearch.disabled = true;
            headSearch.value = '';
            head.appendChild(new Option('Select Transaction Category first', ''));
            setHeadDisplay('Select transaction category first');
            hideHeadSuggestions();
            headHint.textContent = 'Only transaction heads mapped under the selected category will be shown.';
            return;
        }

        const rows = filteredHeadsForSelectedCategory();

        head.disabled = false;
        headToggle.disabled = false;
        headSearch.disabled = false;
        renderOptions(head, rows, rows.length ? 'Select Transaction Head' : 'No Transaction Head found under this category', (item) => item.display_name || item.name);
        if (!head.value) setHeadDisplay(rows.length ? 'Select transaction head' : 'No Transaction Head found');
        renderHeadSuggestionList(rows, showSuggestions);
        headHint.textContent = rows.length
            ? `${rows.length} Transaction Head${rows.length === 1 ? '' : 's'} found under ${category}. Click the field and type to filter.`
            : `No active Transaction Head is mapped under ${category}. Add or update it from Transaction Head Setup.`;
    }

    function setCategory(category) {
        transactionCategory.value = category;
        headSearch.value = '';
        head.value = '';
        screen.value = '';
        setHeadDisplay('Select transaction head');
        categorySelector.querySelectorAll('.tx-category-option').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.category === category);
        });
        const definition = categoryDefinitions.find((item) => item.name === category);
        categoryHint.textContent = definition ? `Example use: ${definition.example}` : 'Select the high-level transaction group.';
        guidanceText.textContent = `${category} selected. Now choose a searchable Transaction Head mapped under this category.`;
        renderHeadsForSelectedCategory(true);
        openHeadSuggestions(true);
        hideDynamicFields(true);
        resetPreview('Select Transaction Head to continue.');
    }

    async function loadCashBanks() {
        const renderCashBanks = () => {
            renderOptions(cashBank, cashBanks, 'Select cash/bank account', (item) => item.name, (option, item) => {
                option.dataset.type = item.type || '';
                option.dataset.ledger = item.ledger || '';
            });
        };
        cashBanks = fallbackCashBanks.map(normaliseCashBank);
        renderCashBanks();
        try {
            const apiRows = (await getRows(form.dataset.cashBankUrl)).map(normaliseCashBank);
            if (apiRows.length > 0) { cashBanks = apiRows; renderCashBanks(); }
        } catch (error) {
            console.warn('Cash/Bank API fallback used:', error);
        }
    }

    function renderDefaultNotApplicableParty() {
        party.innerHTML = '';
        const option = new Option('Default / Not Applicable', '');
        option.dataset.defaultNotApplicable = '1';
        party.appendChild(option);
        party.value = '';
        party.disabled = false;
    }

    function hasDefaultNotApplicableParty() {
        return party.options.length === 1 && party.options[0]?.dataset.defaultNotApplicable === '1';
    }

    function renderPartyOptions(rows, previousValue = '') {
        if (rows.length === 0) { renderDefaultNotApplicableParty(); return; }
        renderOptions(party, rows, 'Select saved Party / Person', (item) => item.display_name || [item.party_code, item.party_name].filter(Boolean).join(' - '), (option, item) => {
            option.dataset.partyType = item.party_type_id || '';
            option.dataset.partyTypeName = item.party_type_name || '';
            option.dataset.linkedLedgerAccountId = item.linked_ledger_account_id || '';
        });
        if (Array.from(party.options).some((option) => option.value && option.value === previousValue)) party.value = previousValue;
        party.disabled = false;
    }

    async function loadParties() {
        const selectedHead = headById(head.value);
        const requirement = selectedRequirement();
        const partyTypeId = requirement.partyTypeId || '';
        const previousValue = party.value;
        party.disabled = true;
        party.innerHTML = '<option value="">Loading Party / Person...</option>';
        if (!selectedHead || requirement.partyMode === 'No') { renderDefaultNotApplicableParty(); return; }
        try {
            const rows = await getRows(endpoint(form.dataset.partiesUrl, { party_type_id: partyTypeId }));
            renderPartyOptions(rows, previousValue);
        } catch (error) {
            const rows = partyTypeId ? fallbackParties.filter((item) => String(item.party_type_id) === String(partyTypeId)) : fallbackParties;
            renderPartyOptions(rows, previousValue);
        }
    }

    async function loadSettlementOptions() {
        const selectedHead = headById(head.value);
        const previousValue = settlement.value;
        settlement.disabled = true;
        settlement.innerHTML = '<option value="">Loading payment methods...</option>';
        if (!selectedHead) { settlement.innerHTML = '<option value="">Select Transaction Head first</option>'; settlement.required = false; settlement.disabled = false; resetPreview(); return; }
        let rows = [];
        try {
            rows = await getRows(endpoint(form.dataset.settlementsUrl, { transaction_head_id: selectedHead.id, mapped_only: 1 }));
        } catch (error) {
            rows = selectedHead.settlements || [];
        }
        renderOptions(settlement, rows, rows.length ? 'Select payment / settlement method' : 'No mapped payment methods found', (item) => item.display_name || item.name, (option, item) => {
            option.dataset.code = item.code || '';
            option.dataset.partyMode = normalisePartyMode(item.party_required_mode || 'No');
            option.dataset.partyRequired = bool(item.party_required) ? '1' : '0';
            option.dataset.partyOptional = bool(item.party_optional) ? '1' : '0';
            option.dataset.partyTypeId = item.party_type_id || '';
            option.dataset.partyTypeName = item.party_type_name || '';
            option.dataset.paymentRequired = bool(item.payment_method_required) ? '1' : '0';
            option.dataset.cashBankRequired = bool(item.cash_bank_required) ? '1' : '0';
            option.dataset.requirementSource = item.requirement_source || '';
        });
        if (rows.some((row) => String(row.id) === String(previousValue))) settlement.value = previousValue;
        else if (rows.length === 1) settlement.value = rows[0].id;
        else settlement.value = '';
        settlement.disabled = false;
    }

    async function refreshForSelectedHead() {
        const selectedHead = headById(head.value);
        party.required = false;
        if (partyRequiredElement()) partyRequiredElement().style.display = 'none';

        if (!selectedHead) {
            screen.value = '';
            guidanceText.textContent = transactionCategory.value ? 'Select a Transaction Head to continue.' : 'Select a Transaction Category to load matching Transaction Heads only.';
            hideDynamicFields(true);
            resetPreview('Select Transaction Head to continue.');
            return;
        }

        screen.value = `${selectedHead.transaction_screen || 'Transaction Entry'} / ${selectedHead.category || selectedHead.nature || 'General'}`;
        guidanceText.textContent = selectedHead.help_text || 'Enter what happened. Accounting posting rules will be handled from setup.';

        await loadSettlementOptions();
        await loadParties();

        const requirement = selectedRequirement();
        const partyLabelText = requirement.partyTypeName || requirement.partyMode || 'Not required';
        partyType.value = requirement.partyMode === 'No' ? 'Not required by selected rule' : `${partyLabelText} (${requirement.partyMode})`;
        updateDynamicFieldVisibility();
        if (!isBooting) schedulePreview();
    }

    function formReadyForPreview() {
        if (!date.value || !transactionCategory.value || !head.value || !settlement.value || Number(amount.value || 0) <= 0) return false;
        const selectedHead = headById(head.value);
        if (selectedRequirement().partyRequired && !party.value && !hasDefaultNotApplicableParty()) return false;
        if (selectedHead?.requires_reference && !reference.value.trim()) return false;
        if (selectedHeadRequiresCashBank() && cashBanks.length > 0 && !cashBank.value) return false;
        return true;
    }

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(() => preview(), 250);
    }

    async function preview() {
        updateDynamicFieldVisibility();
        if (!formReadyForPreview()) { resetPreview('Complete required fields to generate ledger preview.'); return; }
        const formData = new FormData(form);
        formData.set('status', statusInput.value || 'Posted');
        try {
            const response = await fetch(form.dataset.previewUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }, body: formData, credentials: 'same-origin' });
            const result = await parseJsonResponse(response);
            if (!response.ok || !result.success) throw result;
            renderPreview(result.data);
        } catch (error) {
            const message = error?.errors ? Object.values(error.errors)[0][0] : error?.message || 'Posting blocked. Please configure Accounting Rules Setup first.';
            lastPreviewOk = false;
            ledgerRows.innerHTML = `<tr><td colspan="3" style="text-align:center;color:#b42318;padding:22px">${message}</td></tr>`;
            mappingStatus.className = 'badge badge-danger';
            mappingStatus.textContent = 'Mapping Missing';
            validationBox.className = 'validation error';
            validationBox.textContent = message;
            voucherNo.textContent = '—';
            nature.textContent = 'Unknown';
            partyEffect.textContent = 'Unknown';
            cashEffect.textContent = 'Unknown';
        }
    }

    function normalBalanceFromType(accountType) {
        const type = text(accountType, '').toLowerCase();
        if (['asset', 'expense'].includes(type)) return 'Debit';
        if (['liability', 'income', 'equity', 'owner’s equity', "owner's equity"].includes(type)) return 'Credit';
        return 'Debit';
    }

    function postingEffectFromEntry(normalBalance, side) {
        return normalBalance === side ? 'Increase' : 'Decrease';
    }

    function renderPreview(data) {
        lastPreviewOk = true;
        const rows = data.entries.map((entry) => {
            const debitAmount = Number(entry.debit || 0);
            const creditAmount = Number(entry.credit || 0);
            const side = debitAmount > 0 ? 'Debit' : 'Credit';
            const sideAmount = debitAmount > 0 ? debitAmount : creditAmount;
            const accountCode = text(entry.account_code, '');
            const accountName = text(entry.account_name);
            const accountType = text(entry.account_type, 'Account');
            const normalBalance = text(entry.normal_balance, normalBalanceFromType(accountType));
            const sourceLabel = text(entry.source_label, 'Accounting Rule');
            const postingEffect = text(entry.posting_effect, postingEffectFromEntry(normalBalance, side));
            const effectClass = postingEffect === 'Increase' ? 'ledger-effect-increase' : 'ledger-effect-decrease';
            const plainMeaning = `${side} BDT ${money(sideAmount)} → ${postingEffect} ${accountType}`;
            return `<tr><td><div class="ledger-account-name">${accountCode ? `${accountCode} - ` : ''}${accountName}</div><div class="ledger-account-meta"><span>${accountType}</span><span>Normal: ${normalBalance}</span><span class="${effectClass}">${postingEffect}</span></div><div class="ledger-account-rule">${plainMeaning}<br><small>Source: ${sourceLabel}</small></div></td><td>${money(entry.debit)}</td><td>${money(entry.credit)}</td></tr>`;
        }).join('');
        ledgerRows.innerHTML = `${rows}<tr class="total-row"><td>Total</td><td>${money(data.total_debit)}</td><td>${money(data.total_credit)}</td></tr>`;
        mappingStatus.className = 'badge badge-success';
        mappingStatus.textContent = 'Mapping Found';
        validationBox.className = data.balanced ? 'validation' : 'validation error';
        validationBox.textContent = data.balanced ? '✓ Accounting check passed. Debit equals Credit. Account effects are shown using each ledger’s normal balance.' : 'Debit and credit are not balanced. Posting blocked.';
        voucherNo.textContent = data.voucher_number;
        voucherTypeSummary.textContent = data.voucher_type || 'Auto Detected';
        nature.textContent = data.nature;
        partyEffect.textContent = data.party_ledger_effect;
        cashEffect.textContent = data.cash_bank_effect;
    }

    async function submitTransaction(status) {
        statusInput.value = status;
        if (status === 'Posted' && !canPostTransaction) { showToast('Your role can save draft transactions only. Final posting is locked.'); return; }
        if (status === 'Draft' && !canSaveDraftTransaction) { showToast('Your role is not allowed to save draft transactions.'); return; }
        summaryStatus.textContent = status;
        summaryStatus.className = status === 'Posted' ? 'badge badge-success' : 'badge badge-warning';
        await preview();
        if (!lastPreviewOk) { showToast('Cannot save. Ledger preview is invalid.'); return; }
        const formData = new FormData(form);
        formData.set('status', status);
        try {
            const response = await fetch(form.dataset.storeUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }, body: formData, credentials: 'same-origin' });
            const result = await parseJsonResponse(response);
            if (!response.ok) { showToast(result.errors ? Object.values(result.errors)[0][0] : result.message || 'Please check validation errors.'); return; }
            showToast(result.message || 'Transaction saved.');
            setTimeout(() => { window.location.href = result.redirect || window.location.href; }, 700);
        } catch (error) {
            console.error(error);
            showToast(error?.message || 'Transaction API error. Please check backend code.');
        }
    }

    async function clearForm() {
        form.reset();
        statusInput.value = canPostTransaction ? 'Posted' : 'Draft';
        summaryStatus.textContent = statusInput.value;
        summaryStatus.className = statusInput.value === 'Posted' ? 'badge badge-success' : 'badge badge-warning';
        date.value = '<?php echo e($defaultVoucherDate ?? now()->toDateString()); ?>';
        amount.value = '10000';
        transactionCategory.value = '';
        categorySelector.querySelectorAll('.tx-category-option').forEach((button) => button.classList.remove('is-active'));
        categoryHint.textContent = 'This is the high-level grouping, for example: Sales, Purchase, Receipt, Payment, Banking, Expense, Income, Owner / Equity, Asset, Loan, Employee, Opening, and Adjustment.';
        guidanceText.textContent = 'Select a Transaction Category to load matching Transaction Heads only.';
        renderHeadsForSelectedCategory();
        hideDynamicFields(true);
        resetPreview('Select transaction category to begin.');
        showToast('Form cleared.');
    }

    categorySelector.querySelectorAll('.tx-category-option').forEach((button) => {
        button.addEventListener('click', () => setCategory(button.dataset.category));
    });

    headToggle.addEventListener('click', () => {
        if (headToggle.disabled) return;
        if (headSuggestionPanel.classList.contains('tx-hidden')) {
            headSearch.value = '';
            openHeadSuggestions(true);
        } else {
            hideHeadSuggestions();
        }
    });

    headSearch.addEventListener('focus', () => {
        if (!transactionCategory.value) return;
        renderHeadSuggestionList(filteredHeadsForSelectedCategory(), true);
    });

    headSearch.addEventListener('input', () => {
        if (head.value) {
            head.value = '';
            screen.value = '';
            setHeadDisplay('Select transaction head');
            hideDynamicFields(true);
            resetPreview('Choose a Transaction Head from the suggestion list.');
        }

        renderHeadSuggestionList(filteredHeadsForSelectedCategory(), true);
    });

    headSearch.addEventListener('keydown', (event) => {
        const buttons = visibleSuggestionButtons();

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (headSuggestionPanel.classList.contains('tx-hidden')) openHeadSuggestions(false);
            const updatedButtons = visibleSuggestionButtons();
            if (updatedButtons.length === 0) return;
            const currentIndex = updatedButtons.findIndex((button) => button.classList.contains('is-active'));
            setActiveSuggestion(currentIndex >= updatedButtons.length - 1 ? 0 : currentIndex + 1);
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            const updatedButtons = visibleSuggestionButtons();
            if (updatedButtons.length === 0) return;
            const currentIndex = updatedButtons.findIndex((button) => button.classList.contains('is-active'));
            setActiveSuggestion(currentIndex <= 0 ? updatedButtons.length - 1 : currentIndex - 1);
        }

        if (event.key === 'Enter') {
            const active = visibleSuggestionButtons().find((button) => button.classList.contains('is-active')) || visibleSuggestionButtons()[0];
            if (active) {
                event.preventDefault();
                const selected = heads.find((item) => String(item.id) === String(active.dataset.headId));
                if (selected) selectTransactionHead(selected);
            }
        }

        if (event.key === 'Escape') {
            hideHeadSuggestions();
            headToggle.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('#headCombobox')) hideHeadSuggestions();
    });

    [date, party, amount, settlement, cashBank, reference, notes].forEach((input) => {
        input.addEventListener('input', schedulePreview);
        input.addEventListener('change', schedulePreview);
    });
    head.addEventListener('change', () => refreshForSelectedHead());
    settlement.addEventListener('change', async () => { await loadParties(); updateDynamicFieldVisibility(); schedulePreview(); });
    form.addEventListener('submit', (event) => { event.preventDefault(); submitTransaction('Posted'); });
    document.getElementById('draftBtn')?.addEventListener('click', () => submitTransaction('Draft'));
    document.getElementById('clearBtn')?.addEventListener('click', clearForm);
    document.getElementById('newBtn')?.addEventListener('click', clearForm);
    document.querySelectorAll('[data-scroll-target]').forEach((button) => button.addEventListener('click', () => document.querySelector(button.dataset.scrollTarget)?.scrollIntoView({ behavior: 'smooth' })));

    async function boot() {
        statusInput.value = canPostTransaction ? statusInput.value : 'Draft';
        summaryStatus.textContent = statusInput.value;
        summaryStatus.className = statusInput.value === 'Posted' ? 'badge badge-success' : 'badge badge-warning';
        resetPreview('Select transaction category to begin.');
        hideDynamicFields(false);
        await Promise.all([loadTransactionHeads(), loadCashBanks()]);
        renderHeadsForSelectedCategory();
        isBooting = false;
    }

    boot();
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/transactions/create.blade.php ENDPATH**/ ?>