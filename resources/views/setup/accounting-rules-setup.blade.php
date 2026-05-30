@extends('layouts.app')

@section('title', 'Accounting Rule Setup | HisebGhor')

@section('content')
@php
    $activeRules = $rules->where('status', 'Active')->count();
    $draftRules = $rules->whereIn('status', ['Draft', 'Pending Review'])->count();
    $cashBankRules = $rules->where('cash_bank_ledger_required', true)->count();
    $partyRules = $rules->filter(fn ($rule) => ($rule->party_required_mode ?: $rule->party_required) !== 'No')->count();
    $fixedCounterRules = $rules->filter(fn ($rule) => ($rule->counter_selection_method ?: 'Fixed by Rule') === 'Fixed by Rule')->count();
    $userSelectedCounterRules = $rules->filter(fn ($rule) => ($rule->counter_selection_method ?: '') === 'User Selected')->count();
    $headsCovered = $rules->pluck('transaction_head_id')->filter()->unique()->count();
    $pendingReviewRules = $rules->where('status', 'Pending Review')->count();
@endphp

<div class="prototype-page accounting-rule-page">
    <div class="prototype-hero">
        <div>
            <span class="page-label">Accounting Rule Setup</span>
            <h2>Accounting Rule Setup</h2>
            <p>Define how HisebGhor will auto-post debit and credit for each transaction head.</p>
        </div>
        <div class="prototype-actions">
            <button class="btn-outline" type="button" id="addRuleBtn">+ Add New Rule</button>
            <button class="btn-ghost" type="button" data-scroll-target="#ruleListCard">View All Rules</button>
            <button class="btn-ghost" type="button" data-scroll-target="#journalPreview">Test Rule</button>
        </div>
    </div>

    @include('partials.setup-progress', ['current' => 6])

    <div class="prototype-stats eight">
        <div class="card prototype-stat"><span>Total Rules</span><strong>{{ $rules->count() }}</strong><small>Posting mappings</small></div>
        <div class="card prototype-stat"><span>Active Rules</span><strong>{{ $activeRules }}</strong><small>Used by transaction entry</small></div>
        <div class="card prototype-stat"><span>Cash/Bank Rules</span><strong>{{ $cashBankRules }}</strong><small>Requires payment ledger</small></div>
        <div class="card prototype-stat"><span>Party Rules</span><strong>{{ $partyRules }}</strong><small>Uses party/sub-ledger</small></div>
        <div class="card prototype-stat"><span>Fixed Counter</span><strong>{{ $fixedCounterRules }}</strong><small>Counter ledger fixed</small></div>
        <div class="card prototype-stat"><span>User Selected</span><strong>{{ $userSelectedCounterRules }}</strong><small>User chooses counter</small></div>
        <div class="card prototype-stat"><span>Heads Covered</span><strong>{{ $headsCovered }}</strong><small>Transaction heads mapped</small></div>
        <div class="card prototype-stat"><span>Pending Review</span><strong>{{ $pendingReviewRules }}</strong><small>Needs activation</small></div>
    </div>

    <div class="prototype-grid accounting-rule-redesign-grid">
        <div class="card prototype-card">
            <div class="prototype-card-header">
                <div>
                    <h3>Guided Accounting Rule Setup</h3>
                    <p>Define trigger, required user input, primary ledger, and counter ledger.</p>
                </div>
                <span class="badge badge-primary">Rule Engine</span>
            </div>

            <div class="prototype-card-body">
                <form
                    id="ruleForm"
                    class="prototype-form"
                    data-frontend-form
                    data-action="{{ route('api.accounting-rules-setup.store') }}"
                    data-store-url="{{ route('api.accounting-rules-setup.store') }}"
                    data-success="Accounting rule saved successfully."
                >
                    @csrf
                    <input type="hidden" name="_method" id="ruleFormMethod" value="POST">
                    <input type="hidden" name="debit_account_id" id="debitAccountId">
                    <input type="hidden" name="credit_account_id" id="creditAccountId">
                    <input type="hidden" name="auto_post" value="1">
                    <input type="hidden" name="description" id="ruleDescription">
                    <input type="hidden" name="party_ledger_effect" id="partyLedgerEffect">

                    <div class="section-block">
                        <div class="section-heading">
                            <span>1</span>
                            <div><strong>Rule identity</strong><small>Connect this rule with an active Transaction Head.</small></div>
                        </div>
                        <div class="section-body prototype-form-grid three">
                            <div class="prototype-field">
                                <label for="ruleCode">Rule Code</label>
                                <input id="ruleCode" name="rule_code" placeholder="Auto: AR-001">
                            </div>
                            <div class="prototype-field">
                                <label for="ruleName">Rule Name <span class="required">*</span></label>
                                <input id="ruleName" name="rule_name" required placeholder="Example: Customer Collection - Cash">
                            </div>
                            <div class="prototype-field">
                                <label for="transactionHead">Transaction Head <span class="required">*</span></label>
                                <select id="transactionHead" name="transaction_head_id" required>
                                    <option value="">Select transaction head</option>
                                    @foreach($transactionHeads as $head)
                                        <option
                                            value="{{ $head->id }}"
                                            data-screen="{{ e($head->transaction_screen ?: 'Transaction Entry') }}"
                                            data-category="{{ e($head->category ?: $head->nature) }}"
                                            data-payment-required="{{ $head->payment_method_required ? 1 : 0 }}"
                                            data-party-mode="{{ e($head->party_required_mode ?: ($head->requires_party ? 'Required' : 'No')) }}"
                                            data-settlements='@json($head->settlementTypes->pluck('id')->map(fn ($id) => (string) $id)->values())'
                                        >{{ $head->head_code ? $head->head_code . ' - ' : '' }}{{ $head->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="hidden" hidden aria-hidden="true">
                                <select id="settlementType" name="settlement_type_id">
                                    <option value="">Select settlement type</option>
                                    @foreach($settlementTypes as $settlementType)
                                        <option value="{{ $settlementType->id }}" data-code="{{ e($settlementType->code) }}">{{ $settlementType->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="transactionScreen">Transaction Screen</label>
                                <input id="transactionScreen" name="transaction_screen" placeholder="Auto from Transaction Head">
                            </div>
                            <div class="prototype-field">
                                <label for="ruleTrigger">Rule Trigger</label>
                                <select id="ruleTrigger" name="rule_trigger" required>
                                    <option value="Transaction Head selected">Transaction Head selected</option>
                                    <option value="Payment Method selected">Payment Method selected</option>
                                    <option value="Party Type selected">Party Type selected</option>
                                    <option value="System mapping matched">System mapping matched</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="ruleStatus">Rule Status</label>
                                <select id="ruleStatus" name="rule_status" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                    <option value="Draft">Draft</option>
                                    <option value="Pending Review">Pending Review</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="section-block">
                        <div class="section-heading">
                            <span>2</span>
                            <div><strong>User input requirements</strong><small>Specify what the transaction entry screen should ask from users.</small></div>
                        </div>
                        <div class="section-body prototype-form-grid three">
                            <div class="prototype-field">
                                <label for="amountRequired">Amount Required</label>
                                <select id="amountRequired" name="amount_required">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="paymentRequired">Payment Method Required</label>
                                <select id="paymentRequired" name="payment_method_required">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="allowedPayment">Allowed Payment Method</label>
                                <select id="allowedPayment" name="allowed_payment_method" required>
                                    <option value="N/A">N/A</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank">Bank</option>
                                    <option value="Cash, Bank">Cash, Bank</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="cashBankRequired">Cash/Bank Ledger Required</label>
                                <select id="cashBankRequired" name="cash_bank_ledger_required">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="partyRequiredMode">Party/Sub-Ledger Required</label>
                                <select id="partyRequiredMode" name="party_required_mode" required>
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                    <option value="Optional">Optional</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="partySubLedgerType">Party/Sub-Ledger Type</label>
                                <select id="partySubLedgerType" name="party_sub_ledger_type">
                                    <option value="None">None</option>
                                    <option value="Customer">Customer</option>
                                    <option value="Supplier">Supplier</option>
                                    <option value="Employee">Employee</option>
                                    <option value="Owner">Owner</option>
                                </select>
                            </div>
                            <div class="prototype-field full">
                                <label for="otherRequiredInput">Other Required Input</label>
                                <input id="otherRequiredInput" name="other_required_input" placeholder="Example: Invoice number, loan schedule, installment month">
                            </div>
                        </div>
                    </div>

                    <div class="section-block">
                        <div class="section-heading">
                            <span>3</span>
                            <div><strong>Primary ledger</strong><small>The main ledger affected by the selected Transaction Head.</small></div>
                        </div>
                        <div class="section-body prototype-form-grid two">
                            <div class="prototype-field">
                                <label for="primarySource">Primary Ledger Source</label>
                                <select id="primarySource" name="primary_ledger_source" required>
                                    <option value="Fixed Ledger">Fixed Ledger</option>
                                    <option value="User Selected Cash/Bank Ledger">User Selected Cash/Bank Ledger</option>
                                    <option value="Transaction Head Based Ledger">Transaction Head Based Ledger</option>
                                    <option value="System Derived Ledger">System Derived Ledger</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="primaryLedger">Primary Ledger / CoA Code <span class="required">*</span></label>
                                <select id="primaryLedger" name="primary_ledger_id" required>
                                    <option value="">Select ledger</option>
                                    @foreach($accounts as $account)
                                        <option
                                            value="{{ $account->id }}"
                                            data-code="{{ e($account->account_code) }}"
                                            data-name="{{ e($account->account_name) }}"
                                            data-type="{{ e($account->accountType?->name) }}"
                                            data-normal="{{ e($account->normal_balance ?: $account->accountType?->normal_balance) }}"
                                            data-cash-bank="{{ $account->is_cash_bank ? 1 : 0 }}"
                                        >{{ $account->display_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label>Primary Ledger Name</label>
                                <input id="primaryLedgerName" readonly class="readonly-field" placeholder="Auto-filled from CoA">
                            </div>
                            <div class="prototype-field">
                                <label for="primaryMovement">Primary Ledger Movement</label>
                                <select id="primaryMovement" name="primary_ledger_movement" required>
                                    <option value="Increase">Increase</option>
                                    <option value="Decrease">Decrease</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="primarySide">Primary Posting Side</label>
                                <select id="primarySide" name="primary_posting_side" required>
                                    <option value="Debit">Debit</option>
                                    <option value="Credit">Credit</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label>Plain explanation</label>
                                <textarea id="primaryExplanation" name="primary_explanation" placeholder="Example: Customer receivable decreases when money is collected."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="section-block">
                        <div class="section-heading">
                            <span>4</span>
                            <div><strong>Counter ledger</strong><small>The balancing ledger selected by rule, user, payment method, or system mapping.</small></div>
                        </div>
                        <div class="section-body prototype-form-grid two">
                            <div class="prototype-field">
                                <label for="counterSource">Counter Ledger Source</label>
                                <select id="counterSource" name="counter_ledger_source" required>
                                    <option value="Fixed Ledger">Fixed Ledger</option>
                                    <option value="User Selected Cash/Bank Ledger">User Selected Cash/Bank Ledger</option>
                                    <option value="User Selected Party Control Ledger">User Selected Party Control Ledger</option>
                                    <option value="Transaction Head Based Ledger">Transaction Head Based Ledger</option>
                                    <option value="Payment Method Based Ledger">Payment Method Based Ledger</option>
                                    <option value="Party Type Based Ledger">Party Type Based Ledger</option>
                                    <option value="System Derived Ledger">System Derived Ledger</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="counterMethod">Counter Ledger Selection Method</label>
                                <select id="counterMethod" name="counter_selection_method" required>
                                    <option value="Fixed by Rule">Fixed by Rule</option>
                                    <option value="Selected by User">Selected by User</option>
                                    <option value="Derived from Payment Method">Derived from Payment Method</option>
                                    <option value="Derived from Party Type">Derived from Party Type</option>
                                    <option value="Derived from Transaction Head">Derived from Transaction Head</option>
                                    <option value="Derived from System Mapping">Derived from System Mapping</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="fixedCounterLedger">Fixed Counter Ledger / CoA Code <span class="required">*</span></label>
                                <select id="fixedCounterLedger" name="fixed_counter_ledger_id" required>
                                    <option value="">Select counter ledger</option>
                                    @foreach($accounts as $account)
                                        <option
                                            value="{{ $account->id }}"
                                            data-code="{{ e($account->account_code) }}"
                                            data-name="{{ e($account->account_name) }}"
                                            data-type="{{ e($account->accountType?->name) }}"
                                            data-normal="{{ e($account->normal_balance ?: $account->accountType?->normal_balance) }}"
                                            data-cash-bank="{{ $account->is_cash_bank ? 1 : 0 }}"
                                        >{{ $account->display_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="allowedCounterType">Allowed Counter Ledger Type</label>
                                <select id="allowedCounterType" name="allowed_counter_ledger_type" required>
                                    <option value="N/A">N/A</option>
                                    <option value="Cash/Bank">Cash/Bank</option>
                                    <option value="Customer Receivable">Customer Receivable</option>
                                    <option value="Supplier Payable">Supplier Payable</option>
                                    <option value="Income">Income</option>
                                    <option value="Expense">Expense</option>
                                    <option value="Asset">Asset</option>
                                    <option value="Liability">Liability</option>
                                    <option value="Equity">Equity</option>
                                    <option value="Party Control">Party Control</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="counterMovement">Counter Ledger Movement</label>
                                <select id="counterMovement" name="counter_ledger_movement" required>
                                    <option value="Decrease">Decrease</option>
                                    <option value="Increase">Increase</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="counterSide">Counter Posting Side</label>
                                <select id="counterSide" name="counter_posting_side" required>
                                    <option value="Credit">Credit</option>
                                    <option value="Debit">Debit</option>
                                </select>
                            </div>
                            <div class="prototype-field full">
                                <label>Plain explanation</label>
                                <textarea id="counterExplanation" name="counter_explanation" placeholder="Example: Cash increases when customer pays."></textarea>
                            </div>
                        </div>
                    </div>



                    <div id="ruleLineTableBody" hidden aria-hidden="true"></div>
                    <template id="ruleLineTemplate">
                        <div class="rule-line-row">
                            <input type="hidden" name="rule_lines[__INDEX__][line_role]" data-line-field="line_role">
                            <input type="hidden" name="rule_lines[__INDEX__][side]" data-line-field="side">
                            <input type="hidden" name="rule_lines[__INDEX__][ledger_source]" data-line-field="ledger_source">
                            <input type="hidden" name="rule_lines[__INDEX__][ledger_id]" data-line-field="ledger_id">
                            <input type="hidden" name="rule_lines[__INDEX__][amount_source]" data-line-field="amount_source">
                            <input type="hidden" name="rule_lines[__INDEX__][amount_formula]" data-line-field="amount_formula">
                            <input type="hidden" name="rule_lines[__INDEX__][movement]" data-line-field="movement">
                            <input type="hidden" name="rule_lines[__INDEX__][allowed_ledger_type]" data-line-field="allowed_ledger_type">
                            <input type="hidden" name="rule_lines[__INDEX__][explanation]" data-line-field="explanation">
                        </div>
                    </template>



                    <div class="prototype-form-actions">
                        <button type="button" class="btn-ghost" id="clearRuleBtn">Clear</button>
                        <button type="submit" class="btn-primary">Save Accounting Rule</button>
                    </div>
                </form>
            </div>
        </div>

        <aside class="prototype-side-stack">
            <div class="card prototype-preview-card" id="journalPreview">
                <h3>Expected Journal Entry</h3>
                <div class="journal-preview-body empty">Select ledgers and amount to preview Debit/Credit.</div>
            </div>

            <div class="card prototype-preview-card prototype-test-block" id="ruleTestCard">
                <h3>Auto Journal Preview / Rule Test</h3>
                <p class="muted" style="margin:4px 0 14px">Try sample input and see the expected journal entry.</p>
                <div class="prototype-form-grid one">
                            <div class="prototype-field">
                                <label for="testAmount">Example Amount</label>
                                <input id="testAmount" type="number" min="0.01" step="0.01" value="10000">
                    </div>
                            <div class="prototype-field">
                                <label for="testParty">Example Party</label>
                                <input id="testParty" placeholder="Example: ABC Customer">
                            </div>
                            <div class="prototype-field">
                                <label for="testPayment">Example Payment Method</label>
                                <select id="testPayment">
                                    <option value="Cash">Cash</option>
                                    <option value="Bank">Bank</option>
                                    <option value="N/A">N/A</option>
                                </select>
                            </div>
                            <div class="prototype-field">
                                <label for="testCashBank">Example Cash/Bank Ledger</label>
                                <input id="testCashBank" placeholder="Example: Main Cash">
                            </div>
                        </div>
                    </div>
        </aside>
    </div>

    <section class="card prototype-card prototype-section" id="ruleListCard">
        <div class="prototype-card-header">
            <div>
                <h3>Accounting Rule List</h3>
                <p>Search, filter, edit, and test available posting rules.</p>
            </div>
            <span class="badge badge-neutral" id="ruleResultCount">Showing {{ $rules->count() }} of {{ $rules->count() }} entries</span>
        </div>
        <div class="prototype-card-body">
            <div class="prototype-filter-grid" data-table-filter="#ruleTable" data-count-target="#ruleResultCount">
                <div class="field search-field"><span>⌕</span><input placeholder="Search accounting rules..." data-filter-key="text"></div>
                <div><label>Status</label><select data-filter-key="status"><option value="">All Status</option><option value="Active">Active</option><option value="Inactive">Inactive</option><option value="Draft">Draft</option><option value="Pending Review">Pending Review</option></select></div>
                <div><label>Transaction Screen</label><input placeholder="Screen" data-filter-key="screen"></div>
            </div>

            <div class="table-wrap prototype-table-wrap always-scroll">
                <table id="ruleTable">
                    <thead>
                        <tr>
                            <th>Rule Code</th>
                            <th>Rule Name</th>
                            <th>Transaction Head</th>
                            <th>Primary Ledger</th>
                            <th>Primary Side</th>
                            <th>Counter Source</th>
                            <th>Counter Selection</th>
                            <th>Counter Side</th>
                            <th>Party Required</th>
                            <th>Payment Required</th>
                            <th>Transaction Screen</th>
                            <th>Status</th>
                            <th style="text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rules as $rule)
                            @php
                                $primary = $rule->primaryLedger ?: $rule->debitAccount;
                                $counter = $rule->fixedCounterLedger ?: $rule->creditAccount;
                                $partyMode = $rule->party_required_mode ?: $rule->party_required;
                                $syncedLines = $rule->accountingRule?->lines ?? collect();
                                $ruleLinePayload = $syncedLines->isNotEmpty()
                                    ? $syncedLines->map(fn ($line) => [
                                        'line_role' => $line->line_role,
                                        'side' => $line->side,
                                        'ledger_source' => $line->ledger_source,
                                        'ledger_id' => $line->ledger_id,
                                        'movement' => $line->movement ?: 'Increase',
                                        'selection_method' => $line->selection_method,
                                        'allowed_ledger_type' => $line->allowed_ledger_type,
                                        'amount_source' => $line->amount_source ?: 'transaction_amount',
                                        'amount_formula' => $line->amount_formula,
                                        'explanation' => $line->explanation,
                                    ])->values()
                                    : collect([
                                        [
                                            'line_role' => 'primary',
                                            'side' => $rule->primary_posting_side ?: 'Debit',
                                            'ledger_source' => 'fixed',
                                            'ledger_id' => $rule->primary_ledger_id ?: $rule->debit_account_id,
                                            'movement' => $rule->primary_ledger_movement ?: 'Increase',
                                            'selection_method' => null,
                                            'allowed_ledger_type' => null,
                                            'amount_source' => 'transaction_amount',
                                            'amount_formula' => null,
                                            'explanation' => $rule->primary_explanation,
                                        ],
                                        [
                                            'line_role' => 'counter',
                                            'side' => $rule->counter_posting_side ?: 'Credit',
                                            'ledger_source' => str_contains(strtolower((string) $rule->counter_ledger_source), 'cash') ? 'user_cash_bank' : 'fixed',
                                            'ledger_id' => $rule->fixed_counter_ledger_id ?: $rule->credit_account_id,
                                            'movement' => $rule->counter_ledger_movement ?: 'Decrease',
                                            'selection_method' => $rule->counter_selection_method,
                                            'allowed_ledger_type' => $rule->allowed_counter_ledger_type,
                                            'amount_source' => 'transaction_amount',
                                            'amount_formula' => null,
                                            'explanation' => $rule->counter_explanation,
                                        ],
                                    ]);
                                $debitLineCount = $ruleLinePayload->where('side', 'Debit')->count();
                                $creditLineCount = $ruleLinePayload->where('side', 'Credit')->count();
                                $lineSummary = $debitLineCount . ' Dr / ' . $creditLineCount . ' Cr';
                            @endphp
                            <tr
                                data-id="{{ $rule->id }}"
                                data-text="{{ e(trim(($rule->rule_code ? $rule->rule_code . ' ' : '') . ($rule->rule_name ?: '') . ' ' . ($rule->transactionHead?->name ?: '') . ' ' . ($primary?->display_name ?: '') . ' ' . ($counter?->display_name ?: ''))) }}"
                                data-screen="{{ e($rule->transaction_screen) }}"
                                data-status="{{ e($rule->status) }}"
                                data-rule-code="{{ e($rule->rule_code) }}"
                                data-rule-name="{{ e($rule->rule_name) }}"
                                data-transaction-head-id="{{ $rule->transaction_head_id }}"
                                data-settlement-type-id="{{ $rule->settlement_type_id }}"
                                data-transaction-screen="{{ e($rule->transaction_screen) }}"
                                data-rule-trigger="{{ e($rule->rule_trigger ?: 'Transaction Head selected') }}"
                                data-rule-status="{{ e($rule->status) }}"
                                data-amount-required="{{ $rule->amount_required ? 1 : 0 }}"
                                data-payment-method-required="{{ $rule->payment_method_required ? 1 : 0 }}"
                                data-allowed-payment-method="{{ e($rule->allowed_payment_method ?: 'N/A') }}"
                                data-cash-bank-ledger-required="{{ $rule->cash_bank_ledger_required ? 1 : 0 }}"
                                data-party-required-mode="{{ e($partyMode ?: 'No') }}"
                                data-party-sub-ledger-type="{{ e($rule->party_sub_ledger_type ?: 'None') }}"
                                data-other-required-input="{{ e($rule->other_required_input) }}"
                                data-primary-ledger-source="{{ e($rule->primary_ledger_source ?: 'Fixed Ledger') }}"
                                data-primary-ledger-id="{{ $rule->primary_ledger_id ?: $rule->debit_account_id }}"
                                data-primary-ledger-movement="{{ e($rule->primary_ledger_movement ?: 'Increase') }}"
                                data-primary-posting-side="{{ e($rule->primary_posting_side ?: 'Debit') }}"
                                data-primary-explanation="{{ e($rule->primary_explanation) }}"
                                data-counter-ledger-source="{{ e($rule->counter_ledger_source ?: 'Fixed Ledger') }}"
                                data-counter-selection-method="{{ e($rule->counter_selection_method ?: 'Fixed by Rule') }}"
                                data-fixed-counter-ledger-id="{{ $rule->fixed_counter_ledger_id ?: $rule->credit_account_id }}"
                                data-allowed-counter-ledger-type="{{ e($rule->allowed_counter_ledger_type ?: 'N/A') }}"
                                data-counter-ledger-movement="{{ e($rule->counter_ledger_movement ?: 'Decrease') }}"
                                data-counter-posting-side="{{ e($rule->counter_posting_side ?: 'Credit') }}"
                                data-counter-explanation="{{ e($rule->counter_explanation) }}"
                                data-party-ledger-effect="{{ e($rule->party_ledger_effect) }}"
                                data-description="{{ e($rule->description) }}"
                                data-rule-lines="{{ e($ruleLinePayload->toJson()) }}"
                                data-update-url="{{ route('api.accounting-rules-setup.update', $rule) }}"
                            >
                                <td class="code">{{ $rule->rule_code ?: '—' }}</td>
                                <td class="strong">{{ $rule->rule_name ?: $rule->description ?: 'Accounting Rule' }}</td>
                                <td>{{ $rule->transactionHead?->name ?? '—' }}</td>
                                <td>{{ $primary?->display_name ?? '—' }}</td>
                                <td><span class="badge badge-primary">{{ $rule->primary_posting_side ?: 'Debit' }}</span></td>
                                <td>{{ $rule->counter_ledger_source ?: 'Fixed Ledger' }}</td>
                                <td>{{ $rule->counter_selection_method ?: 'Fixed by Rule' }}</td>
                                <td><span class="badge badge-purple">{{ $rule->counter_posting_side ?: 'Credit' }}</span></td>
                                <td>{{ $partyMode ?: 'No' }}</td>
                                <td>{{ $rule->payment_method_required ? 'Yes' : 'No' }}</td>
                                <td>{{ $rule->transaction_screen ?: $rule->transactionHead?->transaction_screen ?: 'Transaction Entry' }}</td>
                                <td><span class="badge {{ $rule->status === 'Active' ? 'badge-success' : 'badge-neutral' }}">{{ $rule->status }}</span></td>
                                <td>
                                    <div class="action-cell">
                                        <button type="button" class="icon-btn edit-btn" title="Edit">✎</button>
                                        <form method="POST" data-delete-form action="{{ route('setup.accounting-rules-setup.destroy', $rule) }}" onsubmit="return confirm('Delete this accounting rule?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty="true"><td colspan="13" class="muted" style="text-align:center;padding:24px">No accounting rules found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('ruleForm');
    if (!form) return;

    const methodInput = document.getElementById('ruleFormMethod');
    const debitInput = document.getElementById('debitAccountId');
    const creditInput = document.getElementById('creditAccountId');
    const ruleDescription = document.getElementById('ruleDescription');
    const partyLedgerEffect = document.getElementById('partyLedgerEffect');
    const journalPreview = document.querySelector('#journalPreview .journal-preview-body');
    const ruleLineBody = document.getElementById('ruleLineTableBody');
    const ruleLineTemplate = document.getElementById('ruleLineTemplate');
    let lastPostingSideChanged = 'primary';

    const fields = {
        rule_code: document.getElementById('ruleCode'),
        rule_name: document.getElementById('ruleName'),
        transaction_head_id: document.getElementById('transactionHead'),
        settlement_type_id: document.getElementById('settlementType'),
        transaction_screen: document.getElementById('transactionScreen'),
        rule_trigger: document.getElementById('ruleTrigger'),
        rule_status: document.getElementById('ruleStatus'),
        amount_required: document.getElementById('amountRequired'),
        payment_method_required: document.getElementById('paymentRequired'),
        allowed_payment_method: document.getElementById('allowedPayment'),
        cash_bank_ledger_required: document.getElementById('cashBankRequired'),
        party_required_mode: document.getElementById('partyRequiredMode'),
        party_sub_ledger_type: document.getElementById('partySubLedgerType'),
        other_required_input: document.getElementById('otherRequiredInput'),
        primary_ledger_source: document.getElementById('primarySource'),
        primary_ledger_id: document.getElementById('primaryLedger'),
        primary_ledger_movement: document.getElementById('primaryMovement'),
        primary_posting_side: document.getElementById('primarySide'),
        primary_explanation: document.getElementById('primaryExplanation'),
        counter_ledger_source: document.getElementById('counterSource'),
        counter_selection_method: document.getElementById('counterMethod'),
        fixed_counter_ledger_id: document.getElementById('fixedCounterLedger'),
        allowed_counter_ledger_type: document.getElementById('allowedCounterType'),
        counter_ledger_movement: document.getElementById('counterMovement'),
        counter_posting_side: document.getElementById('counterSide'),
        counter_explanation: document.getElementById('counterExplanation'),
        primaryLedgerName: document.getElementById('primaryLedgerName'),
        testAmount: document.getElementById('testAmount'),
        testParty: document.getElementById('testParty'),
        testPayment: document.getElementById('testPayment'),
        testCashBank: document.getElementById('testCashBank'),
    };

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }
        alert(message);
    }

    function selectedOption(select) {
        return select?.selectedOptions?.[0] || null;
    }

    function optionLabel(select) {
        return selectedOption(select)?.textContent?.trim() || '';
    }

    function money(value) {
        return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function normaliseLedgerSource(source) {
        switch (source) {
            case 'User Selected Cash/Bank Ledger': return 'user_cash_bank';
            case 'User Selected Party Control Ledger': return 'party_control';
            case 'Transaction Head Based Ledger': return 'transaction_head';
            case 'Payment Method Based Ledger': return 'user_cash_bank';
            case 'Party Type Based Ledger': return 'party_control';
            case 'System Derived Ledger': return 'system_derived';
            default: return 'fixed';
        }
    }

    function lineLabel(line) {
        const selector = line.role === 'primary' ? fields.primary_ledger_id : fields.fixed_counter_ledger_id;
        const fallback = selectedOption(selector)?.textContent?.trim() || 'Selected Ledger';
        if (line.ledger_source === 'user_cash_bank') {
            return fields.testCashBank.value || fallback || 'Selected Cash/Bank Ledger';
        }
        if (line.ledger_source === 'party_control') {
            return fallback || 'Selected Party Control Ledger';
        }
        if (line.ledger_source === 'transaction_head') {
            return fallback || 'Transaction Head Default Ledger';
        }
        if (line.ledger_source === 'system_derived') {
            return fallback || 'System Derived Ledger';
        }
        return fallback;
    }

    function setLineValue(row, field, value) {
        const input = row.querySelector(`[data-line-field="${field}"]`);
        if (input) input.value = value ?? '';
    }

    function getLineValue(row, field) {
        return row.querySelector(`[data-line-field="${field}"]`)?.value ?? '';
    }

    function createRuleLine(data = {}, index = 0) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = ruleLineTemplate.innerHTML.replaceAll('__INDEX__', String(index)).trim();
        const row = wrapper.firstElementChild;
        ruleLineBody.appendChild(row);

        setLineValue(row, 'line_role', data.line_role || (index === 0 ? 'primary' : 'counter'));
        setLineValue(row, 'side', data.side || (index === 0 ? 'Debit' : 'Credit'));
        setLineValue(row, 'ledger_source', data.ledger_source || 'fixed');
        setLineValue(row, 'ledger_id', data.ledger_id || '');
        setLineValue(row, 'amount_source', data.amount_source || 'transaction_amount');
        setLineValue(row, 'amount_formula', data.amount_formula || '');
        setLineValue(row, 'movement', data.movement || (index === 0 ? 'Increase' : 'Decrease'));
        setLineValue(row, 'allowed_ledger_type', data.allowed_ledger_type || '');
        setLineValue(row, 'explanation', data.explanation || '');

        return row;
    }

    function collectRuleLines() {
        return Array.from(ruleLineBody.querySelectorAll('.rule-line-row')).map((row, index) => ({
            row,
            role: getLineValue(row, 'line_role') || (index === 0 ? 'primary' : 'counter'),
            line_role: getLineValue(row, 'line_role') || (index === 0 ? 'primary' : 'counter'),
            side: getLineValue(row, 'side') || 'Debit',
            ledger_source: getLineValue(row, 'ledger_source') || 'fixed',
            ledger_id: getLineValue(row, 'ledger_id'),
            amount_source: getLineValue(row, 'amount_source') || 'transaction_amount',
            amount_formula: getLineValue(row, 'amount_formula'),
            movement: getLineValue(row, 'movement') || 'Increase',
            allowed_ledger_type: getLineValue(row, 'allowed_ledger_type'),
            explanation: getLineValue(row, 'explanation'),
        }));
    }

    function oppositeSide(side) {
        return side === 'Debit' ? 'Credit' : 'Debit';
    }

    function syncPostingSides(changed = 'primary') {
        if (changed === 'primary') {
            fields.counter_posting_side.value = oppositeSide(fields.primary_posting_side.value);
        } else {
            fields.primary_posting_side.value = oppositeSide(fields.counter_posting_side.value);
        }

        if (fields.primary_posting_side.value === 'Debit') {
            debitInput.value = fields.primary_ledger_id.value || '';
            creditInput.value = fields.fixed_counter_ledger_id.value || '';
        } else {
            debitInput.value = fields.fixed_counter_ledger_id.value || '';
            creditInput.value = fields.primary_ledger_id.value || '';
        }
    }

    function rebuildRuleLines() {
        ruleLineBody.innerHTML = '';
        syncPostingSides(lastPostingSideChanged);

        createRuleLine({
            line_role: 'primary',
            side: fields.primary_posting_side.value || 'Debit',
            ledger_source: normaliseLedgerSource(fields.primary_ledger_source.value),
            ledger_id: fields.primary_ledger_id.value || '',
            amount_source: 'transaction_amount',
            movement: fields.primary_ledger_movement.value || 'Increase',
            allowed_ledger_type: '',
            explanation: fields.primary_explanation.value || '',
        }, 0);

        createRuleLine({
            line_role: 'counter',
            side: fields.counter_posting_side.value || oppositeSide(fields.primary_posting_side.value),
            ledger_source: normaliseLedgerSource(fields.counter_ledger_source.value),
            ledger_id: fields.fixed_counter_ledger_id.value || '',
            amount_source: 'transaction_amount',
            movement: fields.counter_ledger_movement.value || 'Decrease',
            allowed_ledger_type: fields.allowed_counter_ledger_type.value || '',
            explanation: fields.counter_explanation.value || '',
        }, 1);
    }

    function syncTransactionHeadDefaults() {
        const option = selectedOption(fields.transaction_head_id);
        if (!option) return;

        fields.transaction_screen.value = option.dataset.screen || fields.transaction_screen.value || 'Transaction Entry';

        const paymentRequired = option.dataset.paymentRequired === '1';
        fields.payment_method_required.value = paymentRequired ? '1' : fields.payment_method_required.value;
        fields.cash_bank_ledger_required.value = paymentRequired ? '1' : fields.cash_bank_ledger_required.value;
        fields.party_required_mode.value = option.dataset.partyMode === 'Required' ? 'Yes' : (option.dataset.partyMode || fields.party_required_mode.value || 'No');
    }

    function syncSettlementFilter() {
        const selectedHeadOption = selectedOption(fields.transaction_head_id);
        let allowed = [];
        try { allowed = JSON.parse(selectedHeadOption?.dataset.settlements || '[]').map(String); } catch (error) { allowed = []; }

        const options = Array.from(fields.settlement_type_id.options).filter((option) => option.value);
        options.forEach((option) => {
            option.hidden = allowed.length > 0 && !allowed.includes(String(option.value));
        });

        const selected = selectedOption(fields.settlement_type_id);
        if (fields.settlement_type_id.value && selected?.hidden) {
            fields.settlement_type_id.value = '';
        }

        if (!fields.settlement_type_id.value) {
            const firstAllowed = options.find((option) => !option.hidden) || options[0];
            if (firstAllowed) fields.settlement_type_id.value = firstAllowed.value;
        }
    }

    function syncAllowedPaymentFromSettlement() {
        const settlement = `${selectedOption(fields.settlement_type_id)?.dataset.code || ''} ${optionLabel(fields.settlement_type_id)}`.toUpperCase();

        if (settlement.includes('CASH') && settlement.includes('BANK')) {
            fields.allowed_payment_method.value = 'Cash, Bank';
            fields.payment_method_required.value = '1';
            fields.cash_bank_ledger_required.value = '1';
            return;
        }

        if (settlement.includes('CASH')) {
            fields.allowed_payment_method.value = 'Cash';
            fields.payment_method_required.value = '1';
            fields.cash_bank_ledger_required.value = '1';
            return;
        }

        if (settlement.includes('BANK')) {
            fields.allowed_payment_method.value = 'Bank';
            fields.payment_method_required.value = '1';
            fields.cash_bank_ledger_required.value = '1';
            return;
        }
    }

    function syncLedgerNames() {
        const primary = selectedOption(fields.primary_ledger_id);
        fields.primaryLedgerName.value = primary ? `${primary.dataset.code || ''} - ${primary.dataset.name || primary.textContent}`.replace(/^ - /, '') : '';
    }

    function inferPartyEffect() {
        const partyMode = fields.party_required_mode.value;
        if (partyMode === 'No') return 'No Effect';
        const primaryType = selectedOption(fields.primary_ledger_id)?.dataset.type || '';
        const counterType = selectedOption(fields.fixed_counter_ledger_id)?.dataset.type || '';
        if (primaryType === 'Asset' || counterType === 'Asset') return 'Increase Receivable';
        if (primaryType === 'Liability' || counterType === 'Liability') return 'Increase Liability';
        return 'No Effect';
    }

    function syncDescription() {
        const explanation = [fields.primary_explanation.value, fields.counter_explanation.value].filter(Boolean).join(' ');
        ruleDescription.value = explanation;
        partyLedgerEffect.value = inferPartyEffect();
    }

    function renderJournalPreview() {
        syncSettlementFilter();
        syncLedgerNames();
        rebuildRuleLines();
        syncDescription();

        const amount = Number(fields.testAmount.value || 0);
        const lines = collectRuleLines();

        if (amount <= 0 || !fields.primary_ledger_id.value || !fields.fixed_counter_ledger_id.value) {
            journalPreview.className = 'journal-preview-body empty';
            journalPreview.innerHTML = 'Select one primary ledger, one counter ledger, and a positive amount to preview.';
            return;
        }

        let totalDebit = 0;
        let totalCredit = 0;

        const html = lines.map((line) => {
            const calculatedAmount = amount;
            if (line.side === 'Debit') totalDebit += calculatedAmount;
            if (line.side === 'Credit') totalCredit += calculatedAmount;
            const label = lineLabel(line);
            const source = line.ledger_source.replaceAll('_', ' ');
            const sideClass = line.side === 'Credit' ? 'side credit' : 'side';
            return `<div class="entry-row"><span class="${sideClass}">${line.side}</span><div><div class="entry-ledger">${label}</div><small>${source} • ${line.movement || 'Increase'}${line.explanation ? ' • ' + line.explanation : ''}</small></div><strong class="entry-amount">BDT ${money(calculatedAmount)}</strong></div>`;
        }).join('');

        const balanced = Math.round(totalDebit * 100) === Math.round(totalCredit * 100) && totalDebit > 0 && totalCredit > 0;
        journalPreview.className = 'journal-preview-body';
        journalPreview.innerHTML = `
            ${html}
            <div class="entry-row total-row"><span class="side">Total</span><div><div class="entry-ledger">Debit: BDT ${money(totalDebit)} / Credit: BDT ${money(totalCredit)}</div><small>${balanced ? 'Accounting check passed. Debit equals Credit.' : 'Accounting check failed. Debit and Credit must be equal before this rule can be active.'}</small></div><strong class="entry-amount">${balanced ? 'Balanced' : 'Mismatch'}</strong></div>
        `;
    }

    function resetForm() {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';
        fields.amount_required.value = '1';
        fields.payment_method_required.value = '0';
        fields.allowed_payment_method.value = 'N/A';
        fields.cash_bank_ledger_required.value = '0';
        fields.party_required_mode.value = 'No';
        fields.party_sub_ledger_type.value = 'None';
        fields.primary_ledger_source.value = 'Fixed Ledger';
        fields.primary_ledger_movement.value = 'Increase';
        fields.primary_posting_side.value = 'Debit';
        fields.counter_ledger_source.value = 'Fixed Ledger';
        fields.counter_selection_method.value = 'Fixed by Rule';
        fields.allowed_counter_ledger_type.value = 'N/A';
        fields.counter_ledger_movement.value = 'Decrease';
        fields.counter_posting_side.value = 'Credit';
        fields.rule_status.value = 'Active';
        fields.testAmount.value = '10000';
        fields.transaction_screen.value = '';
        fields.settlement_type_id.value = '';
        renderJournalPreview();
        fields.rule_name.focus();
    }

    function setField(name, value) {
        if (!fields[name]) return;
        fields[name].value = value ?? '';
    }

    function loadForEdit(row) {
        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';

        Object.entries({
            rule_code: 'ruleCode',
            rule_name: 'ruleName',
            transaction_head_id: 'transactionHeadId',
            settlement_type_id: 'settlementTypeId',
            transaction_screen: 'transactionScreen',
            rule_trigger: 'ruleTrigger',
            rule_status: 'ruleStatus',
            amount_required: 'amountRequired',
            payment_method_required: 'paymentMethodRequired',
            allowed_payment_method: 'allowedPaymentMethod',
            cash_bank_ledger_required: 'cashBankLedgerRequired',
            party_required_mode: 'partyRequiredMode',
            party_sub_ledger_type: 'partySubLedgerType',
            other_required_input: 'otherRequiredInput',
            primary_ledger_source: 'primaryLedgerSource',
            primary_ledger_id: 'primaryLedgerId',
            primary_ledger_movement: 'primaryLedgerMovement',
            primary_posting_side: 'primaryPostingSide',
            primary_explanation: 'primaryExplanation',
            counter_ledger_source: 'counterLedgerSource',
            counter_selection_method: 'counterSelectionMethod',
            fixed_counter_ledger_id: 'fixedCounterLedgerId',
            allowed_counter_ledger_type: 'allowedCounterLedgerType',
            counter_ledger_movement: 'counterLedgerMovement',
            counter_posting_side: 'counterPostingSide',
            counter_explanation: 'counterExplanation',
        }).forEach(([field, dataKey]) => setField(field, row.dataset[dataKey] || ''));

        renderJournalPreview();
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        showToast('Accounting rule loaded for editing.');
    }

    fields.transaction_head_id.addEventListener('change', () => {
        syncTransactionHeadDefaults();
        syncSettlementFilter();
        syncAllowedPaymentFromSettlement();
        renderJournalPreview();
    });

    fields.primary_posting_side.addEventListener('change', () => {
        lastPostingSideChanged = 'primary';
        renderJournalPreview();
    });
    fields.counter_posting_side.addEventListener('change', () => {
        lastPostingSideChanged = 'counter';
        renderJournalPreview();
    });

    fields.counter_ledger_source.addEventListener('change', () => {
        if (fields.counter_ledger_source.value === 'User Selected Cash/Bank Ledger') {
            fields.counter_selection_method.value = 'Selected by User';
            fields.allowed_counter_ledger_type.value = 'Cash/Bank';
        }
        if (fields.counter_ledger_source.value === 'Fixed Ledger') {
            fields.counter_selection_method.value = 'Fixed by Rule';
        }
        renderJournalPreview();
    });

    Object.values(fields).forEach((field) => {
        if (!field || field.tagName === undefined) return;
        field.addEventListener('input', renderJournalPreview);
        field.addEventListener('change', renderJournalPreview);
    });

    form.addEventListener('submit', () => {
        renderJournalPreview();
    }, true);

    document.querySelectorAll('#ruleTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    document.getElementById('addRuleBtn')?.addEventListener('click', () => {
        resetForm();
        showToast('Ready to add a new accounting rule.');
    });
    document.getElementById('clearRuleBtn')?.addEventListener('click', resetForm);
    document.querySelectorAll('[data-scroll-target]').forEach((button) => {
        button.addEventListener('click', () => document.querySelector(button.dataset.scrollTarget)?.scrollIntoView({ behavior: 'smooth' }));
    });

    resetForm();
});
</script>
@endpush
