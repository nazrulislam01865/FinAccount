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
    <style>
        .hg-party-page .hg-page-header {
            align-items: center;
        }

        .hg-party-page .hg-page-header h1 {
            margin-bottom: 2px;
        }

        .hg-party-page .hg-page-header p {
            max-width: 720px;
        }

        .hg-party-table th:nth-child(1) { width: 48%; }
        .hg-party-table th:nth-child(2) { width: 22%; }
        .hg-party-table th:nth-child(3) { width: 14%; }
        .hg-party-table th:nth-child(4) { width: 16%; }

        .hg-party-name-cell strong {
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            color: #17212b;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .hg-party-code {
            display: inline-flex;
            align-items: center;
            margin-top: 4px;
            padding: 3px 8px;
            border: 1px solid #d7e3f1;
            border-radius: 999px;
            color: #536477;
            background: #f7fbff;
            font-size: 11px;
            font-weight: 750;
        }

        #party-modal .hg-modal-box {
            width: min(620px, calc(100vw - 32px));
            border-radius: 24px;
            overflow: hidden;
        }

        #party-modal .hg-modal-head {
            padding: 22px 24px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        #party-modal .hg-modal-head h2 {
            color: #101828;
            font-size: 24px;
            font-weight: 850;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        #party-modal .hg-modal-head [data-setup-close] {
            width: 42px;
            height: 42px;
            padding: 0;
            border-radius: 14px;
            color: #344054;
            font-size: 20px;
        }

        #party-modal .hg-modal-body {
            padding: 24px;
        }

        .hg-party-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
        }

        .hg-party-form .hg-field.full {
            grid-column: 1 / -1;
        }

        .hg-party-modal-intro {
            display: grid;
            gap: 5px;
            padding: 14px 16px;
            border: 1px solid #cfe1ef;
            border-radius: 16px;
            color: #194f7d;
            background: #f3f9ff;
        }

        .hg-party-modal-intro strong {
            font-size: 14px;
            font-weight: 850;
        }

        .hg-party-modal-intro span {
            color: #536477;
            font-size: 13px;
            line-height: 1.45;
        }

        .hg-party-form .hg-field label:not(.hg-party-active-card) {
            margin-bottom: 8px;
            color: #25344d;
            font-size: 14px;
            font-weight: 800;
        }

        .hg-party-form .hg-field input:not([type="checkbox"]),
        .hg-party-form .hg-field select {
            min-height: 50px;
            border-radius: 14px;
            font-size: 15px;
        }

        .hg-party-form .hg-field input::placeholder {
            color: #98a2b3;
        }

        .hg-party-active-card {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            width: 100%;
            margin: 0;
            padding: 15px 16px;
            border: 1px solid #dbe5f0;
            border-radius: 16px;
            color: #24344d;
            background: #fff;
            cursor: pointer;
        }

        .hg-party-active-card:hover {
            border-color: #b8cce2;
            background: #fbfdff;
        }

        .hg-party-active-card span {
            display: grid;
            gap: 3px;
        }

        .hg-party-active-card strong {
            color: #24344d;
            font-size: 14px;
            font-weight: 850;
        }

        .hg-party-active-card small {
            color: var(--hg-muted);
            font-size: 12px;
            font-weight: 500;
            line-height: 1.4;
        }

        .hg-party-active-card input {
            width: 19px !important;
            height: 19px;
            flex: 0 0 auto;
            accent-color: var(--hg-primary);
        }

        .hg-party-form .hg-form-actions {
            margin-top: 2px;
            padding-top: 18px;
            border-top: 1px solid var(--hg-border);
        }

        .hg-party-form .hg-form-actions > .hg-actions {
            justify-content: flex-end;
        }

        .hg-party-form .hg-form-actions .hg-btn {
            min-height: 44px;
            padding-inline: 18px;
            border-radius: 14px;
            font-size: 14px;
        }

        @media (max-width: 760px) {
            .hg-party-page .hg-page-header {
                align-items: stretch;
            }

            .hg-party-page .hg-page-header .hg-btn {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            #party-modal {
                align-items: flex-end;
                padding: 0;
            }

            #party-modal .hg-modal-box {
                width: 100%;
                max-height: 94vh;
                border-radius: 24px 24px 0 0;
            }

            #party-modal .hg-modal-head,
            #party-modal .hg-modal-body {
                padding: 18px;
            }

            #party-modal .hg-modal-head h2 {
                font-size: 22px;
            }

            .hg-party-form .hg-form-actions > .hg-actions {
                display: grid;
                grid-template-columns: 1fr;
                width: 100%;
            }

            .hg-party-form .hg-form-actions .hg-btn {
                width: 100%;
                justify-content: center;
            }

            .hg-party-active-card {
                align-items: flex-start;
            }
        }
    </style>

    <div class="hg-party-page">
        <div class="hg-page-header">
            <div>
                <h1>Parties</h1>
                <p>Manage customers, suppliers, workers, lenders, and owners. COA mapping is handled automatically from the party type.</p>
            </div>
            @if($canManage)
            <button
                type="button"
                class="hg-btn hg-btn-primary"
                data-setup-open="create"
                data-setup-target="party-modal"
                data-defaults="{{ json_encode(['record_id' => '', 'type' => $defaultPartyType, 'is_active' => '1']) }}"
            >+ Add Party</button>
            @endif
        </div>

        @if ($parties->isEmpty() && $draftRows->isEmpty())
            <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No records found.' }}</div>
        @else
            <div class="hg-table-wrap">
                <table class="hg-table hg-party-table">
                    <thead>
                    <tr>
                        <th>Party</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($parties as $party)
                        <tr>
                            <td class="hg-party-name-cell">
                                <strong title="{{ $party->name }}">{{ $party->name }}</strong><br>
                                <span class="hg-party-code">{{ $party->code }}</span>
                            </td>
                            <td><span class="hg-badge">{{ $party->type ? ($partyTypeLabels[$party->type] ?? $party->type) : 'Relationship removed' }}</span></td>
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
                            <td class="hg-party-name-cell">
                                <strong>{{ $fields['name'] ?? 'Draft Party' }}</strong><br>
                                <span class="hg-party-code">{{ $fields['code'] ?? 'Draft' }} · {{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span>
                            </td>
                            <td><span class="hg-badge">{{ $partyTypeLabels[$fields['type'] ?? ''] ?? ($fields['type'] ?? '—') }}</span></td>
                            <td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
                            <td>
                                <div class="hg-actions">
                                    @if($canManage)
                                        @if($isEditDraft)
                                            <button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>
                                        @else
                                            <button type="button" class="hg-btn hg-btn-small" data-setup-open="create" data-setup-target="party-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, ['record_id' => '', 'type' => $defaultPartyType, 'is_active' => '1'])) }}">Continue</button>
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
            <form method="POST" action="{{ $editingParty ? route('parties.update', $editingParty) : route('parties.store') }}" class="hg-party-form" data-setup-form data-party-form
                data-draft-form
                data-draft-defer
                data-draft-key-base="parties"
                data-draft-key="{{ $editingParty ? 'parties.edit.'.$editingParty->id : 'parties.create' }}"
                data-draft-title="Party">
                @csrf
                <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingParty)>
                <input type="hidden" name="setup_modal" value="party">
                <input type="hidden" name="record_id" value="{{ old('record_id') }}">
                <input type="hidden" id="party-code" name="code" value="{{ old('code', $editingParty?->code ?? ($nextPartyCodes[$defaultPartyType] ?? '')) }}" data-party-code>

                <div class="hg-party-modal-intro full">
                    <strong>Simple party profile</strong>
                    <span>Select the party type only. The receivable or payable COA will be mapped automatically in the background.</span>
                </div>

                <div class="hg-field full">
                    <label for="party-name">Party Name <span class="hg-required">*</span></label>
                    <input id="party-name" name="name" value="{{ old('name', $editingParty?->name) }}" required autocomplete="off" placeholder="Enter party name">
                    @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full">
                    <label for="party-type">Party Type <span class="hg-required">*</span></label>
                    <select id="party-type" name="type" required data-party-type data-hg-searchable-ignore>
                        @foreach ($partyTypes as $typeOption)
                            <option value="{{ $typeOption->value }}" data-next-code="{{ $nextPartyCodes[$typeOption->value] ?? '' }}" @selected(old('type', $editingParty?->type ?? $defaultPartyType) === $typeOption->value)>{{ $typeOption->label }}</option>
                        @endforeach
                    </select>
                    <small class="hg-field-help">Opening balance is still managed separately from the Opening Balances page.</small>
                    @error('type')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full">
                    <input type="hidden" name="is_active" value="0">
                    <label class="hg-party-active-card" for="party-active">
                        <span>
                            <strong>Active party</strong>
                            <small>Allow this party to appear in transaction entry and reports.</small>
                        </span>
                        <input id="party-active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingParty?->is_active ?? true))>
                    </label>
                </div>

                <div class="hg-field full"><x-accounting.form-actions submit-label="Save Party" /></div>
            </form>
        </x-accounting.setup-modal>

        @endif
    </div>
</x-layouts::accounting>
