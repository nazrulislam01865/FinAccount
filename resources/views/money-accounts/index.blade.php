@php
    $modalRecordId = (int) old('record_id', 0);
    $editingAccount = $modalRecordId > 0 ? $moneyAccounts->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'money-account' || $addOnlyMode;
    $defaultMoneyKind = $moneyKinds->first()?->value ?? '';
    $canManage = auth()->user()?->canAccounting('money_accounts.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('money-accounts');
@endphp

<x-layouts::accounting title="Money Accounts">
    <div class="hg-page-header">
        <div>
            <h1>Money Accounts</h1>
        </div>
        @if($canManage)
        <button
            type="button"
            class="hg-btn hg-btn-primary"
            data-setup-open="create"
            data-setup-target="money-account-modal"
            data-defaults="{{ json_encode(['record_id' => '', 'kind' => $defaultMoneyKind, 'is_active' => '1']) }}"
        >+ Add Money Account</button>
        @endif
    </div>

    @if ($moneyAccounts->isEmpty() && $draftRows->isEmpty())
        <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No records found.' }}</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Money Account</th>
                    <th>Mapped COA</th>
                    <th class="right">Current Balance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($moneyAccounts as $moneyAccount)
                    <tr>
                        <td><strong>{{ $moneyAccount->name }}</strong><br><span class="hg-muted">{{ $moneyAccount->kind ? ($moneyKindLabels[$moneyAccount->kind] ?? $moneyAccount->kind) : 'Relationship removed' }}</span></td>
                        <td>{{ $moneyAccount->chartOfAccount ? ($moneyAccount->chartOfAccount->code.' — '.$moneyAccount->chartOfAccount->name) : 'Relationship removed' }}</td>
                        <td class="right">{{ \App\Support\CompanyContext::money($balances[$moneyAccount->id] ?? 0) }}</td>
                        <td><span class="hg-badge {{ $moneyAccount->is_active ? 'on' : 'off' }}">{{ $moneyAccount->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="hg-actions">
                                @if($canManage)
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small"
                                    data-setup-open="edit"
                                    data-setup-target="money-account-modal"
                                    data-edit-title="Edit Money Account"
                                    data-draft-edit-key="money-accounts.edit.{{ $moneyAccount->id }}"
                                    data-update-url="{{ route('money-accounts.update', $moneyAccount) }}"
                                    data-values="{{ json_encode([
                                        'record_id' => $moneyAccount->id,
                                        'name' => $moneyAccount->name,
                                        'kind' => $moneyAccount->kind,
                                        'chart_of_account_id' => $moneyAccount->chart_of_account_id,
                                        'is_active' => $moneyAccount->is_active ? '1' : '0',
                                    ]) }}"
                                >Edit</button>
                                @endif
                                @if($canDelete)
                                <form method="POST" action="{{ route('money-accounts.destroy', $moneyAccount) }}" data-safe-delete-form>
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
                        <td><strong>{{ $fields['name'] ?? 'Draft Money Account' }}</strong><br><span class="hg-muted">{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span></td>
                        <td>{{ filled($fields['chart_of_account_id'] ?? null) ? 'COA ID #'.$fields['chart_of_account_id'] : 'Not selected' }}</td>
                        <td class="right">—</td>
                        <td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
                        <td>
                            <div class="hg-actions">
                                @if($canManage)
                                    @if($isEditDraft)
                                        <button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>
                                    @else
                                        <button type="button" class="hg-btn hg-btn-small" data-setup-open="create" data-setup-target="money-account-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, ['record_id' => '', 'kind' => $defaultMoneyKind, 'is_active' => '1'])) }}">Continue</button>
                                    @endif
                                @endif
                                <form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($canManage)

    <x-accounting.setup-modal
        id="money-account-modal"
        :show="$reopenModal"
        :title="$editingAccount ? 'Edit Money Account' : 'Add Money Account'"
        :store-url="route('money-accounts.store')"
        create-title="Add Money Account"
    >
        <form
            method="POST"
            action="{{ $editingAccount ? route('money-accounts.update', $editingAccount) : route('money-accounts.store') }}"
            class="hg-form-grid"
            data-setup-form
            data-draft-form
            data-draft-defer
            data-draft-key-base="money-accounts"
            data-draft-key="{{ $editingAccount ? 'money-accounts.edit.'.$editingAccount->id : 'money-accounts.create' }}"
            data-draft-title="Money Account"
        >
            @csrf
            <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingAccount)>
            <input type="hidden" name="setup_modal" value="money-account">
            <input type="hidden" name="record_id" value="{{ old('record_id') }}">

            <div class="hg-field">
                <label for="money-name">Name <span class="hg-required">*</span></label>
                <input id="money-name" name="name" value="{{ old('name', $editingAccount?->name) }}" required>
                @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="money-kind">Kind</label>
                <select id="money-kind" name="kind" required>
                    @foreach ($moneyKinds as $kindOption)
                        <option value="{{ $kindOption->value }}" @selected(old('kind', $editingAccount?->kind ?? $defaultMoneyKind) === $kindOption->value)>{{ $kindOption->label }}</option>
                    @endforeach
                </select>
                @error('kind')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="money-coa">Mapped Asset COA <span class="hg-required">*</span></label>
                <select id="money-coa" name="chart_of_account_id" required>
                    <option value="">Select from Chart of Accounts</option>
                    @foreach ($assetAccounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('chart_of_account_id', $editingAccount?->chart_of_account_id) === (string) $account->id)>
                            {{ $account->code }} — {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                @if ($assetAccounts->isEmpty())
                    <small class="hg-field-error">No active Asset COA is available. Add or activate an Asset account in Chart of Accounts first.</small>
                @endif
                @error('chart_of_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field full">
                <input type="hidden" name="is_active" value="0">
                <label class="hg-checkbox-label" for="money-active">
                    <input id="money-active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingAccount?->is_active ?? true))>
                    Active
                </label>
            </div>
            <div class="hg-field full"><x-accounting.form-actions submit-label="Save Money Account" /></div>
        </form>
    </x-accounting.setup-modal>

    @endif
</x-layouts::accounting>
