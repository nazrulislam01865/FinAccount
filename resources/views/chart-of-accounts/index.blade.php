@php
    $reopenModal = $errors->any() && old('coa_modal') === '1';
    $editingId = old('account_id');
    $editingAccount = $modalAccount;
    $defaultAccountType = $accountTypes->first()?->value ?? '';
    $defaultNormalBalance = $normalBalances->first()?->value ?? '';
@endphp

<x-layouts::accounting title="Chart of Accounts">
    <div class="hg-page-header">
        <div>
            <h1>Chart of Accounts</h1>
            <p>Minimum COA required to post transactions and generate balances.</p>
        </div>
        <button
            type="button"
            class="hg-btn hg-btn-primary"
            data-coa-open="create"
            data-store-url="{{ route('chart-of-accounts.store') }}"
        >+ Add COA</button>
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

    @if ($accounts->isEmpty())
        <div class="hg-empty">No records found.</div>
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
                                        data-update-url="{{ route('chart-of-accounts.update', $account) }}"
                                    >Edit</button>

                                    <form
                                        method="POST"
                                        action="{{ route('chart-of-accounts.destroy', $account) }}"
                                     data-safe-delete-form>
                                        @csrf
                                        @method('DELETE')
                                        <button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

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
                        <button class="hg-btn hg-btn-primary" type="submit">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts::accounting>
