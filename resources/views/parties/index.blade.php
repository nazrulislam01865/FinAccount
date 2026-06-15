@php
    $modalRecordId = (int) old('record_id', 0);
    $editingParty = $modalRecordId > 0 ? $parties->firstWhere('id', $modalRecordId) : null;
    $reopenModal = old('setup_modal') === 'party';
@endphp

<x-layouts::accounting title="Parties">
    <div class="hg-page-header">
        <div>
            <h1>Parties</h1>
            <p>Customers, suppliers, workers, owners and lenders. Parties are mapped with receivable or payable COA depending on transaction needs.</p>
        </div>
        <button
            type="button"
            class="hg-btn hg-btn-primary"
            data-setup-open="create"
            data-setup-target="party-modal"
            data-defaults="{{ json_encode(['record_id' => '', 'type' => 'Customer', 'opening_balance' => '0', 'is_active' => '1']) }}"
        >+ Add Party</button>
    </div>

    @if ($parties->isEmpty())
        <div class="hg-empty">No records found.</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Party</th>
                    <th>Type</th>
                    <th>Receivable COA</th>
                    <th>Payable COA</th>
                    <th class="right">Balance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($parties as $party)
                    <tr>
                        <td><strong>{{ $party->code }} — {{ $party->name }}</strong></td>
                        <td><span class="hg-badge">{{ $party->type }}</span></td>
                        <td>{{ $party->receivableAccount?->name ?? '-' }}</td>
                        <td>{{ $party->payableAccount?->name ?? '-' }}</td>
                        <td class="right">৳ {{ number_format($balances[$party->id] ?? 0, 2) }}</td>
                        <td><span class="hg-badge {{ $party->is_active ? 'on' : 'off' }}">{{ $party->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="hg-actions">
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small"
                                    data-setup-open="edit"
                                    data-setup-target="party-modal"
                                    data-edit-title="Edit Party"
                                    data-update-url="{{ route('parties.update', $party) }}"
                                    data-values="{{ json_encode([
                                        'record_id' => $party->id,
                                        'code' => $party->code,
                                        'name' => $party->name,
                                        'type' => $party->type,
                                        'opening_balance' => $party->opening_balance,
                                        'receivable_account_id' => $party->receivable_account_id,
                                        'payable_account_id' => $party->payable_account_id,
                                        'is_active' => $party->is_active ? '1' : '0',
                                    ]) }}"
                                >Edit</button>
                                <form method="POST" action="{{ route('parties.destroy', $party) }}" onsubmit="return confirm('Delete this record?')">
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
        id="party-modal"
        :show="$reopenModal"
        :title="$editingParty ? 'Edit Party' : 'Add Party'"
        :store-url="route('parties.store')"
        create-title="Add Party"
    >
        <form method="POST" action="{{ $editingParty ? route('parties.update', $editingParty) : route('parties.store') }}" class="hg-form-grid" data-setup-form>
            @csrf
            <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingParty)>
            <input type="hidden" name="setup_modal" value="party">
            <input type="hidden" name="record_id" value="{{ old('record_id') }}">

            <div class="hg-field">
                <label for="party-code">Code <span class="hg-required">*</span></label>
                <input id="party-code" name="code" value="{{ old('code', $editingParty?->code) }}" required>
                @error('code')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="party-name">Name <span class="hg-required">*</span></label>
                <input id="party-name" name="name" value="{{ old('name', $editingParty?->name) }}" required>
                @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="party-type">Party Type</label>
                <select id="party-type" name="type" required>
                    @foreach (['Customer', 'Supplier', 'Worker', 'Owner', 'Lender'] as $type)
                        <option value="{{ $type }}" @selected(old('type', $editingParty?->type ?? 'Customer') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                @error('type')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="party-opening">Opening Balance</label>
                <input id="party-opening" type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance', $editingParty?->opening_balance ?? 0) }}">
                @error('opening_balance')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="party-receivable">Receivable COA</label>
                <select id="party-receivable" name="receivable_account_id">
                    <option value="">None</option>
                    @foreach ($receivableAccounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('receivable_account_id', $editingParty?->receivable_account_id) === (string) $account->id)>
                            {{ $account->code }} — {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                @error('receivable_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="party-payable">Payable / Capital COA</label>
                <select id="party-payable" name="payable_account_id">
                    <option value="">None</option>
                    @foreach ($payableAccounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('payable_account_id', $editingParty?->payable_account_id) === (string) $account->id)>
                            {{ $account->code }} — {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                @error('payable_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field full">
                <input type="hidden" name="is_active" value="0">
                <label class="hg-checkbox-label" for="party-active">
                    <input id="party-active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingParty?->is_active ?? true))>
                    Active
                </label>
            </div>
            <div class="hg-field full"><button class="hg-btn hg-btn-primary" type="submit">Save</button></div>
        </form>
    </x-accounting.setup-modal>
</x-layouts::accounting>
