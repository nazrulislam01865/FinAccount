@php
    $modalRecordId = (int) old('record_id', 0);
    $editingHead = $modalRecordId > 0 ? $transactionHeads->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'transaction-head' || $addOnlyMode;
    $defaultCategory = $transactionCategories->first()?->value ?? '';
    $defaultSettlements = $transactionTypeDefinitions[$defaultCategory]['default_settlements'] ?? [\App\Support\TransactionTypes::CASH];
    $defaultPartyType = $transactionTypeDefinitions[$defaultCategory]['party_type'] ?? 'Any';
    $canManage = auth()->user()?->canAccounting('transaction_heads.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('transaction-heads');
    $defaultHeadValues = [
        'record_id' => '',
        'category' => $defaultCategory,
        'allowed_settlements' => $defaultSettlements,
        'party_type' => $defaultPartyType,
        'is_active' => '1',
    ];
@endphp

<x-layouts::accounting title="Transaction Heads">
    <div class="hg-page-header">
        <div>
            <h1>Transaction Heads</h1>
            <p class="hg-muted">A head only defines what happened and the account where it is recorded. Accounting rules are selected automatically.</p>
        </div>
        <div class="hg-actions">
            @if(! $addOnlyMode)
                <a class="hg-btn" href="{{ route('transaction-heads.export') }}">Export Excel</a>
            @endif
            @if($canManage)
                <button type="button" class="hg-btn hg-btn-primary" data-setup-open="create" data-setup-target="transaction-head-modal" data-defaults="{{ json_encode($defaultHeadValues) }}">+ Add Transaction Head</button>
            @endif
        </div>
    </div>

    @if($canManage)
        <form
            id="transaction-head-bulk-form"
            method="POST"
            action="{{ route('transaction-heads.bulk-action') }}"
            data-bulk-action-form
            data-bulk-group="transaction-heads"
            data-bulk-entity="Transaction Head"
            data-safe-delete-form
            data-safe-delete-when-action="delete"
        >
            @csrf
        </form>
        <div class="hg-toolbar hg-bulk-toolbar hg-bulk-action-toolbar" data-bulk-toolbar="transaction-heads" hidden>
            <select
                class="hg-filter-select"
                name="bulk_action"
                form="transaction-head-bulk-form"
                data-bulk-action-select="transaction-heads"
                aria-label="Choose Transaction Head bulk action"
            >
                <option value="">Choose bulk action</option>
                <option value="activate">Set Active</option>
                <option value="deactivate">Set Inactive</option>
                @if($canDelete)<option value="delete">Delete Permanently</option>@endif
            </select>
            <button
                type="submit"
                class="hg-btn hg-btn-primary"
                form="transaction-head-bulk-form"
                data-bulk-apply="transaction-heads"
                disabled
            >Apply</button>
            <button type="button" class="hg-btn" data-bulk-clear="transaction-heads">Clear Selection</button>
            <span class="hg-muted" data-bulk-count="transaction-heads">0 selected</span>
            <small class="hg-muted">Inactive heads are hidden from new transaction entry. Delete permanently uses safe dependency checking.</small>
        </div>
    @endif

    @if ($transactionHeads->isEmpty() && $draftRows->isEmpty())
        <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No transaction heads found.' }}</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    @if($canManage)<th class="hg-checkbox-col"><input type="checkbox" data-bulk-master="transaction-heads" aria-label="Select all transaction heads"></th>@endif
                    <th>Head</th>
                    <th>Transaction Type</th>
                    <th>Linked Account</th>
                    <th>Allowed Payment Types</th>
                    <th>Party Type</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($transactionHeads as $head)
                    @php
                        $editValues = [
                            'record_id' => $head->id,
                            'code' => $head->code,
                            'name' => $head->name,
                            'category' => $head->category,
                            'posting_account_id' => $head->posting_account_id,
                            'allowed_settlements' => $head->allowedSettlementCodes(),
                            'party_type' => $head->party_type ?: ($transactionTypeDefinitions[$head->category]['party_type'] ?? 'Any'),
                            'is_active' => $head->is_active ? '1' : '0',
                        ];
                    @endphp
                    <tr>
                        @if($canManage)
                            <td class="hg-checkbox-col">
                                <input
                                    type="checkbox"
                                    name="record_ids[]"
                                    value="{{ $head->id }}"
                                    form="transaction-head-bulk-form"
                                    data-bulk-checkbox="transaction-heads"
                                    aria-label="Select {{ $head->code }} — {{ $head->name }}"
                                >
                            </td>
                        @endif
                        <td><strong>{{ $head->code }}</strong><br>{{ $head->name }}</td>
                        <td>{{ $categoryLabels[$head->category] ?? $head->category }}</td>
                        <td>{{ $head->postingAccount ? ($head->postingAccount->code.' — '.$head->postingAccount->name) : 'Not linked' }}</td>
                        <td>{{ collect($head->allowedSettlementCodes())->map(fn($value) => $settlementLabels[$value] ?? $value)->join(', ') ?: 'Not configured' }}</td>
                        <td>{{ $partyTypeLabels[$head->party_type] ?? ($head->party_type ?: 'Any') }}</td>
                        <td><span class="hg-badge {{ $head->is_active ? 'on' : 'off' }}">{{ $head->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="hg-actions">
                                @if($canManage)
                                    <button type="button" class="hg-btn hg-btn-small" data-setup-open="edit" data-setup-target="transaction-head-modal" data-edit-title="Edit Transaction Head" data-draft-edit-key="transaction-heads.edit.{{ $head->id }}" data-update-url="{{ route('transaction-heads.update', $head) }}" data-values="{{ json_encode($editValues) }}">Edit</button>
                                @endif
                                @if($canDelete)
                                    <form method="POST" action="{{ route('transaction-heads.destroy', $head) }}" data-safe-delete-form>@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button></form>
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
                        <td><strong>{{ $fields['code'] ?? 'Draft' }}</strong><br>{{ $fields['name'] ?? 'Draft Transaction Head' }}</td>
                        <td>{{ $categoryLabels[$fields['category'] ?? ''] ?? ($fields['category'] ?? '—') }}</td>
                        <td>{{ filled($fields['posting_account_id'] ?? null) ? 'COA ID #'.$fields['posting_account_id'] : 'Not selected' }}</td>
                        <td>{{ collect((array) ($fields['allowed_settlements'] ?? []))->map(fn($value) => $settlementLabels[$value] ?? $value)->join(', ') ?: 'Not selected' }}</td>
                        <td>{{ $partyTypeLabels[$fields['party_type'] ?? ''] ?? ($fields['party_type'] ?? 'Any') }}</td>
                        <td><span class="hg-badge draft">Draft</span></td>
                        <td><div class="hg-actions">@if($canManage) @if($isEditDraft)<button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>@else<button type="button" class="hg-btn hg-btn-small" data-setup-open="create" data-setup-target="transaction-head-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, $defaultHeadValues)) }}">Continue</button>@endif @endif<form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($canManage)
        <x-accounting.setup-modal id="transaction-head-modal" :show="$reopenModal" :title="$editingHead ? 'Edit Transaction Head' : 'Add Transaction Head'" :store-url="route('transaction-heads.store')" create-title="Add Transaction Head">
            <form method="POST" action="{{ $editingHead ? route('transaction-heads.update', $editingHead) : route('transaction-heads.store') }}" class="hg-form-grid" data-setup-form data-transaction-head-form data-draft-form data-draft-defer data-draft-key-base="transaction-heads" data-draft-key="{{ $editingHead ? 'transaction-heads.edit.'.$editingHead->id : 'transaction-heads.create' }}" data-draft-title="Transaction Head">
                @csrf
                <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingHead)>
                <input type="hidden" name="setup_modal" value="transaction-head">
                <input type="hidden" name="record_id" value="{{ old('record_id') }}">

                <input id="head-code" type="hidden" name="code" value="{{ old('code', $editingHead?->code) }}">

                <div class="hg-field">
                    <label for="head-name">Head Name <span class="hg-required">*</span></label>
                    <input id="head-name" name="name" value="{{ old('name', $editingHead?->name) }}" required maxlength="120" placeholder="Milk Sale">
                    <small class="hg-muted">The code is generated automatically after saving and appears in the Transaction Heads table.</small>
                    @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>
                <div class="hg-field">
                    <label for="head-category">Transaction Type <span class="hg-required">*</span></label>
                    <select id="head-category" name="category" required data-head-transaction-type>
                        @foreach ($transactionCategories as $categoryOption)
                            @php($definition = $transactionTypeDefinitions[$categoryOption->value] ?? [])
                            <option value="{{ $categoryOption->value }}" data-allowed-settlements="{{ json_encode($definition['allowed_settlements'] ?? []) }}" data-party-type="{{ $definition['party_type'] ?? 'Any' }}" data-posting-types="{{ json_encode($definition['posting_types'] ?? []) }}" @selected(old('category', $editingHead?->category ?? $defaultCategory) === $categoryOption->value)>{{ $categoryOption->label }}</option>
                        @endforeach
                    </select>
                    @error('category')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>
                <div class="hg-field">
                    <label for="head-posting">Linked COA Account <span class="hg-required">*</span></label>
                    <select id="head-posting" name="posting_account_id" required data-head-posting-account>
                        <option value="">Select account</option>
                        @foreach ($postingAccounts as $account)
                            <option value="{{ $account->id }}" data-account-type="{{ $account->type }}" @selected((string) old('posting_account_id', $editingHead?->posting_account_id) === (string) $account->id)>{{ $account->code }} — {{ $account->name }} ({{ $account->type }})</option>
                        @endforeach
                    </select>
                    @error('posting_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full">
                    <label>Allowed Payment Types <span class="hg-required">*</span></label>
                    <div class="hg-checkbox-grid" data-head-settlement-options>
                        @foreach ($settlementTypes as $settlementOption)
                            <label class="hg-checkbox-label" data-settlement-wrapper="{{ $settlementOption->value }}">
                                <input type="checkbox" name="allowed_settlements[]" value="{{ $settlementOption->value }}" @checked(in_array($settlementOption->value, old('allowed_settlements', $editingHead?->allowedSettlementCodes() ?? $defaultSettlements), true))>
                                {{ $settlementOption->label }}
                            </label>
                        @endforeach
                    </div>
                    @error('allowed_settlements')<small class="hg-field-error">{{ $message }}</small>@enderror
                    @error('allowed_settlements.*')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="head-party-type">Expected Party Type <span class="hg-required">*</span></label>
                    <select id="head-party-type" name="party_type" required data-head-party-type>
                        @foreach ($partyTypes as $partyTypeOption)
                            <option value="{{ $partyTypeOption->value }}" @selected(old('party_type', $editingHead?->party_type ?? $defaultPartyType) === $partyTypeOption->value)>{{ $partyTypeOption->label }}</option>
                        @endforeach
                    </select>
                    @error('party_type')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>
                <div class="hg-field">
                    <input type="hidden" name="is_active" value="0">
                    <label class="hg-checkbox-label" for="head-active"><input id="head-active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingHead?->is_active ?? true))> Active</label>
                </div>
                <div class="hg-field full"><x-accounting.form-actions submit-label="Save Transaction Head" /></div>
            </form>
        </x-accounting.setup-modal>
    @endif
</x-layouts::accounting>
