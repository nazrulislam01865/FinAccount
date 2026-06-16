@php
    $modalRecordId = (int) old('record_id', 0);
    $editingAccount = $modalRecordId > 0 ? $moneyAccounts->firstWhere('id', $modalRecordId) : null;
    $reopenModal = old('setup_modal') === 'money-account';
    $defaultMoneyKind = $moneyKinds->first()?->value ?? '';
@endphp

<x-layouts::accounting title="Money Accounts">
    <div class="hg-page-header">
        <div>
            <h1>Money Accounts</h1>
        </div>
        <button
            type="button"
            class="hg-btn hg-btn-primary"
            data-setup-open="create"
            data-setup-target="money-account-modal"
            data-defaults="{{ json_encode(['record_id' => '', 'kind' => $defaultMoneyKind, 'opening_balance' => '0', 'is_active' => '1']) }}"
        >+ Add Money Account</button>
    </div>

    @if ($moneyAccounts->isEmpty())
        <div class="hg-empty">No records found.</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Money Account</th>
                    <th>Mapped COA</th>
                    <th class="right">Opening</th>
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
                        <td class="right">৳ {{ number_format((float) $moneyAccount->opening_balance, 2) }}</td>
                        <td class="right">৳ {{ number_format($balances[$moneyAccount->id] ?? 0, 2) }}</td>
                        <td><span class="hg-badge {{ $moneyAccount->is_active ? 'on' : 'off' }}">{{ $moneyAccount->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="hg-actions">
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small"
                                    data-setup-open="edit"
                                    data-setup-target="money-account-modal"
                                    data-edit-title="Edit Money Account"
                                    data-update-url="{{ route('money-accounts.update', $moneyAccount) }}"
                                    data-values="{{ json_encode([
                                        'record_id' => $moneyAccount->id,
                                        'name' => $moneyAccount->name,
                                        'kind' => $moneyAccount->kind,
                                        'chart_of_account_id' => $moneyAccount->chart_of_account_id,
                                        'opening_balance' => $moneyAccount->opening_balance,
                                        'is_active' => $moneyAccount->is_active ? '1' : '0',
                                    ]) }}"
                                >Edit</button>
                                <form method="POST" action="{{ route('money-accounts.destroy', $moneyAccount) }}" data-safe-delete-form>
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
            <div class="hg-field">
                <label for="money-opening">Opening Balance</label>
                <input id="money-opening" type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance', $editingAccount?->opening_balance ?? 0) }}">
                @error('opening_balance')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field full">
                <input type="hidden" name="is_active" value="0">
                <label class="hg-checkbox-label" for="money-active">
                    <input id="money-active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingAccount?->is_active ?? true))>
                    Active
                </label>
            </div>
            <div class="hg-field full"><button class="hg-btn hg-btn-primary" type="submit">Save</button></div>
        </form>
    </x-accounting.setup-modal>
</x-layouts::accounting>
