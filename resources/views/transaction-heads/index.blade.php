@php
    $modalRecordId = (int) old('record_id', 0);
    $editingHead = $modalRecordId > 0 ? $transactionHeads->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'transaction-head' || $addOnlyMode;
    $defaultCategory = $transactionCategories->first()?->value ?? '';
    $canManage = auth()->user()?->canAccounting('transaction_heads.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('transaction-heads');
@endphp

<x-layouts::accounting title="Transaction Heads">
    <div class="hg-page-header">
        <div>
            <h1>Transaction Heads</h1>
        </div>
        @if($canManage)
        <button
            type="button"
            class="hg-btn hg-btn-primary"
            data-setup-open="create"
            data-setup-target="transaction-head-modal"
            data-defaults="{{ json_encode(['record_id' => '', 'category' => $defaultCategory, 'is_active' => '1']) }}"
        >+ Add Transaction Head</button>
        @endif
    </div>

    @if ($transactionHeads->isEmpty() && $draftRows->isEmpty())
        <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No records found.' }}</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Head</th>
                    <th>Category</th>
                    <th>Linked Rule</th>
                    <th>Posting COA</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($transactionHeads as $head)
                    <tr>
                        <td><strong>{{ $head->code }}</strong><br>{{ $head->name }}</td>
                        <td><span class="hg-badge {{ strtolower($head->category ?? '') }}">{{ $head->category ? ($categoryLabels[$head->category] ?? $head->category) : 'Relationship removed' }}</span></td>
                        <td>{{ $head->accountingRule?->name ?? 'Relationship removed' }}</td>
                        <td>{{ $head->postingAccount ? ($head->postingAccount->code.' — '.$head->postingAccount->name) : 'Relationship removed' }}</td>
                        <td><span class="hg-badge {{ $head->is_active ? 'on' : 'off' }}">{{ $head->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="hg-actions">
                                @if($canManage)
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small"
                                    data-setup-open="edit"
                                    data-setup-target="transaction-head-modal"
                                    data-edit-title="Edit Transaction Head"
                                    data-draft-edit-key="transaction-heads.edit.{{ $head->id }}"
                                    data-update-url="{{ route('transaction-heads.update', $head) }}"
                                    data-values="{{ json_encode([
                                        'record_id' => $head->id,
                                        'code' => $head->code,
                                        'name' => $head->name,
                                        'category' => $head->category,
                                        'accounting_rule_id' => $head->accounting_rule_id,
                                        'posting_account_id' => $head->posting_account_id,
                                        'is_active' => $head->is_active ? '1' : '0',
                                    ]) }}"
                                >Edit</button>
                                @endif
                                @if($canDelete)
                                <form method="POST" action="{{ route('transaction-heads.destroy', $head) }}" data-safe-delete-form>
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
                        <td><strong>{{ $fields['code'] ?? 'Draft' }}</strong><br>{{ $fields['name'] ?? 'Draft Transaction Head' }}<br><span class="hg-muted">{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span></td>
                        <td><span class="hg-badge {{ strtolower((string) ($fields['category'] ?? '')) }}">{{ $categoryLabels[$fields['category'] ?? ''] ?? ($fields['category'] ?? '—') }}</span></td>
                        <td>{{ filled($fields['accounting_rule_id'] ?? null) ? 'Rule ID #'.$fields['accounting_rule_id'] : 'Not selected' }}</td>
                        <td>{{ filled($fields['posting_account_id'] ?? null) ? 'COA ID #'.$fields['posting_account_id'] : 'Not selected' }}</td>
                        <td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
                        <td><div class="hg-actions">@if($canManage) @if($isEditDraft)<button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>@else<button type="button" class="hg-btn hg-btn-small" data-setup-open="create" data-setup-target="transaction-head-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, ['record_id'=>'','category'=>$defaultCategory,'is_active'=>'1'])) }}">Continue</button>@endif @endif<form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($canManage)

    <x-accounting.setup-modal
        id="transaction-head-modal"
        :show="$reopenModal"
        :title="$editingHead ? 'Edit Transaction Head' : 'Add Transaction Head'"
        :store-url="route('transaction-heads.store')"
        create-title="Add Transaction Head"
    >
        <form method="POST" action="{{ $editingHead ? route('transaction-heads.update', $editingHead) : route('transaction-heads.store') }}" class="hg-form-grid" data-setup-form
            data-draft-form
            data-draft-defer
            data-draft-key-base="transaction-heads"
            data-draft-key="{{ $editingHead ? 'transaction-heads.edit.'.$editingHead->id : 'transaction-heads.create' }}"
            data-draft-title="Transaction Head">
            @csrf
            <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingHead)>
            <input type="hidden" name="setup_modal" value="transaction-head">
            <input type="hidden" name="record_id" value="{{ old('record_id') }}">

            <div class="hg-field">
                <label for="head-code">Code <span class="hg-required">*</span></label>
                <input id="head-code" name="code" value="{{ old('code', $editingHead?->code) }}" required>
                @error('code')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="head-name">Name <span class="hg-required">*</span></label>
                <input id="head-name" name="name" value="{{ old('name', $editingHead?->name) }}" required>
                @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="head-category">Category</label>
                <select id="head-category" name="category" required>
                    @foreach ($transactionCategories as $categoryOption)
                        <option value="{{ $categoryOption->value }}" @selected(old('category', $editingHead?->category ?? $defaultCategory) === $categoryOption->value)>{{ $categoryOption->label }}</option>
                    @endforeach
                </select>
                @error('category')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="head-rule">Accounting Rule <span class="hg-required">*</span></label>
                <select id="head-rule" name="accounting_rule_id" required>
                    <option value="">Select rule</option>
                    @foreach ($accountingRules as $rule)
                        <option value="{{ $rule->id }}" @selected((string) old('accounting_rule_id', $editingHead?->accounting_rule_id) === (string) $rule->id)>
                            {{ $rule->code }} — {{ $rule->name }} ({{ $categoryLabels[$rule->category] ?? $rule->category }})
                        </option>
                    @endforeach
                </select>
                @error('accounting_rule_id')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field full">
                <label for="head-posting">Posting COA <span class="hg-required">*</span></label>
                <select id="head-posting" name="posting_account_id" required>
                    <option value="">Select COA</option>
                    @foreach ($postingAccounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('posting_account_id', $editingHead?->posting_account_id) === (string) $account->id)>
                            {{ $account->code }} — {{ $account->name }} ({{ $account->type }})
                        </option>
                    @endforeach
                </select>
                @error('posting_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field full">
                <input type="hidden" name="is_active" value="0">
                <label class="hg-checkbox-label" for="head-active">
                    <input id="head-active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingHead?->is_active ?? true))>
                    Active
                </label>
            </div>
            <div class="hg-field full"><x-accounting.form-actions submit-label="Save Transaction Head" /></div>
        </form>
    </x-accounting.setup-modal>

    @endif
</x-layouts::accounting>
