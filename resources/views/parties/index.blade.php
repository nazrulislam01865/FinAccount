@php
    $modalRecordId = (int) old('record_id', 0);
    $editingParty = $modalRecordId > 0 ? $parties->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'party' || $addOnlyMode;
    $defaultPartyType = $partyTypes->first()?->value ?? '';
    $canManage = auth()->user()?->canAccounting('parties.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('parties');
    $partyStats = [
        'total' => $parties->count(),
        'active' => $parties->where('is_active', true)->count(),
        'inactive' => $parties->where('is_active', false)->count(),
    ];
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

        .hg-party-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin: 18px 0 14px;
            flex-wrap: wrap;
        }

        .hg-party-counters {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .hg-party-counter {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 12px;
            border: 1px solid #dce8f4;
            border-radius: 999px;
            color: #344054;
            background: #fff;
            font-size: 13px;
            font-weight: 750;
        }

        .hg-party-counter strong {
            color: #17212b;
            font-size: 14px;
            font-weight: 900;
        }

        .hg-party-search {
            width: min(360px, 100%);
        }

        .hg-party-search input {
            width: 100%;
            min-height: 42px;
            padding: 10px 14px;
            border: 1px solid #dce8f4;
            border-radius: 14px;
            background: #fff;
            color: #17212b;
            font-size: 14px;
            font-weight: 650;
        }

        .hg-party-search input::placeholder {
            color: #8a98aa;
        }

        .hg-party-table th:nth-child(1) { width: 8%; }
        .hg-party-table th:nth-child(2) { width: 25%; }
        .hg-party-table th:nth-child(3) { width: 22%; }
        .hg-party-table th:nth-child(4) { width: 12%; }
        .hg-party-table th:nth-child(5) { width: 17%; }
        .hg-party-table th:nth-child(6) { width: 8%; }
        .hg-party-table th:nth-child(7) { width: 8%; }

        .hg-party-avatar {
            position: relative;
            width: 48px;
            height: 48px;
            border: 1px solid #dce8f4;
            border-radius: 16px;
            overflow: hidden;
            background: #eef6ff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #536477;
            font-size: 18px;
            font-weight: 900;
        }

        .hg-party-avatar img {
            position: absolute;
            inset: 0;
            z-index: 2;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            background: #eef6ff;
        }

        .hg-party-avatar-initial {
            position: relative;
            z-index: 1;
        }

        .hg-party-detail {
            display: grid;
            gap: 4px;
            color: #536477;
            font-size: 13px;
            font-weight: 650;
            line-height: 1.35;
        }

        .hg-party-empty-filter {
            display: none;
            padding: 18px;
            border: 1px dashed #cbd9e9;
            border-radius: 16px;
            color: #536477;
            background: #f8fbff;
            font-weight: 750;
        }

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
            max-height: calc(100vh - 160px);
            overflow-y: auto;
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

        .hg-party-upload-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border: 1px solid #dbe5f0;
            border-radius: 18px;
            background: #f8fbff;
        }

        .hg-party-upload-preview {
            position: relative;
            width: 76px;
            height: 76px;
            flex: 0 0 76px;
            border: 1px solid #d5e3f3;
            border-radius: 22px;
            overflow: hidden;
            background: #eef6ff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #536477;
            font-size: 26px;
            font-weight: 900;
        }

        .hg-party-upload-preview img {
            position: absolute;
            inset: 0;
            z-index: 2;
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: #eef6ff;
        }

        .hg-party-upload-info {
            min-width: 0;
            display: grid;
            gap: 6px;
        }

        .hg-party-upload-info strong {
            color: #25344d;
            font-size: 14px;
            font-weight: 850;
        }

        .hg-party-upload-info span {
            color: #667085;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.35;
        }

        .hg-party-upload-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 2px;
        }

        .hg-party-upload-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 8px 13px;
            border: 1px solid #cbd9e9;
            border-radius: 12px;
            color: #17212b !important;
            background: #fff;
            font-size: 13px !important;
            font-weight: 850 !important;
            cursor: pointer;
        }

        .hg-party-upload-button:hover {
            border-color: #aac0d9;
            background: #f8fbff;
        }

        .hg-party-upload-input {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
        }

        @media (max-width: 640px) {
            .hg-party-upload-card {
                align-items: flex-start;
            }
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

        @media (max-width: 980px) {
            .hg-party-table {
                min-width: 1080px;
            }
        }

        @media (max-width: 760px) {
            .hg-party-toolbar {
                align-items: stretch;
            }

            .hg-party-counters,
            .hg-party-search {
                width: 100%;
            }

            .hg-party-counter {
                flex: 1 1 auto;
                justify-content: center;
            }

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

        <div class="hg-party-toolbar" data-party-toolbar>
            <div class="hg-party-counters" aria-label="Party counters">
                <span class="hg-party-counter">Total <strong data-party-visible-count>{{ $partyStats['total'] }}</strong></span>
                <span class="hg-party-counter">Active <strong>{{ $partyStats['active'] }}</strong></span>
                <span class="hg-party-counter">Inactive <strong>{{ $partyStats['inactive'] }}</strong></span>
            </div>
            <div class="hg-party-search">
                <input type="search" placeholder="Search party, code, type, phone or email..." aria-label="Search parties" data-party-search>
            </div>
        </div>

        @if ($parties->isEmpty() && $draftRows->isEmpty())
            <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No records found.' }}</div>
        @else
            <div class="hg-table-wrap">
                <table class="hg-table hg-party-table">
                    <thead>
                    <tr>
                        <th>Image</th>
                        <th>Party</th>
                        <th>Contact</th>
                        <th>Type</th>
                        <th>Created</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($parties as $party)
                        @php
                            $partyImageUrl = $party->profile_pic ? route('parties.avatar', $party) : null;
                            $partyInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($party->name, 0, 1));
                            $partyCreator = $party->creator?->name ?? 'System';
                            $partyCreatedOn = $party->created_at?->format('d/m/Y') ?? '—';
                            $partySearchText = collect([
                                $party->name,
                                $party->code,
                                $party->type,
                                $partyTypeLabels[$party->type] ?? null,
                                $party->phone,
                                $party->email,
                                $partyCreator,
                                $partyCreatedOn,
                                $party->is_active ? 'Active' : 'Inactive',
                            ])->filter()->implode(' ');
                        @endphp
                        <tr data-party-row data-party-search-text="{{ \Illuminate\Support\Str::lower($partySearchText) }}">
                            <td>
                                <span class="hg-party-avatar">
                                    <span class="hg-party-avatar-initial">{{ $partyInitial }}</span>
                                    @if($partyImageUrl)
                                        <img src="{{ $partyImageUrl }}" alt="{{ $party->name }}" loading="lazy" onerror="this.remove()">
                                    @endif
                                </span>
                            </td>
                            <td class="hg-party-name-cell">
                                <strong title="{{ $party->name }}">{{ $party->name }}</strong><br>
                                <span class="hg-party-code">{{ $party->code }}</span>
                            </td>
                            <td>
                                <div class="hg-party-detail">
                                    <span>Phone: {{ $party->phone ?: '—' }}</span>
                                    <span>Email: {{ $party->email ?: '—' }}</span>
                                </div>
                            </td>
                            <td><span class="hg-badge">{{ $party->type ? ($partyTypeLabels[$party->type] ?? $party->type) : 'Relationship removed' }}</span></td>
                            <td>
                                <div class="hg-party-detail">
                                    <span>By: {{ $partyCreator }}</span>
                                    <span>On: {{ $partyCreatedOn }}</span>
                                </div>
                            </td>
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
                                            'phone' => $party->phone,
                                            'email' => $party->email,
                                            'address' => $party->address,
                                            'is_active' => $party->is_active ? '1' : '0',
                                            'profile_image_url' => $partyImageUrl,
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
                            <td><span class="hg-party-avatar">D</span></td>
                            <td class="hg-party-name-cell">
                                <strong>{{ $fields['name'] ?? 'Draft Party' }}</strong><br>
                                <span class="hg-party-code">{{ $fields['code'] ?? 'Draft' }} · {{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span>
                            </td>
                            <td>
                                <div class="hg-party-detail">
                                    <span>Phone: {{ $fields['phone'] ?? '—' }}</span>
                                    <span>Email: {{ $fields['email'] ?? '—' }}</span>
                                </div>
                            </td>
                            <td><span class="hg-badge">{{ $partyTypeLabels[$fields['type'] ?? ''] ?? ($fields['type'] ?? '—') }}</span></td>
                            <td><div class="hg-party-detail"><span>Unsaved</span><span>{{ $draft->updated_at?->diffForHumans() }}</span></div></td>
                            <td><span class="hg-badge draft">Draft</span></td>
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
            <div class="hg-party-empty-filter" data-party-empty-filter>No matching parties found.</div>
        @endif

        @if($canManage)

        <x-accounting.setup-modal
            id="party-modal"
            :show="$reopenModal"
            :title="$editingParty ? 'Edit Party' : 'Add Party'"
            :store-url="route('parties.store')"
            create-title="Add Party"
        >
            <form method="POST" action="{{ $editingParty ? route('parties.update', $editingParty) : route('parties.store') }}" enctype="multipart/form-data" class="hg-party-form" data-setup-form data-party-form
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
                    <label for="party-phone">Phone</label>
                    <input id="party-phone" name="phone" value="{{ old('phone', $editingParty?->phone) }}" autocomplete="off" placeholder="Enter phone number">
                    @error('phone')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full">
                    <label for="party-email">Email</label>
                    <input type="email" id="party-email" name="email" value="{{ old('email', $editingParty?->email) }}" autocomplete="off" placeholder="Enter email address">
                    @error('email')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full">
                    <label for="party-address">Address</label>
                    <textarea id="party-address" name="address" rows="3" class="feed-control" placeholder="Enter full address">{{ old('address', $editingParty?->address) }}</textarea>
                    @error('address')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                @php
                    $editingPartyImageUrl = $editingParty?->profile_pic ? route('parties.avatar', $editingParty) : null;
                    $editingPartyInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(old('name', $editingParty?->name ?? 'P'), 0, 1));
                @endphp
                <div class="hg-field full">
                    <label>Profile Picture (Optional)</label>
                    <div class="hg-party-upload-card">
                        <div class="hg-party-upload-preview" data-party-upload-preview data-initial="{{ $editingPartyInitial }}">
                            <span data-party-upload-initial>{{ $editingPartyInitial }}</span>
                            @if($editingPartyImageUrl)
                                <img src="{{ $editingPartyImageUrl }}" alt="Current profile picture" data-party-upload-preview-image onerror="this.remove()">
                            @endif
                        </div>
                        <div class="hg-party-upload-info">
                            <strong>Upload a party image</strong>
                            <span>Use JPG, PNG, or WEBP. Maximum file size is 5 MB.</span>
                            <div class="hg-party-upload-actions">
                                <label class="hg-party-upload-button" for="party-profile-pic">Choose image</label>
                                <span data-party-upload-name>{{ $editingPartyImageUrl ? 'Current image uploaded' : 'No image selected' }}</span>
                            </div>
                            <input class="hg-party-upload-input" type="file" id="party-profile-pic" name="profile_pic" accept="image/png,image/jpeg,image/jpg,image/webp">
                        </div>
                    </div>
                    @if($editingParty && $editingParty->profile_pic)
                        <small class="hg-field-help">Choosing a new image will replace the current image.</small>
                    @endif
                    @error('profile_pic')<small class="hg-field-error">{{ $message }}</small>@enderror
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const input = document.querySelector('[data-party-search]');
            const rows = Array.from(document.querySelectorAll('[data-party-row]'));
            const visibleCounter = document.querySelector('[data-party-visible-count]');
            const emptyState = document.querySelector('[data-party-empty-filter]');

            if (!input || rows.length === 0) {
                return;
            }

            const filterRows = () => {
                const query = input.value.trim().toLowerCase();
                let visible = 0;

                rows.forEach((row) => {
                    const haystack = row.getAttribute('data-party-search-text') || row.textContent.toLowerCase();
                    const matches = query === '' || haystack.includes(query);
                    row.hidden = !matches;
                    if (matches) visible += 1;
                });

                if (visibleCounter) {
                    visibleCounter.textContent = String(visible);
                }

                if (emptyState) {
                    emptyState.style.display = visible === 0 ? 'block' : 'none';
                }
            };

            input.addEventListener('input', filterRows);
            filterRows();
        });

        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-party-form]');
            if (!form) return;

            const fileInput = form.querySelector('#party-profile-pic');
            const nameInput = form.querySelector('#party-name');
            const preview = form.querySelector('[data-party-upload-preview]');
            const uploadName = form.querySelector('[data-party-upload-name]');
            const initialNode = form.querySelector('[data-party-upload-initial]');
            let currentImageUrl = preview?.querySelector('img')?.getAttribute('src') || '';

            const initialFromName = (name) => {
                const value = String(name || '').trim();
                return (value ? value.charAt(0) : 'P').toUpperCase();
            };

            const clearPreviewImage = () => {
                preview?.querySelectorAll('img').forEach((image) => image.remove());
            };

            const setInitial = (name) => {
                if (initialNode) initialNode.textContent = initialFromName(name);
                if (preview) preview.dataset.initial = initialFromName(name);
            };

            const setPreviewImage = (src, label = 'Image selected') => {
                clearPreviewImage();
                if (!preview || !src) {
                    if (uploadName) uploadName.textContent = 'No image selected';
                    return;
                }

                const image = document.createElement('img');
                image.src = src;
                image.alt = 'Profile picture preview';
                image.dataset.partyUploadPreviewImage = '1';
                image.onerror = () => image.remove();
                preview.appendChild(image);
                if (uploadName) uploadName.textContent = label;
            };

            nameInput?.addEventListener('input', () => {
                setInitial(nameInput.value);
            });

            fileInput?.addEventListener('change', () => {
                const file = fileInput.files?.[0];
                if (!file) {
                    setPreviewImage(currentImageUrl, currentImageUrl ? 'Current image uploaded' : 'No image selected');
                    return;
                }

                if (!file.type.startsWith('image/')) {
                    fileInput.value = '';
                    setPreviewImage(currentImageUrl, currentImageUrl ? 'Current image uploaded' : 'No image selected');
                    return;
                }

                const reader = new FileReader();
                reader.onload = (event) => setPreviewImage(String(event.target?.result || ''), file.name);
                reader.readAsDataURL(file);
            });

            form.addEventListener('hisebghor:setup-values-applied', (event) => {
                const values = event.detail?.values || {};
                currentImageUrl = String(values.profile_image_url || '');
                if (fileInput) fileInput.value = '';
                setInitial(values.name || nameInput?.value || 'P');
                setPreviewImage(currentImageUrl, currentImageUrl ? 'Current image uploaded' : 'No image selected');
            });
        });

    </script>
</x-layouts::accounting>
