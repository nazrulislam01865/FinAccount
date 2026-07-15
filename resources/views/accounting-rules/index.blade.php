@php
    $modalRecordId = (int) old('record_id', 0);
    $editingRule = $modalRecordId > 0 ? $rules->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'accounting-rule' || $addOnlyMode;
    $defaultCategory = $transactionCategories->first()?->value ?? '';
    $defaultSettlement = collect($transactionTypeDefinitions[$defaultCategory]['allowed_settlements'] ?? [])->first()
        ?? $settlementTypes->first()?->value
        ?? '';
    $canManage = auth()->user()?->canAccounting('accounting_rules.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('accounting-rules');
    $defaultRuleValues = [
        'record_id' => '',
        'category' => $defaultCategory,
        'transaction_head_id' => '',
        'settlement_type' => $defaultSettlement,
        'is_active' => '1',
    ];
@endphp

<x-layouts::accounting title="Accounting Rules">
    <div class="hg-page-header">
        <div>
            <h1>Accounting Rules</h1>
            <p class="hg-muted">The system applies a head-specific rule first, then falls back to the transaction-type rule for the same payment type.</p>
        </div>
        <div class="hg-actions">
            @if(! $addOnlyMode)
                <a class="hg-btn" href="{{ route('accounting-rules.export') }}">Export Excel</a>
            @endif
            @if($canManage)
                <button type="button" class="hg-btn hg-btn-primary" data-setup-open="create" data-setup-target="accounting-rule-modal" data-defaults="{{ json_encode($defaultRuleValues) }}">+ Add Accounting Rule</button>
            @endif
        </div>
    </div>

    @if($canManage)
        <form
            id="accounting-rule-bulk-form"
            method="POST"
            action="{{ route('accounting-rules.bulk-action') }}"
            data-bulk-action-form
            data-bulk-group="accounting-rules"
            data-bulk-entity="Accounting Rule"
            data-safe-delete-form
            data-safe-delete-when-action="delete"
        >
            @csrf
        </form>
        <div class="hg-toolbar hg-bulk-toolbar hg-bulk-action-toolbar" data-bulk-toolbar="accounting-rules" hidden>
            <select
                class="hg-filter-select"
                name="bulk_action"
                form="accounting-rule-bulk-form"
                data-bulk-action-select="accounting-rules"
                aria-label="Choose Accounting Rule bulk action"
            >
                <option value="">Choose bulk action</option>
                <option value="activate">Set Active</option>
                <option value="deactivate">Set Inactive</option>
                @if($canDelete)<option value="delete">Delete Permanently</option>@endif
            </select>
            <button
                type="submit"
                class="hg-btn hg-btn-primary"
                form="accounting-rule-bulk-form"
                data-bulk-apply="accounting-rules"
                disabled
            >Apply</button>
            <button type="button" class="hg-btn" data-bulk-clear="accounting-rules">Clear Selection</button>
            <span class="hg-muted" data-bulk-count="accounting-rules">0 selected</span>
            <small class="hg-muted">Inactive rules are ignored during automatic posting. Delete permanently uses safe dependency checking.</small>
        </div>
    @endif

    @if ($rules->isEmpty() && $draftRows->isEmpty())
        <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No accounting rules found.' }}</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    @if($canManage)<th class="hg-checkbox-col"><input type="checkbox" data-bulk-master="accounting-rules" aria-label="Select all accounting rules"></th>@endif
                    <th>Accounting Rule</th>
                    <th>Transaction Type</th>
                    <th>Rule Scope</th>
                    <th>Payment Type</th>
                    <th>Automatic Posting</th>
                    <th>Required Information</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rules as $rule)
                    @php
                        $editValues = [
                            'record_id' => $rule->id,
                            'code' => $rule->code,
                            'name' => $rule->name,
                            'category' => $rule->category,
                            'transaction_head_id' => $rule->transaction_head_id,
                            'settlement_type' => $rule->settlement_type,
                            'is_active' => $rule->is_active ? '1' : '0',
                        ];
                    @endphp
                    <tr>
                        @if($canManage)
                            <td class="hg-checkbox-col">
                                <input
                                    type="checkbox"
                                    name="record_ids[]"
                                    value="{{ $rule->id }}"
                                    form="accounting-rule-bulk-form"
                                    data-bulk-checkbox="accounting-rules"
                                    aria-label="Select {{ $rule->code }} — {{ $rule->name }}"
                                >
                            </td>
                        @endif
                        <td><strong>{{ $rule->code }}</strong><br>{{ $rule->name }}</td>
                        <td>{{ $categoryLabels[$rule->category] ?? $rule->category }}</td>
                        <td>
                            @if($rule->transactionHead)
                                <strong>{{ $rule->transactionHead->name }}</strong><br><small class="hg-muted">Transaction Head</small>
                            @else
                                <strong>All {{ $categoryLabels[$rule->category] ?? $rule->category }} Heads</strong><br><small class="hg-muted">Fallback rule</small>
                            @endif
                        </td>
                        <td>{{ $settlementLabels[$rule->settlement_type] ?? $rule->settlement_type }}</td>
                        <td>
                            @foreach($rule->lines as $line)
                                <div>
                                    <strong>{{ $line->line_side === 'debit' ? 'Value goes to' : 'Value comes from' }}:</strong>
                                    {{ $sourceLabels[$line->account_source] ?? $line->account_source }}
                                    @if($line->amount_basis !== \App\Models\AccountingRuleLine::BASIS_TOTAL)
                                        <small class="hg-muted">({{ $amountBasisLabels[$line->amount_basis] ?? $line->amount_basis }})</small>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                        <td>
                            @php($requirements = collect([$rule->money_required ? 'Cash / Bank / Mobile Account' : null, $rule->party_required ? ($rule->party_type ?: 'Party') : null])->filter())
                            {{ $requirements->isEmpty() ? 'No additional selection' : $requirements->join(', ') }}
                        </td>
                        <td><span class="hg-badge {{ $rule->is_active ? 'on' : 'off' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="hg-actions">
                                @if($canManage)
                                    <button type="button" class="hg-btn hg-btn-small" data-setup-open="edit" data-setup-target="accounting-rule-modal" data-edit-title="Edit Accounting Rule" data-draft-edit-key="accounting-rules.edit.{{ $rule->id }}" data-update-url="{{ route('accounting-rules.update', $rule) }}" data-values="{{ json_encode($editValues) }}">Edit</button>
                                @endif
                                @if($canDelete)
                                    <form method="POST" action="{{ route('accounting-rules.destroy', $rule) }}" data-safe-delete-form>@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach

                @foreach ($draftRows as $draft)
                    @php($fields = \App\Support\VisibleFormDrafts::fields($draft))
                    @php($isEditDraft = \App\Support\VisibleFormDrafts::isEdit($draft))
                    <tr class="hg-table-draft-row">
                        @if($canManage)<td class="hg-checkbox-col"><span class="hg-muted">—</span></td>@endif
                        <td><strong>{{ $fields['code'] ?? 'Draft' }}</strong><br>{{ $fields['name'] ?? 'Draft Accounting Rule' }}</td>
                        <td>{{ $categoryLabels[$fields['category'] ?? ''] ?? ($fields['category'] ?? '—') }}</td>
                        <td>{{ filled($fields['transaction_head_id'] ?? null) ? 'Selected Transaction Head' : 'All Heads' }}</td>
                        <td>{{ $settlementLabels[$fields['settlement_type'] ?? ''] ?? ($fields['settlement_type'] ?? '—') }}</td>
                        <td colspan="2"><span class="hg-muted">Automatic posting will be generated when saved.</span></td>
                        <td><span class="hg-badge draft">Draft</span></td>
                        <td><div class="hg-actions">@if($canManage) @if($isEditDraft)<button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>@else<button type="button" class="hg-btn hg-btn-small" data-setup-open="create" data-setup-target="accounting-rule-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, $defaultRuleValues)) }}">Continue</button>@endif @endif<form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($canManage)
        <x-accounting.setup-modal id="accounting-rule-modal" :show="$reopenModal" :title="$editingRule ? 'Edit Accounting Rule' : 'Add Accounting Rule'" :store-url="route('accounting-rules.store')" create-title="Add Accounting Rule">
            <form method="POST" action="{{ $editingRule ? route('accounting-rules.update', $editingRule) : route('accounting-rules.store') }}" class="hg-form-grid" data-setup-form data-accounting-rule-form data-draft-form data-draft-defer data-draft-key-base="accounting-rules" data-draft-key="{{ $editingRule ? 'accounting-rules.edit.'.$editingRule->id : 'accounting-rules.create' }}" data-draft-title="Accounting Rule">
                @csrf
                <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingRule)>
                <input type="hidden" name="setup_modal" value="accounting-rule">
                <input type="hidden" name="record_id" value="{{ old('record_id') }}">

                <input type="hidden" id="rule-code" name="code" value="{{ old('code', $editingRule?->code) }}">
                <div class="hg-field">
                    <label for="rule-name">Name <span class="hg-required">*</span></label>
                    <input id="rule-name" name="name" value="{{ old('name', $editingRule?->name) }}" required maxlength="120">
                    @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>
                <div class="hg-field">
                    <label for="rule-category">Transaction Type <span class="hg-required">*</span></label>
                    <select id="rule-category" name="category" required data-rule-transaction-type>
                        @foreach ($transactionCategories as $categoryOption)
                            <option value="{{ $categoryOption->value }}" data-allowed-settlements="{{ json_encode($transactionTypeDefinitions[$categoryOption->value]['allowed_settlements'] ?? \App\Support\TransactionTypes::ALL_SETTLEMENTS) }}" @selected(old('category', $editingRule?->category ?? $defaultCategory) === $categoryOption->value)>{{ $categoryOption->label }}</option>
                        @endforeach
                    </select>
                    @error('category')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>
                <div class="hg-field">
                    <label for="rule-head">Transaction Head Scope</label>
                    <select id="rule-head" name="transaction_head_id" data-rule-transaction-head>
                        <option value="">All heads of this transaction type</option>
                        @foreach ($transactionHeads as $transactionHead)
                            <option
                                value="{{ $transactionHead->id }}"
                                data-head-category="{{ $transactionHead->category }}"
                                @selected((string) old('transaction_head_id', $editingRule?->transaction_head_id) === (string) $transactionHead->id)
                            >{{ $transactionHead->name }} ({{ $transactionHead->code }})</option>
                        @endforeach
                    </select>
                    <small class="hg-muted">Choose a head to override the general rule only for that head. Feed Purchase and Feed Sale use this scope.</small>
                    @error('transaction_head_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>
                <div class="hg-field">
                    <label for="rule-settlement">Payment Type <span class="hg-required">*</span></label>
                    <select id="rule-settlement" name="settlement_type" required data-rule-settlement-type>
                        @foreach ($settlementTypes as $settlementOption)
                            <option value="{{ $settlementOption->value }}" @selected(old('settlement_type', $editingRule?->settlement_type ?? $defaultSettlement) === $settlementOption->value)>{{ $settlementOption->label }}</option>
                        @endforeach
                    </select>
                    @error('settlement_type')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>
                <div class="hg-field full">
                    <input type="hidden" name="is_active" value="0">
                    <label class="hg-checkbox-label" for="rule-active"><input id="rule-active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingRule?->is_active ?? true))> Active</label>
                </div>
                <div class="hg-field full"><x-accounting.form-actions submit-label="Save Accounting Rule" /></div>
            </form>
        </x-accounting.setup-modal>
    @endif
</x-layouts::accounting>
