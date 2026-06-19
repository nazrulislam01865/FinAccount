@php
    $modalRecordId = (int) old('record_id', 0);
    $editingRule = $modalRecordId > 0 ? $rules->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'accounting-rule' || $addOnlyMode;
    $defaultCategory = $transactionCategories->first()?->value ?? '';
    $defaultPartyType = $rulePartyTypes->first()?->value ?? '';
    $defaultDebitSource = $accountingSources->first(fn ($option) => (bool) ($option->metadata['default_debit'] ?? false))?->value
        ?? $accountingSources->first()?->value
        ?? '';
    $defaultCreditSource = $accountingSources->first(fn ($option) => (bool) ($option->metadata['default_credit'] ?? false))?->value
        ?? $accountingSources->skip(1)->first()?->value
        ?? $defaultDebitSource;
    $defaultMoneyRequired = (bool) ($accountingSources->firstWhere('value', $defaultDebitSource)?->metadata['requires_money'] ?? false)
        || (bool) ($accountingSources->firstWhere('value', $defaultCreditSource)?->metadata['requires_money'] ?? false);
    $defaultPartyRequired = (bool) ($accountingSources->firstWhere('value', $defaultDebitSource)?->metadata['requires_party'] ?? false)
        || (bool) ($accountingSources->firstWhere('value', $defaultCreditSource)?->metadata['requires_party'] ?? false);
    $canManage = auth()->user()?->canAccounting('accounting_rules.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('accounting-rules');
    $defaultRuleValues = [
        'record_id' => '',
        'category' => $defaultCategory,
        'party_type' => $defaultPartyType,
        'money_required' => $defaultMoneyRequired ? '1' : '0',
        'party_required' => $defaultPartyRequired ? '1' : '0',
        'generates_invoice' => '0',
        'invoice_title' => 'Sales Invoice',
        'supports_split_transaction' => '0',
        'debit_source' => $defaultDebitSource,
        'credit_source' => $defaultCreditSource,
        'is_active' => '1',
    ];
@endphp

<x-layouts::accounting title="Accounting Rules">
    <div class="hg-page-header">
        <div>
            <h1>Accounting Rules</h1>
        </div>
        @if($canManage)
        <button
            type="button"
            class="hg-btn hg-btn-primary"
            data-setup-open="create"
            data-setup-target="accounting-rule-modal"
            data-defaults="{{ json_encode($defaultRuleValues) }}"
        >+ Add Rule</button>
        @endif
    </div>

    @if ($rules->isEmpty() && $draftRows->isEmpty())
        <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No records found.' }}</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Rule</th>
                    <th>Category</th>
                    <th>Debit / Credit</th>
                    <th>Money?</th>
                    <th>Party?</th>
                    <th>Invoice?</th>
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
                            'party_type' => $rule->party_type,
                            'money_required' => $rule->money_required ? '1' : '0',
                            'party_required' => $rule->party_required ? '1' : '0',
                            'generates_invoice' => $rule->generates_invoice ? '1' : '0',
                            'invoice_title' => $rule->invoice_title ?: 'Sales Invoice',
                            'supports_split_transaction' => $rule->lines->contains(fn ($line) => in_array($line->amount_basis, [\App\Models\AccountingRuleLine::BASIS_PAID, \App\Models\AccountingRuleLine::BASIS_DUE], true)) ? '1' : '0',
                            'debit_source' => $rule->debit_source,
                            'credit_source' => $rule->credit_source,
                            'is_active' => $rule->is_active ? '1' : '0',
                        ];
                    @endphp
                    <tr>
                        <td><strong>{{ $rule->code }}</strong><br>{{ $rule->name }}</td>
                        <td><span class="hg-badge {{ strtolower($rule->category ?? '') }}">{{ $rule->category ? ($categoryLabels[$rule->category] ?? $rule->category) : 'Relationship removed' }}</span></td>
                        <td>
                            <div><strong>Debit</strong> {{ $sourceLabels[$rule->debit_source] ?? $rule->debit_source }}</div>
                            <div><strong>Credit</strong> {{ $sourceLabels[$rule->credit_source] ?? $rule->credit_source }}</div>
                            @if($rule->lines->contains(fn ($line) => in_array($line->amount_basis, [\App\Models\AccountingRuleLine::BASIS_PAID, \App\Models\AccountingRuleLine::BASIS_DUE], true)))
                                <small class="hg-muted">Partial payment supported by this rule.</small>
                            @endif
                        </td>
                        <td>{{ $rule->money_required ? 'Yes' : 'No' }}</td>
                        <td>{{ $rule->party_required ? ($partyTypeLabels[$rule->party_type] ?? $rule->party_type) : 'No' }}</td>
                        <td>
                            @if($rule->generates_invoice)
                                <span class="hg-badge sales">Yes</span><br>
                                <small class="hg-muted">{{ $rule->invoice_title ?: 'Sales Invoice' }}</small>
                            @else
                                <span class="hg-muted">No</span>
                            @endif
                        </td>
                        <td><span class="hg-badge {{ $rule->is_active ? 'on' : 'off' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="hg-actions">
                                @if($canManage)
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small"
                                    data-setup-open="edit"
                                    data-setup-target="accounting-rule-modal"
                                    data-edit-title="Edit Accounting Rule"
                                    data-draft-edit-key="accounting-rules.edit.{{ $rule->id }}"
                                    data-update-url="{{ route('accounting-rules.update', $rule) }}"
                                    data-values="{{ json_encode($editValues) }}"
                                >Edit</button>
                                @endif
                                @if($canDelete)
                                <form method="POST" action="{{ route('accounting-rules.destroy', $rule) }}" data-safe-delete-form>
                                    @csrf
                                    @method('DELETE')
                                    <button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach

                @foreach ($draftRows as $draft)
                    @php
                        $fields = \App\Support\VisibleFormDrafts::fields($draft);
                        $isEditDraft = \App\Support\VisibleFormDrafts::isEdit($draft);
                    @endphp
                    <tr class="hg-table-draft-row">
                        <td><strong>{{ $fields['code'] ?? 'Draft' }}</strong><br>{{ $fields['name'] ?? 'Draft Accounting Rule' }}<br><span class="hg-muted">{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span></td>
                        <td><span class="hg-badge {{ strtolower((string) ($fields['category'] ?? '')) }}">{{ $categoryLabels[$fields['category'] ?? ''] ?? ($fields['category'] ?? '—') }}</span></td>
                        <td><span class="hg-muted">Draft debit/credit setup will appear after saving.</span></td>
                        <td>{{ \App\Support\VisibleFormDrafts::boolField($draft, 'money_required') ? 'Yes' : 'No' }}</td>
                        <td>{{ \App\Support\VisibleFormDrafts::boolField($draft, 'party_required') ? ($partyTypeLabels[$fields['party_type'] ?? ''] ?? ($fields['party_type'] ?? 'Any')) : 'No' }}</td>
                        <td>{{ \App\Support\VisibleFormDrafts::boolField($draft, 'generates_invoice') ? 'Yes' : 'No' }}</td>
                        <td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
                        <td><div class="hg-actions">@if($canManage) @if($isEditDraft)<button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>@else<button type="button" class="hg-btn hg-btn-small" data-setup-open="create" data-setup-target="accounting-rule-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, $defaultRuleValues)) }}">Continue</button>@endif @endif<form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($canManage)

    <x-accounting.setup-modal
        id="accounting-rule-modal"
        :show="$reopenModal"
        :title="$editingRule ? 'Edit Accounting Rule' : 'Add Accounting Rule'"
        :store-url="route('accounting-rules.store')"
        create-title="Add Accounting Rule"
    >
        <form method="POST" action="{{ $editingRule ? route('accounting-rules.update', $editingRule) : route('accounting-rules.store') }}" class="hg-form-grid" data-setup-form data-accounting-rule-form
            data-draft-form
            data-draft-defer
            data-draft-key-base="accounting-rules"
            data-draft-key="{{ $editingRule ? 'accounting-rules.edit.'.$editingRule->id : 'accounting-rules.create' }}"
            data-draft-title="Accounting Rule">
            @csrf
            <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingRule)>
            <input type="hidden" name="setup_modal" value="accounting-rule">
            <input type="hidden" name="record_id" value="{{ old('record_id') }}">

            <div class="hg-field">
                <label for="rule-code">Code <span class="hg-required">*</span></label>
                <input id="rule-code" name="code" value="{{ old('code', $editingRule?->code) }}" required>
                @error('code')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="rule-name">Name <span class="hg-required">*</span></label>
                <input id="rule-name" name="name" value="{{ old('name', $editingRule?->name) }}" required>
                @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="rule-category">Category</label>
                <select id="rule-category" name="category" required>
                    @foreach ($transactionCategories as $categoryOption)
                        <option value="{{ $categoryOption->value }}" @selected(old('category', $editingRule?->category ?? $defaultCategory) === $categoryOption->value)>{{ $categoryOption->label }}</option>
                    @endforeach
                </select>
                @error('category')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="rule-party-type">Party Type</label>
                <select id="rule-party-type" name="party_type" required>
                    @foreach ($rulePartyTypes as $partyTypeOption)
                        <option value="{{ $partyTypeOption->value }}" @selected(old('party_type', $editingRule?->party_type ?? $defaultPartyType) === $partyTypeOption->value)>{{ $partyTypeOption->label }}</option>
                    @endforeach
                </select>
                @error('party_type')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="hg-field">
                <label for="rule-debit-source">Debit Source</label>
                <select id="rule-debit-source" name="debit_source" required data-rule-source>
                    @foreach ($accountingSources as $sourceOption)
                        <option
                            value="{{ $sourceOption->value }}"
                            data-requires-money="{{ (bool) ($sourceOption->metadata['requires_money'] ?? false) ? '1' : '0' }}"
                            data-requires-party="{{ (bool) ($sourceOption->metadata['requires_party'] ?? false) ? '1' : '0' }}"
                            @selected(old('debit_source', $editingRule?->debit_source ?? $defaultDebitSource) === $sourceOption->value)
                        >{{ $sourceOption->label }}</option>
                    @endforeach
                </select>
                @error('debit_source')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="rule-credit-source">Credit Source</label>
                <select id="rule-credit-source" name="credit_source" required data-rule-source>
                    @foreach ($accountingSources as $sourceOption)
                        <option
                            value="{{ $sourceOption->value }}"
                            data-requires-money="{{ (bool) ($sourceOption->metadata['requires_money'] ?? false) ? '1' : '0' }}"
                            data-requires-party="{{ (bool) ($sourceOption->metadata['requires_party'] ?? false) ? '1' : '0' }}"
                            @selected(old('credit_source', $editingRule?->credit_source ?? $defaultCreditSource) === $sourceOption->value)
                        >{{ $sourceOption->label }}</option>
                    @endforeach
                </select>
                @error('credit_source')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="hg-field">
                <input type="hidden" name="supports_split_transaction" value="0">
                <label class="hg-checkbox-label" for="rule-supports-split">
                    <input id="rule-supports-split" type="checkbox" name="supports_split_transaction" value="1" @checked(old('supports_split_transaction', $editingRule ? $editingRule->lines->contains(fn ($line) => in_array($line->amount_basis, [\App\Models\AccountingRuleLine::BASIS_PAID, \App\Models\AccountingRuleLine::BASIS_DUE], true)) : false))>
                    Allow partial payment / due split
                </label>
                <small class="hg-field-help">For sales: paid amount goes to money account and due amount goes to customer receivable. For purchase/expense: paid amount goes from money account and due amount goes to supplier payable.</small>
                @error('supports_split_transaction')<small class="hg-field-error">{{ $message }}</small>@enderror
                @error('lines')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field" data-invoice-field>
                <input type="hidden" name="generates_invoice" value="0">
                <label class="hg-checkbox-label" for="rule-generates-invoice">
                    <input id="rule-generates-invoice" type="checkbox" name="generates_invoice" value="1" @checked(old('generates_invoice', $editingRule?->generates_invoice ?? false))>
                    Generate sales invoice
                </label>
                <small class="hg-field-help">Enable for sales rules that should create and download invoice after posting.</small>
                @error('generates_invoice')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field" data-invoice-field>
                <label for="rule-invoice-title">Invoice Title</label>
                <input id="rule-invoice-title" name="invoice_title" value="{{ old('invoice_title', $editingRule?->invoice_title ?? 'Sales Invoice') }}" maxlength="120" placeholder="Sales Invoice">
                @error('invoice_title')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="hg-field">
                <input type="hidden" name="money_required" value="0">
                <label class="hg-checkbox-label" for="rule-money-required">
                    <input id="rule-money-required" type="checkbox" name="money_required" value="1" @checked(old('money_required', $editingRule?->money_required ?? false))>
                    Money account required
                </label>
            </div>
            <div class="hg-field">
                <input type="hidden" name="party_required" value="0">
                <label class="hg-checkbox-label" for="rule-party-required">
                    <input id="rule-party-required" type="checkbox" name="party_required" value="1" @checked(old('party_required', $editingRule?->party_required ?? false))>
                    Party required
                </label>
            </div>
            <div class="hg-field full">
                <input type="hidden" name="is_active" value="0">
                <label class="hg-checkbox-label" for="rule-active">
                    <input id="rule-active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingRule?->is_active ?? true))>
                    Active
                </label>
            </div>
            <div class="hg-field full"><x-accounting.form-actions submit-label="Save Accounting Rule" /></div>
        </form>
    </x-accounting.setup-modal>

    @endif
</x-layouts::accounting>
