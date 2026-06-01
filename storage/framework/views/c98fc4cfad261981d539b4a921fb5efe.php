<?php $__env->startSection('title', 'Transaction Head Setup | HisebGhor'); ?>


<?php $__env->startPush('styles'); ?>
<style>
    .transaction-head-page .transaction-head-redesign-grid{
        grid-template-columns:1fr;
    }
    .transaction-head-page .prototype-side-stack{
        max-width:none;
    }
    .transaction-head-page .tx-head-filter-grid{
        display:grid;
        grid-template-columns:minmax(260px,2fr) repeat(3,minmax(160px,1fr));
        gap:12px;
        align-items:end;
        margin-bottom:16px;
    }
    .transaction-head-page .tx-head-filter-grid label{
        display:block;
        margin:0 0 6px;
        color:#344054;
        font-size:12px;
        font-weight:850;
    }
    .transaction-head-page .tx-head-filter-grid input,
    .transaction-head-page .tx-head-filter-grid select{
        width:100%;
        min-height:46px;
        border-radius:14px;
    }
    .transaction-head-page .tx-head-list-summary{
        color:var(--muted);
        font-size:12px;
        font-weight:750;
        margin:-4px 0 12px;
    }
    .transaction-head-page .tx-head-table-wrap{
        max-height:620px;
        overflow:auto;
        scrollbar-gutter:stable both-edges;
    }
    .transaction-head-page .tx-head-table-wrap thead th{
        position:sticky;
        top:0;
        z-index:2;
    }
    .transaction-head-page .tx-head-table-wrap::-webkit-scrollbar{
        width:12px;
        height:12px;
    }
    .transaction-head-page .tx-head-table-wrap::-webkit-scrollbar-track{
        background:#f2f4f7;
        border-radius:999px;
    }
    .transaction-head-page .tx-head-table-wrap::-webkit-scrollbar-thumb{
        background:#cbd5e1;
        border-radius:999px;
        border:3px solid #f2f4f7;
    }
    @media(max-width:980px){
        .transaction-head-page .tx-head-filter-grid{
            grid-template-columns:1fr 1fr;
        }
    }
    @media(max-width:640px){
        .transaction-head-page .tx-head-filter-grid{
            grid-template-columns:1fr;
        }
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $activeHeads = $transactionHeads->where('status', 'Active')->count();
    $systemHeads = $transactionHeads->where('is_system_default', true)->count();
    $partyHeads = $transactionHeads->filter(fn ($head) => ($head->party_required_mode ?: ($head->requires_party ? 'Required' : 'No')) !== 'No')->count();
    $paymentHeads = $transactionHeads->where('payment_method_required', true)->count();
    $salesHeads = $transactionHeads->filter(fn ($head) => str_contains(strtolower((string) ($head->category ?: $head->nature ?: $head->name)), 'sales'))->count();
    $expenseHeads = $transactionHeads->filter(fn ($head) => str_contains(strtolower((string) ($head->category ?: $head->nature ?: $head->name)), 'expense'))->count();
    $screenCount = $transactionHeads->pluck('transaction_screen')->filter()->unique()->count();
    $categoryFilterOptions = collect(\App\Models\TransactionHead::transactionCategories())
        ->merge($transactionHeads->pluck('category')->filter())
        ->merge($transactionHeads->pluck('nature')->filter())
        ->unique()
        ->values();
?>

<div class="prototype-page transaction-head-page">
    <div class="prototype-hero">
        <div>
            <span class="page-label">Transaction Head Setup</span>
            <h2>Transaction Head Setup</h2>
            <p>Create business activities, not manual journal rules. Accounting rules will convert these heads into debit and credit postings.</p>
        </div>

        <div class="prototype-actions">
            <button class="btn-outline" type="button" id="addHeadBtn">+ Add New Transaction Head</button>
            <button class="btn-ghost" type="button" data-scroll-target="#headListCard">View All Heads</button>
            <button class="btn-ghost" type="button" data-scroll-target="#mappingPreview">Rule Mapping Preview</button>
        </div>
    </div>

    <?php echo $__env->make('partials.setup-progress', ['current' => 5], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="prototype-stats six">
        <div class="card prototype-stat"><span>Total Transaction Heads</span><strong><?php echo e($transactionHeads->count()); ?></strong><small>Business activities</small></div>
        <div class="card prototype-stat"><span>Payment Required</span><strong><?php echo e($paymentHeads); ?></strong><small>Needs cash/bank input</small></div>
        <div class="card prototype-stat"><span>Party Required</span><strong><?php echo e($partyHeads); ?></strong><small>Customer / supplier / employee</small></div>
        <div class="card prototype-stat"><span>Sales Heads</span><strong><?php echo e($salesHeads); ?></strong><small>Sales / income activity</small></div>
        <div class="card prototype-stat"><span>Expense Heads</span><strong><?php echo e($expenseHeads); ?></strong><small>Payment / expense activity</small></div>
        <div class="card prototype-stat"><span>Active Heads</span><strong><?php echo e($activeHeads); ?></strong><small>Visible in transaction entry</small></div>
    </div>

    <div class="prototype-grid transaction-head-redesign-grid">
        <div class="card prototype-card">
            <div class="prototype-card-header">
                <div>
                    <h3>Guided Transaction Head Setup</h3>
                    <p>Create business activities, not manual journal rules.</p>
                </div>
                <span class="badge badge-primary">SRS Setup</span>
            </div>

            <div class="prototype-card-body">
                <form
                    id="headForm"
                    class="prototype-form"
                    data-frontend-form
                    data-action="<?php echo e(route('api.transaction-heads.store')); ?>"
                    data-store-url="<?php echo e(route('api.transaction-heads.store')); ?>"
                    data-success="Transaction head saved successfully."
                >
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="_method" id="headFormMethod" value="POST">
                    <input type="hidden" name="nature" id="headNature" value="Payment">
                    <input type="hidden" name="requires_party" id="requiresParty" value="0">
                    <input type="hidden" name="requires_reference" id="requiresReference" value="0">
                    <div id="settlementHiddenInputs"></div>

                    <div class="prototype-form-grid two">
                        <div class="prototype-field">
                            <label for="headCode">Transaction Head Code</label>
                            <input id="headCode" name="head_code" placeholder="Auto: TH-001">
                            <div class="hint">Leave blank to auto-generate.</div>
                        </div>

                        <div class="prototype-field">
                            <label for="headName">What business activity are you creating? <span class="required">*</span></label>
                            <input id="headName" name="name" placeholder="Example: Customer Collection" required>
                        </div>

                        <div class="prototype-field">
                            <label for="headCategory">Which category does it belong to? <span class="required">*</span></label>
                            <select id="headCategory" name="category" required>
                                <option value="">Select category</option>
                                <?php $__currentLoopData = \App\Models\TransactionHead::transactionCategories(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($categoryOption); ?>"><?php echo e($categoryOption); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <div class="hint">Used by the accounting engine and transaction screen filter.</div>
                        </div>

                        <div class="prototype-field">
                            <label for="transactionScreen">Which transaction screen will use this?</label>
                            <input id="transactionScreen" name="transaction_screen" placeholder="Daily Transaction Entry">
                            <div class="hint">Example: Sales Entry, Payment Entry, Transaction Entry.</div>
                        </div>

                        <div class="prototype-field">
                            <label for="defaultLedger">Which ledger is mainly affected?</label>
                            <select id="defaultLedger" name="default_primary_ledger_id">
                                <option value="">Rule-selected / user-selected</option>
                                <?php $__currentLoopData = $postingLedgers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ledger): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($ledger->id); ?>"><?php echo e($ledger->display_name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="defaultMovement">Does this transaction increase or decrease the selected ledger? <span class="required">*</span></label>
                            <select id="defaultMovement" name="default_movement" required>
                                <option value="Increase">Increase</option>
                                <option value="Decrease">Decrease</option>
                                <option value="No Movement">No Movement</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="paymentRequired">Does the user need to select cash or bank? <span class="required">*</span></label>
                            <select id="paymentRequired" name="payment_method_required" required>
                                <option value="1">Yes</option>
                                <option value="0" selected>No</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="partyRequiredMode">Does the user need to select a party? <span class="required">*</span></label>
                            <select id="partyRequiredMode" name="party_required_mode" required>
                                <option value="No">No</option>
                                <option value="Optional">Optional</option>
                                <option value="Required">Required</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="partyType">Party type</label>
                            <select id="partyType" name="default_party_type_id" data-dropdown="/api/dropdowns/party-types" data-placeholder="Select Party Type"></select>
                            <div class="hint">Restricts party list in transaction entry.</div>
                        </div>

                        <div class="prototype-field">
                            <label for="headStatus">Is Active?</label>
                            <select id="headStatus" name="status" required>
                                <option value="Active">Yes - Active</option>
                                <option value="Inactive">No - Inactive</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="systemDefault">Is System Default?</label>
                            <select id="systemDefault" name="is_system_default">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="userSelectable">Allow user selection in transaction entry?</label>
                            <select id="userSelectable" name="is_user_selectable">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>

                        <div class="prototype-field">
                            <label for="sortOrder">Sort order</label>
                            <input id="sortOrder" name="sort_order" type="number" min="0" step="1" placeholder="Auto">
                        </div>

                        <div class="prototype-field">
                            <label for="linkedRuleCode">Linked Accounting Rule Code</label>
                            <input id="linkedRuleCode" name="linked_accounting_rule_code" placeholder="Example: AR-001">
                        </div>

                        <div class="prototype-field full">
                            <label>Allowed Settlement Types <span class="required">*</span></label>
                            <div class="multi-select prototype-chip-box" id="settlementTypeChips" data-selected-count="0">
                                <?php $__currentLoopData = $settlementTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $settlementType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class="select-chip" tabindex="0" role="button" data-id="<?php echo e($settlementType->id); ?>" data-value="<?php echo e($settlementType->id); ?>" data-name="<?php echo e(e($settlementType->name)); ?>" data-code="<?php echo e(e($settlementType->code)); ?>">
                                        <?php echo e($settlementType->name); ?>

                                    </span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                            <div class="hint">Choose the payment/settlement modes that can be used with this head.</div>
                        </div>

                        <div class="prototype-field full">
                            <label for="description">Short explanation for users</label>
                            <textarea id="description" name="description" placeholder="Explain when users should select this transaction head."></textarea>
                        </div>

                        <div class="prototype-field full">
                            <label for="helpText">Help text for transaction entry</label>
                            <textarea id="helpText" name="help_text" placeholder="Guidance shown to the transaction entry user."></textarea>
                        </div>

                        <div class="prototype-field full">
                            <label for="developerNote">Notes for developer</label>
                            <textarea id="developerNote" name="developer_note" placeholder="Internal implementation note or rule dependency."></textarea>
                        </div>
                    </div>

                    <div class="prototype-form-actions">
                        <button type="button" class="btn-ghost" id="cancelHeadBtn">Clear</button>
                        <button type="submit" class="btn-primary">Save Transaction Head</button>
                    </div>
                </form>
            </div>
        </div>

        <aside class="prototype-side-stack">
            <div class="card prototype-preview-card" id="mappingPreview">
                <h3>Current Form Meaning</h3>
                <div class="prototype-preview-list">
                    <div><span>Category</span><strong id="previewCategory">—</strong></div>
                    <div><span>Nature</span><strong id="previewNature">—</strong></div>
                    <div><span>Party</span><strong id="previewParty">No</strong></div>
                    <div><span>Payment</span><strong id="previewPayment">No</strong></div>
                    <div><span>Selectable</span><strong id="previewSelectable">Yes</strong></div>
                </div>
            </div>
        </aside>
    </div>

    <section class="card prototype-card prototype-section" id="headListCard">
        <div class="prototype-card-header">
            <div>
                <h3>Transaction Head List</h3>
                <p>Search, filter, edit, and review available business activities.</p>
            </div>
            <span class="badge badge-neutral" id="resultCount">Showing <?php echo e($transactionHeads->count()); ?> of <?php echo e($transactionHeads->count()); ?> entries</span>
        </div>

        <div class="prototype-card-body">
            <div class="tx-head-filter-grid" aria-label="Transaction head filters">
                <div class="field search-field">
                    <label for="headFilterSearch">Search</label>
                    <input id="headFilterSearch" placeholder="Search transaction heads...">
                </div>
                <div>
                    <label for="headFilterCategory">Category</label>
                    <select id="headFilterCategory">
                        <option value="">All Categories</option>
                        <?php $__currentLoopData = $categoryFilterOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($categoryOption); ?>"><?php echo e($categoryOption); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label for="headFilterStatus">Status</label>
                    <select id="headFilterStatus">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label for="headLoadSize">Show records</label>
                    <select id="headLoadSize">
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                        <option value="150">150</option>
                        <option value="200">200</option>
                        <option value="all">All</option>
                    </select>
                </div>
            </div>
            <div class="tx-head-list-summary" id="headVisibleSummary">Showing records as you scroll.</div>

            <div class="table-wrap prototype-table-wrap always-scroll tx-head-table-wrap" id="headTableWrap">
                <table id="headTable" data-no-client-pagination="true">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Transaction Head</th>
                            <th>Category</th>
                            <th>Main Ledger / CoA Code</th>
                            <th>Movement</th>
                            <th>Payment Required</th>
                            <th>Party Required</th>
                            <th>Party Type</th>
                            <th>Transaction Screen</th>
                            <th>Status</th>
                            <th style="text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $transactionHeads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $head): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php
                                $partyMode = $head->party_required_mode ?: ($head->requires_party ? 'Required' : 'No');
                                $category = $head->category ?: $head->nature;
                                $settlementIds = $head->settlementTypes->pluck('id')->map(fn ($id) => (string) $id)->values();
                            ?>
                            <tr
                                data-head-row="true"
                                data-id="<?php echo e($head->id); ?>"
                                data-text="<?php echo e(e(trim(($head->head_code ? $head->head_code . ' ' : '') . $head->name . ' ' . $category . ' ' . ($head->transaction_screen ?: '')))); ?>"
                                data-head-code="<?php echo e(e($head->head_code)); ?>"
                                data-name="<?php echo e(e($head->name)); ?>"
                                data-nature="<?php echo e(e($head->nature)); ?>"
                                data-category="<?php echo e(e($category)); ?>"
                                data-default-primary-ledger-id="<?php echo e($head->default_primary_ledger_id); ?>"
                                data-default-movement="<?php echo e(e($head->default_movement ?: 'Increase')); ?>"
                                data-payment-method-required="<?php echo e($head->payment_method_required ? 1 : 0); ?>"
                                data-party-required-mode="<?php echo e(e($partyMode)); ?>"
                                data-default-party-type-id="<?php echo e($head->default_party_type_id); ?>"
                                data-requires-reference="<?php echo e($head->requires_reference ? 1 : 0); ?>"
                                data-transaction-screen="<?php echo e(e($head->transaction_screen)); ?>"
                                data-description="<?php echo e(e($head->description)); ?>"
                                data-help-text="<?php echo e(e($head->help_text)); ?>"
                                data-developer-note="<?php echo e(e($head->developer_note)); ?>"
                                data-is-system-default="<?php echo e($head->is_system_default ? 1 : 0); ?>"
                                data-is-user-selectable="<?php echo e($head->is_user_selectable ? 1 : 0); ?>"
                                data-sort-order="<?php echo e($head->sort_order); ?>"
                                data-linked-accounting-rule-code="<?php echo e(e($head->linked_accounting_rule_code)); ?>"
                                data-settlement-ids='<?php echo json_encode($settlementIds, 15, 512) ?>'
                                data-status="<?php echo e(e($head->status)); ?>"
                                data-update-url="<?php echo e(route('api.transaction-heads.update', $head)); ?>"
                            >
                                <td class="code"><?php echo e($head->head_code ?: '—'); ?></td>
                                <td class="strong"><?php echo e($head->name); ?></td>
                                <td><span class="badge badge-primary"><?php echo e($category); ?></span></td>
                                <td class="<?php echo e($head->defaultPrimaryLedger ? '' : 'muted'); ?>">
                                    <?php echo e($head->defaultPrimaryLedger?->display_name ?? 'Rule-selected'); ?>

                                </td>
                                <td><?php echo e($head->default_movement ?: 'Increase'); ?></td>
                                <td><?php echo e($head->payment_method_required ? 'Yes' : 'No'); ?></td>
                                <td><?php echo e($partyMode); ?></td>
                                <td><?php echo e($head->defaultPartyType?->name ?? '—'); ?></td>
                                <td><?php echo e($head->transaction_screen ?: 'Transaction Entry'); ?></td>
                                <td><span class="badge <?php echo e($head->status === 'Active' ? 'badge-success' : 'badge-neutral'); ?>"><?php echo e($head->status); ?></span></td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn edit-btn" type="button" title="Edit">✎</button>
                                        <form method="POST" data-delete-form action="<?php echo e(route('setup.transaction-heads.destroy', $head)); ?>" onsubmit="return confirm('Delete this transaction head?')">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr data-empty="true"><td colspan="11" class="muted" style="text-align:center;padding:24px">No transaction heads found.</td></tr>
                        <?php endif; ?>
                        <tr id="headFilterEmptyRow" data-filter-empty="true" style="display:none">
                            <td colspan="11" class="muted" style="text-align:center;padding:24px">No transaction heads match the current filter.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('headForm');
    if (!form) return;

    const methodInput = document.getElementById('headFormMethod');
    const natureInput = document.getElementById('headNature');
    const settlementHidden = document.getElementById('settlementHiddenInputs');
    const settlementBox = document.getElementById('settlementTypeChips');
    const addButton = document.getElementById('addHeadBtn');
    const cancelButton = document.getElementById('cancelHeadBtn');

    const fields = {
        head_code: form.querySelector('[name="head_code"]'),
        name: form.querySelector('[name="name"]'),
        category: form.querySelector('[name="category"]'),
        default_primary_ledger_id: form.querySelector('[name="default_primary_ledger_id"]'),
        default_movement: form.querySelector('[name="default_movement"]'),
        payment_method_required: form.querySelector('[name="payment_method_required"]'),
        party_required_mode: form.querySelector('[name="party_required_mode"]'),
        default_party_type_id: form.querySelector('[name="default_party_type_id"]'),
        transaction_screen: form.querySelector('[name="transaction_screen"]'),
        status: form.querySelector('[name="status"]'),
        is_system_default: form.querySelector('[name="is_system_default"]'),
        is_user_selectable: form.querySelector('[name="is_user_selectable"]'),
        sort_order: form.querySelector('[name="sort_order"]'),
        linked_accounting_rule_code: form.querySelector('[name="linked_accounting_rule_code"]'),
        description: form.querySelector('[name="description"]'),
        help_text: form.querySelector('[name="help_text"]'),
        developer_note: form.querySelector('[name="developer_note"]'),
    };

    const preview = {
        category: document.getElementById('previewCategory'),
        nature: document.getElementById('previewNature'),
        party: document.getElementById('previewParty'),
        payment: document.getElementById('previewPayment'),
        selectable: document.getElementById('previewSelectable'),
    };

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }
        alert(message);
    }

    function natureFromCategory(category) {
        switch (category) {
            case 'Sales':
            case 'Receipt':
            case 'Income':
                return 'Receipt';
            case 'Purchase':
                return 'Purchase';
            case 'Expense':
            case 'Payment':
            case 'Employee':
                return 'Payment';
            case 'Banking':
            case 'Opening':
            case 'Adjustment':
                return 'Adjustment';
            case 'Owner / Equity':
                return 'Equity';
            case 'Asset':
                return 'Asset';
            case 'Loan':
                return 'Loan';
            default:
                return 'Payment';
        }
    }

    function defaultRulesForCategory(category) {
        switch (category) {
            case 'Sales':
                return { movement: 'Increase', payment: '1', party: 'Optional', screen: 'Sales Entry', settlements: ['cash', 'bank', 'due'] };
            case 'Purchase':
                return { movement: 'Increase', payment: '1', party: 'Required', screen: 'Purchase Entry', settlements: ['cash', 'bank', 'due'] };
            case 'Receipt':
                return { movement: 'Decrease', payment: '1', party: 'Required', screen: 'Receipt Entry', settlements: ['cash', 'bank', 'advance'] };
            case 'Payment':
                return { movement: 'Decrease', payment: '1', party: 'Optional', screen: 'Payment Entry', settlements: ['cash', 'bank'] };
            case 'Banking':
                return { movement: 'No Movement', payment: '1', party: 'No', screen: 'Banking Entry', settlements: ['cash', 'bank', 'adjust'] };
            case 'Expense':
                return { movement: 'Increase', payment: '1', party: 'No', screen: 'Expense Entry', settlements: ['cash', 'bank', 'due'] };
            case 'Income':
                return { movement: 'Increase', payment: '1', party: 'Optional', screen: 'Income Entry', settlements: ['cash', 'bank', 'due'] };
            case 'Owner / Equity':
                return { movement: 'Increase', payment: '1', party: 'Optional', screen: 'Owner / Equity Entry', settlements: ['cash', 'bank'] };
            case 'Asset':
                return { movement: 'Increase', payment: '1', party: 'Optional', screen: 'Asset Entry', settlements: ['cash', 'bank', 'due'] };
            case 'Loan':
                return { movement: 'Increase', payment: '1', party: 'Optional', screen: 'Loan Entry', settlements: ['cash', 'bank'] };
            case 'Employee':
                return { movement: 'Decrease', payment: '1', party: 'Required', screen: 'Employee Entry', settlements: ['cash', 'bank', 'advance'] };
            case 'Opening':
                return { movement: 'Increase', payment: '0', party: 'Optional', screen: 'Opening Balance Entry', settlements: ['opening', 'adjust'] };
            case 'Adjustment':
                return { movement: 'No Movement', payment: '0', party: 'Optional', screen: 'Adjustment Entry', settlements: ['adjust'] };
            default:
                return { movement: 'Increase', payment: '0', party: 'No', screen: 'Transaction Entry', settlements: [] };
        }
    }

    function applyCategoryDefaults() {
        const category = fields.category.value || '';
        const rules = defaultRulesForCategory(category);
        fields.default_movement.value = rules.movement;
        fields.payment_method_required.value = rules.payment;
        fields.party_required_mode.value = rules.party;
        if (!fields.transaction_screen.value || fields.transaction_screen.dataset.autoFilled === '1') {
            fields.transaction_screen.value = rules.screen;
            fields.transaction_screen.dataset.autoFilled = '1';
        }
        if (rules.settlements.length > 0 && selectedSettlementIds().length === 0) {
            const selected = [];
            settlementBox.querySelectorAll('.select-chip').forEach((chip) => {
                const label = `${chip.dataset.code || ''} ${chip.dataset.name || chip.textContent || ''}`.toLowerCase();
                if (rules.settlements.some((keyword) => label.includes(keyword))) {
                    selected.push(chip.dataset.id || chip.dataset.value);
                }
            });
            if (selected.length > 0) setSettlementIds(selected);
        }
        syncDerivedFields();
    }

    function selectedSettlementIds() {
        return Array.from(settlementBox.querySelectorAll('.select-chip.selected'))
            .map((chip) => chip.dataset.id || chip.dataset.value)
            .filter(Boolean);
    }

    function syncSettlementHidden() {
        const ids = selectedSettlementIds();
        settlementHidden.innerHTML = '';
        ids.forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'settlement_type_ids[]';
            input.value = id;
            settlementHidden.appendChild(input);
        });
        settlementBox.dataset.selectedCount = String(ids.length);
    }

    function setSettlementIds(ids) {
        const selected = (Array.isArray(ids) ? ids : []).map(String);
        settlementBox.querySelectorAll('.select-chip').forEach((chip) => {
            chip.classList.toggle('selected', selected.includes(String(chip.dataset.id || chip.dataset.value)));
        });
        syncSettlementHidden();
    }

    function syncDerivedFields() {
        const category = fields.category.value || '';
        const nature = natureFromCategory(category);
        natureInput.value = nature;
        document.getElementById('requiresParty').value = fields.party_required_mode.value === 'No' ? '0' : '1';
        if (fields.default_party_type_id) fields.default_party_type_id.required = fields.party_required_mode.value === 'Required';
        preview.category.textContent = category || '—';
        preview.nature.textContent = nature;
        preview.party.textContent = fields.party_required_mode.value || 'No';
        preview.payment.textContent = fields.payment_method_required.value === '1' ? 'Yes' : 'No';
        preview.selectable.textContent = fields.is_user_selectable.value === '1' ? 'Yes' : 'No';
    }

    function setDropdownValue(select, value) {
        if (!select) return;
        select.dataset.selected = value || '';
        select.value = value || '';
        if (select.dataset.dropdown && window.AccountingUI?.loadSelect) {
            window.AccountingUI.loadSelect(select).then(() => {
                select.value = value || '';
            });
        }
    }

    function resetForm() {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';
        fields.payment_method_required.value = '0';
        fields.party_required_mode.value = 'No';
        fields.default_movement.value = 'Increase';
        fields.transaction_screen.dataset.autoFilled = '1';
        fields.status.value = 'Active';
        fields.is_system_default.value = '0';
        fields.is_user_selectable.value = '1';
        setDropdownValue(fields.default_party_type_id, '');
        setSettlementIds([]);
        syncDerivedFields();
        fields.name.focus();
    }

    function loadForEdit(row) {
        let settlementIds = [];
        try { settlementIds = JSON.parse(row.dataset.settlementIds || '[]'); } catch (error) { settlementIds = []; }

        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';
        fields.head_code.value = row.dataset.headCode || '';
        fields.name.value = row.dataset.name || '';
        fields.category.value = row.dataset.category || '';
        fields.default_primary_ledger_id.value = row.dataset.defaultPrimaryLedgerId || '';
        fields.default_movement.value = row.dataset.defaultMovement || 'Increase';
        fields.payment_method_required.value = row.dataset.paymentMethodRequired || '0';
        fields.party_required_mode.value = row.dataset.partyRequiredMode || 'No';
        setDropdownValue(fields.default_party_type_id, row.dataset.defaultPartyTypeId || '');
        fields.transaction_screen.value = row.dataset.transactionScreen || '';
        fields.transaction_screen.dataset.autoFilled = '0';
        fields.status.value = row.dataset.status || 'Active';
        fields.is_system_default.value = row.dataset.isSystemDefault || '0';
        fields.is_user_selectable.value = row.dataset.isUserSelectable || '1';
        fields.sort_order.value = row.dataset.sortOrder || '';
        fields.linked_accounting_rule_code.value = row.dataset.linkedAccountingRuleCode || '';
        fields.description.value = row.dataset.description || '';
        fields.help_text.value = row.dataset.helpText || '';
        fields.developer_note.value = row.dataset.developerNote || '';
        setSettlementIds(settlementIds);
        syncDerivedFields();
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        showToast('Transaction head loaded for editing.');
    }

    settlementBox.querySelectorAll('.select-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            chip.classList.toggle('selected');
            syncSettlementHidden();
        });
        chip.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                chip.click();
            }
        });
    });

    ['change', 'input'].forEach((eventName) => {
        [fields.party_required_mode, fields.payment_method_required, fields.is_user_selectable].forEach((field) => {
            field.addEventListener(eventName, syncDerivedFields);
        });
        fields.category.addEventListener(eventName, applyCategoryDefaults);
        fields.transaction_screen.addEventListener('input', () => { fields.transaction_screen.dataset.autoFilled = '0'; });
    });

    document.querySelectorAll('#headTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    const headTable = document.getElementById('headTable');
    const headTableWrap = document.getElementById('headTableWrap');
    const headFilterSearch = document.getElementById('headFilterSearch');
    const headFilterCategory = document.getElementById('headFilterCategory');
    const headFilterStatus = document.getElementById('headFilterStatus');
    const headLoadSize = document.getElementById('headLoadSize');
    const headVisibleSummary = document.getElementById('headVisibleSummary');
    const headResultCount = document.getElementById('resultCount');
    const headFilterEmptyRow = document.getElementById('headFilterEmptyRow');
    let headVisibleLimit = selectedHeadLoadSize();

    function selectedHeadLoadSize() {
        const value = headLoadSize?.value || '50';
        return value === 'all' ? Infinity : Number(value || 50);
    }

    function allHeadRows() {
        return Array.from(headTable?.querySelectorAll('tbody tr[data-head-row="true"]') || []);
    }

    function headMatchesFilters(row) {
        const search = String(headFilterSearch?.value || '').toLowerCase().trim();
        const category = String(headFilterCategory?.value || '').toLowerCase().trim();
        const status = String(headFilterStatus?.value || '').toLowerCase().trim();
        const rowText = String(row.dataset.text || row.innerText || '').toLowerCase();
        const rowCategory = String(row.dataset.category || '').toLowerCase();
        const rowStatus = String(row.dataset.status || '').toLowerCase();

        return (!search || rowText.includes(search))
            && (!category || rowCategory === category)
            && (!status || rowStatus === status);
    }

    function updateHeadVisibleSummary(shown, matched, total) {
        const text = total
            ? `Showing ${Math.min(shown, matched)} of ${matched} matching transaction head${matched === 1 ? '' : 's'} (${total} total). Scroll to load more.`
            : 'No transaction heads found.';

        if (headVisibleSummary) headVisibleSummary.textContent = text;
        if (headResultCount) headResultCount.textContent = matched
            ? `Showing ${Math.min(shown, matched)} of ${matched} entries`
            : `Showing 0 of ${total} entries`;
    }

    function renderHeadRows() {
        const rows = allHeadRows();
        const matchedRows = rows.filter((row) => row.dataset.filterMatch !== '0');
        const visibleRows = Number.isFinite(headVisibleLimit)
            ? matchedRows.slice(0, headVisibleLimit)
            : matchedRows;
        const visibleSet = new Set(visibleRows);

        rows.forEach((row) => {
            row.style.display = visibleSet.has(row) ? '' : 'none';
        });

        if (headFilterEmptyRow) {
            headFilterEmptyRow.style.display = rows.length > 0 && matchedRows.length === 0 ? '' : 'none';
        }

        updateHeadVisibleSummary(visibleRows.length, matchedRows.length, rows.length);
    }

    function applyHeadFilters(resetLimit = true) {
        const batchSize = selectedHeadLoadSize();
        if (resetLimit) headVisibleLimit = batchSize;
        if (batchSize === Infinity) headVisibleLimit = Infinity;
        if (!Number.isFinite(headVisibleLimit) || headVisibleLimit < batchSize) headVisibleLimit = batchSize;

        allHeadRows().forEach((row) => {
            row.dataset.filterMatch = headMatchesFilters(row) ? '1' : '0';
        });

        renderHeadRows();
    }

    function loadMoreHeadRows() {
        const batchSize = selectedHeadLoadSize();
        if (batchSize === Infinity) return;

        const matchedRows = allHeadRows().filter((row) => row.dataset.filterMatch !== '0');
        if (headVisibleLimit >= matchedRows.length) return;

        headVisibleLimit += batchSize;
        renderHeadRows();
    }

    [headFilterSearch, headFilterCategory, headFilterStatus].forEach((control) => {
        control?.addEventListener('input', () => applyHeadFilters(true));
        control?.addEventListener('change', () => applyHeadFilters(true));
    });

    headLoadSize?.addEventListener('change', () => applyHeadFilters(true));

    headTableWrap?.addEventListener('scroll', () => {
        const nearBottom = headTableWrap.scrollTop + headTableWrap.clientHeight >= headTableWrap.scrollHeight - 90;
        if (nearBottom) loadMoreHeadRows();
    }, { passive: true });


    form.addEventListener('submit', (event) => {
        syncSettlementHidden();
        if (selectedSettlementIds().length === 0) {
            event.preventDefault();
            event.stopImmediatePropagation();
            showToast('Select at least one allowed settlement type.');
            settlementBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, true);


    document.querySelectorAll('[data-scroll-target]').forEach((button) => {
        button.addEventListener('click', () => document.querySelector(button.dataset.scrollTarget)?.scrollIntoView({ behavior: 'smooth' }));
    });

    addButton?.addEventListener('click', () => {
        resetForm();
        showToast('Ready to add a new transaction head.');
    });

    cancelButton?.addEventListener('click', resetForm);

    applyHeadFilters(true);
    syncSettlementHidden();
    syncDerivedFields();
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/setup/transaction-heads.blade.php ENDPATH**/ ?>