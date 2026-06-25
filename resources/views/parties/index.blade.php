@php
    $modalRecordId = (int) old('record_id', 0);
    $editingParty = $modalRecordId > 0 ? $parties->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'party' || $addOnlyMode;
    $defaultPartyType = $partyTypes->first()?->value ?? '';
    $canManage = auth()->user()?->canAccounting('parties.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('parties');
@endphp

<x-layouts::accounting title="Parties">
    <div class="hg-page-header">
        <div>
            <h1>Parties</h1>
        </div>
        @if($canManage)
        <button
            type="button"
            class="hg-btn hg-btn-primary"
            data-setup-open="create"
            data-setup-target="party-modal"
            data-defaults="{{ json_encode(['record_id' => '', 'type' => $defaultPartyType, 'opening_balance' => '0', 'is_active' => '1']) }}"
        >+ Add Party</button>
        @endif
    </div>

    @if ($parties->isEmpty() && $draftRows->isEmpty())
        <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No records found.' }}</div>
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
                        <td><span class="hg-badge">{{ $party->type ? ($partyTypeLabels[$party->type] ?? $party->type) : 'Relationship removed' }}</span></td>
                        <td>{{ $party->receivableAccount?->name ?? '-' }}</td>
                        <td>{{ $party->payableAccount?->name ?? '-' }}</td>
                        <td class="right">{{ \App\Support\CompanyContext::money($balances[$party->id] ?? 0) }}</td>
                        <td><span class="hg-badge {{ $party->is_active ? 'on' : 'off' }}">{{ $party->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="hg-actions">
                                @if($canManage)
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small"
                                    data-setup-open="edit"
                                    data-setup-target="party-modal"
                                    data-edit-title="Edit Party"
                                    data-draft-edit-key="parties.edit.{{ $party->id }}"
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
                                @endif
                                @if($canDelete)
                                <form method="POST" action="{{ route('parties.destroy', $party) }}" data-safe-delete-form>
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
                        <td><strong>{{ ($fields['code'] ?? 'Draft').' — '.($fields['name'] ?? 'Draft Party') }}</strong><br><span class="hg-muted">{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span></td>
                        <td><span class="hg-badge">{{ $partyTypeLabels[$fields['type'] ?? ''] ?? ($fields['type'] ?? '—') }}</span></td>
                        <td>{{ filled($fields['receivable_account_id'] ?? null) ? 'COA ID #'.$fields['receivable_account_id'] : '-' }}</td>
                        <td>{{ filled($fields['payable_account_id'] ?? null) ? 'COA ID #'.$fields['payable_account_id'] : '-' }}</td>
                        <td class="right">{{ \App\Support\CompanyContext::money((float) ($fields['opening_balance'] ?? 0)) }}</td>
                        <td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
                        <td>
                            <div class="hg-actions">
                                @if($canManage)
                                    @if($isEditDraft)
                                        <button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>
                                    @else
                                        <button type="button" class="hg-btn hg-btn-small" data-setup-open="create" data-setup-target="party-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, ['record_id' => '', 'type' => $defaultPartyType, 'opening_balance' => '0', 'is_active' => '1'])) }}">Continue</button>
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
        id="party-modal"
        :show="$reopenModal"
        :title="$editingParty ? 'Edit Party' : 'Add Party'"
        :store-url="route('parties.store')"
        create-title="Add Party"
    >
        <form method="POST" action="{{ $editingParty ? route('parties.update', $editingParty) : route('parties.store') }}" class="hg-form-grid" data-setup-form data-party-form
            data-draft-form
            data-draft-defer
            data-draft-key-base="parties"
            data-draft-key="{{ $editingParty ? 'parties.edit.'.$editingParty->id : 'parties.create' }}"
            data-draft-title="Party">
            @csrf
            <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingParty)>
            <input type="hidden" name="setup_modal" value="party">
            <input type="hidden" name="record_id" value="{{ old('record_id') }}">

            <div class="hg-field">
                <label for="party-code">Code <span class="hg-required">*</span></label>
                <input id="party-code" name="code" value="{{ old('code', $editingParty?->code ?? ($nextPartyCodes[$defaultPartyType] ?? '')) }}" required readonly data-party-code>
                <small class="hg-muted">Generated automatically from the selected Party Type.</small>
                @error('code')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="party-name">Name <span class="hg-required">*</span></label>
                <input id="party-name" name="name" value="{{ old('name', $editingParty?->name) }}" required>
                @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="party-type">Party Type</label>
                <select id="party-type" name="type" required data-party-type>
                    @foreach ($partyTypes as $typeOption)
                        <option value="{{ $typeOption->value }}" data-next-code="{{ $nextPartyCodes[$typeOption->value] ?? '' }}" @selected(old('type', $editingParty?->type ?? $defaultPartyType) === $typeOption->value)>{{ $typeOption->label }}</option>
                    @endforeach
                </select>
                @error('type')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="party-opening">Opening Balance</label>
                <input id="party-opening" type="number" step="{{ \App\Support\CompanyContext::amountStep() }}" name="opening_balance" value="{{ old('opening_balance', $editingParty?->opening_balance ?? 0) }}">
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
            <div class="hg-field full"><x-accounting.form-actions submit-label="Save Party" /></div>
        </form>
    </x-accounting.setup-modal>

    @endif
</x-layouts::accounting>
