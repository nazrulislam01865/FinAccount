@php
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = ($errors->any() && old('coa_modal') === '1') || $addOnlyMode;
    $editingAccount = $modalAccount;
    $defaultAccountType = old('type', $editingAccount?->type ?? $accountTypes->first()?->value ?? '');
    $defaultNormalBalance = old('normal_balance', $editingAccount?->normal_balance ?? $normalBalances->first()?->value ?? '');
    $defaultParentId = old('parent_id', $editingAccount?->parent_id);
    $defaultLevel = (int) old('level', $editingAccount?->level ?? ($defaultParentId ? 2 : 1));
    $defaultCode = old('code', $editingAccount?->code ?? ($nextCodes[(string) ($defaultParentId ?: 'root')] ?? $nextCodes['root'] ?? ''));
    $canManage = auth()->user()?->canAccounting('chart_of_accounts.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('chart-of-accounts');
@endphp

<x-layouts::accounting title="Chart of Accounts">
    <div class="hg-page-header">
        <div>
            <h1>Chart of Accounts</h1>
            <p class="hg-muted">Level 1 is the main group, Level 2 is the category, and Level 3 is the posting ledger.</p>
        </div>
        <div class="hg-actions">
            @if(! $addOnlyMode)
                <a class="hg-btn" href="{{ route('chart-of-accounts.export') }}">Export Excel</a>
            @endif
            @if($canManage)
                <button
                    type="button"
                    class="hg-btn hg-btn-primary"
                    data-coa-open="create"
                    data-store-url="{{ route('chart-of-accounts.store') }}"
                >+ Add COA</button>
            @endif
        </div>
    </div>

    <div class="hg-coa-level-summary" aria-label="Chart of accounts level summary">
        @foreach ([1 => 'Main Groups', 2 => 'Categories', 3 => 'Posting Ledgers'] as $level => $label)
            <a
                href="{{ route('chart-of-accounts.index', array_filter(['search' => $search, 'level' => $level])) }}"
                class="hg-coa-level-card {{ $levelFilter === $level ? 'active' : '' }}"
            >
                <span>Level {{ $level }}</span>
                <strong>{{ number_format($levelCounts[$level] ?? 0) }}</strong>
                <small>{{ $label }}</small>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('chart-of-accounts.index') }}" class="hg-toolbar">
        <input
            class="hg-search"
            type="search"
            name="search"
            value="{{ $search }}"
            placeholder="Search code, account, parent, type, or level..."
            aria-label="Search chart of accounts"
        >
        <select class="hg-filter-select" name="level" aria-label="Filter chart of accounts by level" onchange="this.form.submit()">
            <option value="0">All Levels</option>
            <option value="1" @selected($levelFilter === 1)>Level 1 — Main Groups</option>
            <option value="2" @selected($levelFilter === 2)>Level 2 — Categories</option>
            <option value="3" @selected($levelFilter === 3)>Level 3 — Posting Ledgers</option>
        </select>
        @if($search !== '' || $levelFilter !== 0)
            <a class="hg-btn" href="{{ route('chart-of-accounts.index') }}">Clear</a>
        @endif
    </form>

    @if($canDelete)
        <form
            id="coa-bulk-delete-form"
            method="POST"
            action="{{ route('chart-of-accounts.bulk-destroy') }}"
            data-safe-delete-form
            data-coa-bulk-form
        >
            @csrf
            @method('DELETE')
        </form>
        <div class="hg-toolbar hg-bulk-toolbar" data-coa-bulk-toolbar hidden>
            <button
                type="submit"
                class="hg-btn hg-btn-danger"
                form="coa-bulk-delete-form"
                data-coa-bulk-delete
                disabled
            >Delete Selected</button>
            <span class="hg-muted" data-coa-bulk-count>0 selected</span>
            <small class="hg-muted">Parent accounts can be bulk deleted only when all child accounts are selected too.</small>
        </div>
    @endif

    @if ($accounts->isEmpty() && $draftRows->isEmpty())
        <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No records found.' }}</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table hg-coa-table">
                <thead>
                    <tr>
                        @if($canDelete)<th class="hg-checkbox-col"><input type="checkbox" data-coa-bulk-master aria-label="Select all chart of accounts"></th>@endif
                        <th>Code</th>
                        <th>Account Name</th>
                        <th>Level</th>
                        <th>Parent Account</th>
                        <th>Type</th>
                        <th>Normal</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        <tr class="hg-coa-row hg-coa-row-level-{{ $account->level }}">
                            @if($canDelete)
                                <td class="hg-checkbox-col">
                                    <input
                                        type="checkbox"
                                        name="account_ids[]"
                                        value="{{ $account->id }}"
                                        form="coa-bulk-delete-form"
                                        data-coa-bulk-checkbox
                                        aria-label="Select {{ $account->code }} — {{ $account->name }}"
                                    >
                                </td>
                            @endif
                            <td><strong class="hg-code-chip">{{ $account->code }}</strong></td>
                            <td>
                                <div class="hg-coa-account-name hg-coa-indent-{{ $account->parent_id ? $account->level : 1 }}">
                                    @if((int) $account->level > 1 && $account->parent_id)
                                        <span class="hg-coa-branch" aria-hidden="true">↳</span>
                                    @endif
                                    <div>
                                        <strong>{{ $account->name }}</strong>
                                        @if((int) $account->level === 3 && ! $account->parent_id)
                                            <small class="hg-muted">Legacy ledger — assign a Level 2 parent when convenient</small>
                                        @elseif($account->children_count > 0)
                                            <small class="hg-muted">{{ $account->children_count }} direct {{ \Illuminate\Support\Str::plural('child', $account->children_count) }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td><span class="hg-badge hg-level-badge level-{{ $account->level }}">Level {{ $account->level }}</span></td>
                            <td>
                                @if($account->parent)
                                    <span class="hg-code-chip">{{ $account->parent->code }}</span>
                                    {{ $account->parent->name }}
                                @else
                                    <span class="hg-muted">{{ (int) $account->level === 1 ? 'None — top level' : 'Unassigned' }}</span>
                                @endif
                            </td>
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
                                        data-parent-id="{{ $account->parent_id }}"
                                        data-level="{{ $account->level }}"
                                        data-children-count="{{ $account->children_count }}"
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
                                        @if($account->children_count > 0)
                                            <span class="hg-muted" title="Move or delete child accounts first">Has children</span>
                                        @else
                                            <form method="POST" action="{{ route('chart-of-accounts.destroy', $account) }}" data-safe-delete-form>
                                                @csrf
                                                @method('DELETE')
                                                <button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    @foreach ($draftRows as $draft)
                        @php
                            $fields = \App\Support\VisibleFormDrafts::fields($draft);
                            $isEditDraft = \App\Support\VisibleFormDrafts::isEdit($draft);
                            $draftLevel = (int) ($fields['level'] ?? 1);
                            $draftParent = $parentOptions->firstWhere('id', (int) ($fields['parent_id'] ?? 0));
                        @endphp
                        <tr class="hg-table-draft-row">
                            @if($canDelete)<td class="hg-checkbox-col"></td>@endif
                            <td><strong>{{ $fields['code'] ?? 'Draft' }}</strong><br><small>{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</small></td>
                            <td>{{ $fields['name'] ?? 'Draft Chart of Account' }}</td>
                            <td><span class="hg-badge draft">Level {{ $draftLevel }}</span></td>
                            <td>{{ $draftParent ? $draftParent->code.' — '.$draftParent->name : 'None' }}</td>
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
        data-root-next-code="{{ $nextCodes['root'] ?? '' }}"
        data-editing-parent-id="{{ $editingAccount?->parent_id }}"
        data-editing-level="{{ $editingAccount?->level }}"
        data-editing-code="{{ $editingAccount?->code }}"
        data-editing-type="{{ $editingAccount?->type }}"
        data-editing-children-count="{{ $editingAccount?->children_count ?? 0 }}"
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

                    <div class="hg-field full">
                        <label for="coa-parent">Parent Account</label>
                        <select id="coa-parent" name="parent_id" data-hg-searchable data-hg-search-placeholder="Search parent account...">
                            <option
                                value=""
                                data-level="0"
                                data-type=""
                                data-next-code="{{ $nextCodes['root'] ?? '' }}"
                            >None / Main Parent — creates Level 1</option>
                            @foreach($parentOptions as $parentOption)
                                <option
                                    value="{{ $parentOption->id }}"
                                    data-level="{{ $parentOption->level }}"
                                    data-type="{{ $parentOption->type }}"
                                    data-normal="{{ $parentOption->normal_balance }}"
                                    data-next-code="{{ $nextCodes[(string) $parentOption->id] ?? '' }}"
                                    @selected((string) $defaultParentId === (string) $parentOption->id)
                                >{{ $parentOption->level === 2 ? ' ↳ ' : '' }}{{ $parentOption->code }} — {{ $parentOption->name }} (Level {{ $parentOption->level }})</option>
                            @endforeach
                        </select>
                        <small class="hg-field-help" id="coa-parent-help">No parent creates Level 1. Selecting Level 1 creates Level 2; selecting Level 2 creates Level 3.</small>
                        @error('parent_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>

                    <input id="coa-level" type="hidden" name="level" value="{{ $defaultLevel }}">

                    <div class="hg-field">
                        <label for="coa-code">Code <span class="hg-required">*</span></label>
                        <input id="coa-code" name="code" value="{{ $defaultCode }}" required readonly>
                        <small class="hg-field-help" id="coa-code-help">Generated from the latest sequence in this hierarchy level.</small>
                        @error('code')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="hg-field full">
                        <label for="coa-name">Name <span class="hg-required">*</span></label>
                        <input id="coa-name" name="name" value="{{ old('name', $editingAccount?->name) }}" required>
                        @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="hg-field">
                        <label for="coa-type">Type <span class="hg-required">*</span></label>
                        <select id="coa-type" name="type" required data-hg-searchable data-hg-search-placeholder="Search account type...">
                            @foreach ($accountTypes as $typeOption)
                                <option
                                    value="{{ $typeOption->value }}"
                                    data-default-normal="{{ $typeOption->metadata['default_normal_balance'] ?? '' }}"
                                    @selected($defaultAccountType === $typeOption->value)
                                >{{ $typeOption->label }}</option>
                            @endforeach
                        </select>
                        <input id="coa-type-hidden" type="hidden" name="type" value="{{ $defaultAccountType }}" disabled>
                        <small class="hg-field-help" id="coa-type-help">Level 2 and Level 3 automatically inherit the parent account type.</small>
                        @error('type')<small class="hg-field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="hg-field" id="coa-normal-field">
                        <label for="coa-normal">Normal Balance <span class="hg-required">*</span></label>
                        <select id="coa-normal" name="normal_balance" required data-hg-searchable data-hg-search-placeholder="Search normal balance...">
                            @foreach ($normalBalances as $normalOption)
                                <option value="{{ $normalOption->value }}" @selected($defaultNormalBalance === $normalOption->value)>{{ $normalOption->label }}</option>
                            @endforeach
                        </select>
                        <input id="coa-normal-hidden" type="hidden" name="normal_balance" value="{{ $defaultNormalBalance }}" disabled>
                        <small class="hg-field-help" id="coa-normal-help">Level 1 normal balance can be selected. Level 2 and Level 3 inherit from their parent.</small>
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
