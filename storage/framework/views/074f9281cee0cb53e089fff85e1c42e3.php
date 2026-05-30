<?php $__env->startSection('title', 'Transaction Entry | HisebGhor'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $money = fn ($value) => number_format((float) $value, 2);

    $headPayload = $transactionHeads->map(fn ($head) => [
        'id' => $head->id,
        'name' => $head->name,
        'nature' => $head->nature,
        'category' => $head->category ?: $head->nature,
        'transaction_screen' => $head->transaction_screen ?: 'Transaction Entry',
        'default_party_type_id' => $head->default_party_type_id,
        'default_party_type_name' => $head->defaultPartyType?->name,
        'payment_method_required' => (bool) $head->payment_method_required,
        'party_required_mode' => $head->party_required_mode ?: ($head->requires_party ? 'Required' : 'No'),
        'help_text' => $head->help_text,
        'requires_party' => (bool) $head->requires_party,
        'requires_reference' => (bool) $head->requires_reference,
        'settlements' => $head->settlementTypes->map(fn ($settlement) => [
            'id' => $settlement->id,
            'name' => $settlement->name,
            'code' => $settlement->code,
        ])->values(),
    ])->values();

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
            <p>Record business transactions without accounting complexity. The Accounting Engine prepares debit and credit automatically.</p>
        </div>
        <div class="prototype-actions">
            <button class="btn-outline" id="newBtn" type="button">+ New Transaction</button>
            <button class="btn-ghost" type="button" data-scroll-target="#recentTransactions">Recent Transactions</button>
            <button class="btn-ghost" type="button" data-scroll-target="#help">Help</button>
        </div>
    </div>

    <div class="prototype-stats four transaction-stats-redesign">
        <div class="card prototype-stat"><span>Today Cash In</span><strong class="green">BDT <?php echo e($money($todayCashIn)); ?></strong><small>Receipt and collections</small></div>
        <div class="card prototype-stat"><span>Today Cash Out</span><strong class="red">BDT <?php echo e($money($todayCashOut)); ?></strong><small>Payments and expenses</small></div>
        <div class="card prototype-stat"><span>Due Payable</span><strong class="orange">BDT <?php echo e($money($duePayable)); ?></strong><small>We need to pay</small></div>
        <div class="card prototype-stat"><span>Due Receivable</span><strong class="blue">BDT <?php echo e($money($dueReceivable)); ?></strong><small>We need to collect</small></div>
    </div>

    <div class="prototype-grid transaction-entry-grid">
        <div class="card prototype-card">
            <div class="prototype-card-header">
                <div>
                    <h3>Record a Transaction</h3>
                    <p>Enter what happened. HisebGhor will prepare the accounting entry.</p>
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

                    <div class="prototype-guidance inline-guidance">
                        <div class="prototype-guidance-icon">💡</div>
                        <div>
                            <strong>Simple entry. Accounting happens behind the screen.</strong>
                            <p id="guidanceText">Select a transaction type to see what information is needed.</p>
                        </div>
                    </div>

                    <div class="prototype-form-grid two">
                        <div class="prototype-field">
                            <label for="date">Date <span class="required">*</span></label>
                            <input type="date" id="date" name="voucher_date" value="<?php echo e(now()->toDateString()); ?>" required>
                        </div>

                        <div class="prototype-field">
                            <label for="screen">Transaction screen / category</label>
                            <input type="text" id="screen" readonly class="readonly-field" placeholder="Auto-filled after selecting transaction type">
                            <div class="hint">The category is decided from the transaction type.</div>
                        </div>

                        <div class="prototype-field full">
                            <label for="head">What type of transaction is this? <span class="required">*</span></label>
                            <select id="head" name="transaction_head_id" required>
                                <option value="">Loading Transaction Heads...</option>
                            </select>
                            <div class="hint">Example: Rent Payment, Customer Collection, Cash Sales.</div>
                        </div>

                        <div class="prototype-field" id="partyTypeWrap">
                            <label for="partyType">Who is involved?</label>
                            <input type="text" id="partyType" readonly class="readonly-field" placeholder="Auto from transaction type">
                        </div>

                        <div class="prototype-field" id="partyWrap">
                            <label for="party">Select person / business <span class="required" id="partyRequired">*</span></label>
                            <select id="party" name="party_id">
                                <option value="">Select saved Party / Person</option>
                            </select>
                        </div>

                        <div class="prototype-field" id="paymentWrap">
                            <label for="settlement">How was the payment made? <span class="required">*</span></label>
                            <select id="settlement" name="settlement_type_id" required>
                                <option value="">Select payment / settlement method</option>
                            </select>
                            <div class="hint" id="settlementHint">Only settlement types with active accounting rules are shown.</div>
                        </div>

                        <div class="prototype-field" id="cashBankWrap">
                            <label for="cashBank">Which cash/bank account?</label>
                            <select id="cashBank" name="cash_bank_account_id">
                                <option value="">Select cash/bank account</option>
                            </select>
                            <div class="hint">Only active cash/bank accounts are shown here.</div>
                        </div>

                        <div class="prototype-field money-wrap">
                            <label for="amount">Amount <span class="required">*</span></label>
                            <span>BDT</span>
                            <input type="number" id="amount" name="amount" value="10000" min="0.01" step="0.01" required>
                        </div>

                        <div class="prototype-field">
                            <label for="reference">Reference number</label>
                            <input id="reference" name="reference" placeholder="Example: BILL-1029">
                            <div class="hint" id="referenceHint">Optional unless transaction head requires it.</div>
                        </div>

                        <div class="prototype-field full">
                            <label for="notes">Note / narration</label>
                            <textarea id="notes" name="notes" placeholder="Write a short note, if needed"></textarea>
                        </div>

                        <div class="prototype-field full">
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

        <aside class="prototype-side-stack">
            <div class="card prototype-preview-card">
                <div class="prototype-card-header compact">
                    <div><h3>Accounting Preview</h3><p>Generated by rules before saving.</p></div>
                    <span class="badge badge-warning" id="mappingStatus">Waiting</span>
                </div>
                <div class="prototype-card-body">
                    <div class="table-wrap ledger-preview-wrap">
                        <table class="ledger-table">
                            <thead><tr><th>Ledger Account</th><th>Debit</th><th>Credit</th></tr></thead>
                            <tbody id="ledgerRows">
                                <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:22px">Select transaction information to preview ledger entries.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="validation warn" id="validationBox">Select transaction information to generate preview.</div>
                </div>
            </div>

            <div class="card prototype-preview-card">
                <h3>Transaction Summary</h3>
                <div class="prototype-preview-list">
                    <div><span>Voucher No.</span><strong id="voucherNo">—</strong></div>
                    <div><span>Voucher Type</span><strong id="voucherTypeSummary">—</strong></div>
                    <div><span>Nature</span><strong id="nature">—</strong></div>
                    <div><span>Party Effect</span><strong id="partyEffect">—</strong></div>
                    <div><span>Cash/Bank Effect</span><strong id="cashEffect">—</strong></div>
                    <div><span>Status</span><strong><span class="badge badge-warning" id="summaryStatus">Draft</span></strong></div>
                </div>
            </div>

            <div class="card prototype-guidance-card" id="help">
                <div class="prototype-guidance-icon">✓</div>
                <strong>How this screen helps non-accounting users</strong>
                <ul>
                    <li>You enter normal business information.</li>
                    <li>You do not choose debit or credit manually.</li>
                    <li>The accounting rule decides the journal entry.</li>
                    <li>You can review the entry before saving.</li>
                </ul>
            </div>
        </aside>
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

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const fallbackHeads = <?php echo json_encode($headPayload, 15, 512) ?>;
    const fallbackParties = <?php echo json_encode($partyPayload, 15, 512) ?>;
    const fallbackCashBanks = <?php echo json_encode($cashBankPayload, 15, 512) ?>;
    const form = document.getElementById('transactionForm');
    if (!form) return;

    const canPostTransaction = form.dataset.canPost === '1';
    const canSaveDraftTransaction = form.dataset.canDraft === '1';

    const date = document.getElementById('date');
    const screen = document.getElementById('screen');
    const head = document.getElementById('head');
    const partyType = document.getElementById('partyType');
    const party = document.getElementById('party');
    const partyRequired = document.getElementById('partyRequired');
    const amount = document.getElementById('amount');
    const settlement = document.getElementById('settlement');
    const settlementHint = document.getElementById('settlementHint');
    const cashBank = document.getElementById('cashBank');
    const reference = document.getElementById('reference');
    const referenceHint = document.getElementById('referenceHint');
    const notes = document.getElementById('notes');
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
        return {
            id: item.id,
            name: item.name || item.display_name,
            nature: item.nature,
            category: item.category || item.nature,
            transaction_screen: item.transaction_screen || 'Transaction Entry',
            default_party_type_id: item.default_party_type_id || null,
            default_party_type_name: item.default_party_type_name || '',
            party_required_mode: normalisePartyMode(item.party_required_mode || (item.requires_party ? 'Required' : 'No')),
            payment_method_required: Boolean(Number(item.payment_method_required) || item.payment_method_required === true),
            help_text: item.help_text || '',
            requires_party: bool(item.requires_party) || normalisePartyMode(item.party_required_mode) === 'Required',
            requires_reference: Boolean(Number(item.requires_reference) || item.requires_reference === true),
            settlements: settlements.map((settlementItem) => ({ id: settlementItem.id, name: settlementItem.name || settlementItem.display_name, code: settlementItem.code || '' })),
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
        const value = `${selectedHead?.nature || ''} ${selectedHead?.name || ''} ${settlementText()}`.toUpperCase();
        if (value.includes('RECEIPT') || value.includes('RECEIVED') || value.includes('COLLECTION') || value.includes('INCOME') || value.includes('CAPITAL') || value.includes('ADVANCE_RECEIVED') || value.includes('ADVANCE RECEIVED')) return 'received in';
        return 'paid from';
    }

    function updateSettlementHint() {
        settlementHint.textContent = selectedHeadRequiresCashBank()
            ? `Select the Cash/Bank account where money will be ${cashBankDirectionLabel()}. The accounting rule still decides Debit/Credit.`
            : 'Only settlement types with active accounting rules are shown. Cash/Bank may stay blank if this is a due or journal transaction.';
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

    async function loadTransactionHeads() {
        const previousValue = head.value;
        const renderHeadDropdown = () => {
            renderOptions(head, heads, 'Select transaction type', (item) => item.name);
            head.disabled = false;
            if (previousValue && heads.some((item) => String(item.id) === String(previousValue))) head.value = previousValue;
            else if (!head.value && heads.length > 0) head.value = heads[0].id;
            if (heads.length === 0) head.innerHTML = '<option value="">No active Transaction Heads found</option>';
        };

        head.disabled = true;
        heads = fallbackHeads.map(normaliseHead);
        if (heads.length > 0) renderHeadDropdown();

        try {
            const apiHeads = (await getRows(form.dataset.headsUrl)).map(normaliseHead);
            if (apiHeads.length > 0) { heads = apiHeads; renderHeadDropdown(); }
        } catch (error) {
            console.warn('Transaction Heads API fallback used:', error);
            if (heads.length === 0) renderHeadDropdown();
        }
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
        if (!selectedHead) { settlement.innerHTML = '<option value="">Select transaction type first</option>'; settlement.disabled = false; resetPreview(); return; }
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
        toggleCashBank();
    }

    async function refreshForSelectedHead() {
        const selectedHead = headById(head.value);
        party.required = false;
        partyRequired.style.display = 'none';
        screen.value = selectedHead ? `${selectedHead.transaction_screen || 'Transaction Entry'} / ${selectedHead.category || selectedHead.nature || 'General'}` : '';
        guidanceText.textContent = selectedHead?.help_text || 'Enter normal business information. The accounting rule will prepare Debit and Credit automatically.';
        reference.required = Boolean(selectedHead?.requires_reference);
        referenceHint.textContent = selectedHead?.requires_reference ? 'Reference is required for this transaction head.' : 'Optional unless transaction head requires it.';

        await loadSettlementOptions();
        await loadParties();

        const requirement = selectedRequirement();
        const partyMustBeSelected = requirement.partyRequired && !hasDefaultNotApplicableParty();
        const partyLabel = requirement.partyTypeName || requirement.partyMode || 'Not required';
        partyType.value = requirement.partyMode === 'No' ? 'Not required by selected rule' : `${partyLabel} (${requirement.partyMode})`;
        party.required = partyMustBeSelected;
        partyRequired.style.display = partyMustBeSelected ? '' : 'none';
        document.getElementById('partyWrap')?.classList.toggle('soft-required', partyMustBeSelected);
        toggleCashBank();
        if (!isBooting) schedulePreview();
    }

    function toggleCashBank() {
        const show = selectedHeadRequiresCashBank();
        cashBank.required = show && cashBanks.length > 0;
        document.getElementById('cashBankWrap').classList.toggle('soft-required', show);
        updateSettlementHint();
    }

    function formReadyForPreview() {
        if (!date.value || !head.value || !settlement.value || Number(amount.value || 0) <= 0) return false;
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
        toggleCashBank();
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
        date.value = '<?php echo e(now()->toDateString()); ?>';
        amount.value = '10000';
        if (heads.length > 0) head.value = heads[0].id;
        await refreshForSelectedHead();
        resetPreview('Complete required fields to generate ledger preview.');
        showToast('Form cleared.');
    }

    [date, party, amount, settlement, cashBank, reference, notes].forEach((input) => {
        input.addEventListener('input', schedulePreview);
        input.addEventListener('change', schedulePreview);
    });
    head.addEventListener('change', () => refreshForSelectedHead());
    settlement.addEventListener('change', async () => { await loadParties(); toggleCashBank(); schedulePreview(); });
    form.addEventListener('submit', (event) => { event.preventDefault(); submitTransaction('Posted'); });
    document.getElementById('draftBtn')?.addEventListener('click', () => submitTransaction('Draft'));
    document.getElementById('clearBtn')?.addEventListener('click', clearForm);
    document.getElementById('newBtn')?.addEventListener('click', clearForm);
    document.querySelectorAll('[data-scroll-target]').forEach((button) => button.addEventListener('click', () => document.querySelector(button.dataset.scrollTarget)?.scrollIntoView({ behavior: 'smooth' })));

    async function boot() {
        statusInput.value = canPostTransaction ? statusInput.value : 'Draft';
        summaryStatus.textContent = statusInput.value;
        summaryStatus.className = statusInput.value === 'Posted' ? 'badge badge-success' : 'badge badge-warning';
        resetPreview();
        await Promise.all([loadTransactionHeads(), loadCashBanks()]);
        await refreshForSelectedHead();
        isBooting = false;
        schedulePreview();
    }

    boot();
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/transactions/create.blade.php ENDPATH**/ ?>