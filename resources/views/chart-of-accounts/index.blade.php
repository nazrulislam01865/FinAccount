@php
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = ($errors->any() && old('coa_modal') === '1') || $addOnlyMode;
    $editingId = old('account_id');
    $editingAccount = $modalAccount;
    $defaultAccountType = $accountTypes->first()?->value ?? '';
    $defaultNormalBalance = $normalBalances->first()?->value ?? '';
    $canManage = auth()->user()?->canAccounting('chart_of_accounts.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('chart-of-accounts');
@endphp

<x-layouts::accounting title="Chart of Accounts">
    <div class="hg-page-header">
        <div>
            <h1>Chart of Accounts</h1>
        </div>
        @if($canManage)
        <button
            type="button"
            class="hg-btn hg-btn-primary"
            data-coa-open="create"
            data-store-url="{{ route('chart-of-accounts.store') }}"
        >+ Add COA</button>
        @endif
    </div>

    <form method="GET" action="{{ route('chart-of-accounts.index') }}" class="hg-toolbar">
        <input
            class="hg-search"
            type="search"
            name="search"
            value="{{ $search }}"
            placeholder="Search account code or name..."
            aria-label="Search chart of accounts"
        >
    </form>

    @if ($accounts->isEmpty() && $draftRows->isEmpty())
        <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No records found.' }}</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Normal</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        <tr>
                            <td><strong>{{ $account->code }}</strong></td>
                            <td>{{ $account->name }}</td>
                            <td><span class="hg-badge {{ strtolower($account->type) }}">{{ $account->type }}</span></td>
                            <td>{{ $account->normal_balance }}</td>
                            <td>
                                <span class="hg-badge {{ $account->is_active ? 'on' : 'off' }}">
                                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="hg-actions">
                                    @if($canManage)
                                    <button
                                        type="button"
                                        class="hg-btn hg-btn-small"
                                        data-coa-open="edit"
                                        data-account-id="{{ $account->id }}"
                                        data-code="{{ $account->code }}"
                                        data-name="{{ $account->name }}"
                                        data-type="{{ $account->type }}"
                                        data-normal="{{ $account->normal_balance }}"
                                        data-active="{{ $account->is_active ? '1' : '0' }}"
                                        data-draft-edit-key="chart-of-accounts.edit.{{ $account->id }}"
                                        data-update-url="{{ route('chart-of-accounts.update', $account) }}"
                                    >Edit</button>
                                    @endif

                                    @if($canDelete)

                                    <form
                                        method="POST"
                                        action="{{ route('chart-of-accounts.destroy', $account) }}"
                                     data-safe-delete-form>
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
                            <td><strong>{{ $fields['code'] ?? 'Draft' }}</strong><br><small>{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</small></td>
                            <td>{{ $fields['name'] ?? 'Draft Chart of Account' }}</td>
                            <td><span class="hg-badge {{ strtolower((string) ($fields['type'] ?? '')) }}">{{ $fields['type'] ?? '—' }}</span></td>
                            <td>{{ $fields['normal_balance'] ?? '—' }}</td>
                            <td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
                            <td>
                                <div class="hg-actions">
                                    @if($canManage)
                                        @if($isEditDraft)
                                            <button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>
                                        @else
                                            <button type="button" class="hg-btn hg-btn-small" data-coa-open="create" data-store-url="{{ route('chart-of-accounts.store') }}">Continue</button>
                                        @endif
                                    @endif
                                    <form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($canManage)

    <div
        class="hg-modal {{ $reopenModal ? 'show' : '' }}"
        id="coa-modal"
        data-store-url="{{ route('chart-of-accounts.store') }}"
        data-default-type="{{ $defaultAccountType }}"
        data-default-normal="{{ $defaultNormalBalance }}"
        aria-hidden="{{ $reopenModal ? 'false' : 'true' }}"
    >
        <div class="hg-modal-box" role="dialog" aria-modal="true" aria-labelledby="coa-modal-title">
            <div class="hg-modal-head">
                <h2 id="coa-modal-title">{{ $editingAccount ? 'Edit COA Account' : 'Add COA Account' }}</h2>
                <button type="button" class="hg-btn hg-btn-small" data-coa-close aria-label="Close">✕</button>
            </div>

            <div class="hg-modal-body">
                <form
                    id="coa-form"
                    method="POST"
                    action="{{ $editingAccount ? route('chart-of-accounts.update', $editingAccount) : route('chart-of-accounts.store') }}"
                    class="hg-form-grid"
                    data-draft-form
                    data-draft-defer
                    data-draft-key-base="chart-of-accounts"
                    data-draft-key="{{ $editingAccount ? 'chart-of-accounts.edit.'.$editingAccount->id : 'chart-of-accounts.create' }}"
                    data-draft-title="Chart of Account"
                >
                    @csrf
                    <input id="coa-method" type="hidden" name="_method" value="PUT" @disabled(! $editingAccount)>
                    <input type="hidden" name="coa_modal" value="1">
                    <input id="coa-account-id" type="hidden" name="account_id" value="{{ old('account_id') }}">

                    <div class="hg-field">
                        <label for="coa-code">Code <span class="hg-required">*</span></label>
                        <input id="coa-code" name="code" value="{{ old('code', $editingAccount?->code) }}" required>
                        @error('code')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="hg-field">
                        <label for="coa-name">Name <span class="hg-required">*</span></label>
                        <input id="coa-name" name="name" value="{{ old('name', $editingAccount?->name) }}" required>
                        @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="hg-field">
                        <label for="coa-type">Type <span class="hg-required">*</span></label>
                        <select id="coa-type" name="type" required>
                            @foreach ($accountTypes as $typeOption)
                                <option value="{{ $typeOption->value }}" @selected(old('type', $editingAccount?->type ?? $defaultAccountType) === $typeOption->value)>{{ $typeOption->label }}</option>
                            @endforeach
                        </select>
                        @error('type')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="hg-field">
                        <label for="coa-normal">Normal Balance</label>
                        <select id="coa-normal" name="normal_balance" required>
                            @foreach ($normalBalances as $normalOption)
                                <option value="{{ $normalOption->value }}" @selected(old('normal_balance', $editingAccount?->normal_balance ?? $defaultNormalBalance) === $normalOption->value)>{{ $normalOption->label }}</option>
                            @endforeach
                        </select>
                        @error('normal_balance')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="hg-field full">
                        <input type="hidden" name="is_active" value="0">
                        <label class="hg-checkbox-label" for="coa-active">
                            <input
                                id="coa-active"
                                type="checkbox"
                                name="is_active"
                                value="1"
                                @checked(old('is_active', $editingAccount?->is_active ?? true))
                            >
                            Active
                        </label>
                    </div>

                    <div class="hg-field full">
                        <x-accounting.form-actions submit-label="Save Account" />
                    </div>
                </form>
            </div>
        </div>
    </div>

    @endif
</x-layouts::accounting>
