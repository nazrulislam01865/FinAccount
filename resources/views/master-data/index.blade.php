@php
    $modalRecordId = (int) old('record_id', 0);
    $editingOption = $modalRecordId > 0 ? $options->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'master-'.$configuration['section'] || $addOnlyMode;
    $isTransactionCategory = $configuration['group'] === \App\Models\AccountingOption::GROUP_TRANSACTION_CATEGORY;
    $coreCategoryValues = $configuration['core_values'] ?? [];
    $singularTitle = str($configuration['title'])->singular();
    $permissionPrefix = match ($configuration['section']) {
        'party-types' => 'party_types',
        'money-account-types' => 'money_account_types',
        default => 'transaction_categories',
    };
    $canManage = auth()->user()?->canAccounting($permissionPrefix.'.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('master-data-'.$configuration['section']);
@endphp

<x-layouts::accounting :title="$configuration['title']">
    <div class="hg-page-header">
        <div>
            <div class="hg-page-kicker">Master / {{ $configuration['menu_group'] }}</div>
            <h1>{{ $configuration['title'] }}</h1>
            @if (filled($configuration['description'] ?? null))
                <p>{{ $configuration['description'] }}</p>
            @endif
        </div>

        @if ($configuration['creatable'])
            @if($canManage)
            <button
                type="button"
                class="hg-btn hg-btn-primary"
                data-setup-open="create"
                data-setup-target="master-data-modal"
                data-defaults="{{ json_encode([
                    'record_id' => '',
                    'value' => '',
                    'label' => '',
                    'money_label' => 'Money Account',
                    'voucher_prefix' => '',
                    'sort_order' => (($options->max('sort_order') ?? 0) + 10),
                    'is_active' => '1',
                ]) }}"
            >+ Add {{ $singularTitle }}</button>
            @endif
        @endif
    </div>

    <div class="hg-spacer"></div>

    @if($addOnlyMode)<div class="hg-info">You may add records, but your role is not allowed to view this list.</div><div class="hg-spacer"></div>@endif

    @if ($options->isEmpty() && $draftRows->isEmpty())
        <div class="hg-empty">No master values found.</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Internal Value</th>
                    <th>Display Label</th>
                    @if ($isTransactionCategory)
                        <th>Voucher Prefix</th>
                        <th>Money Field Label</th>
                    @endif
                    <th>Sort Order</th>
                    <th>Usage</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($options as $option)
                    @php
                        $optionUsage = $usage[$option->id] ?? ['count' => 0, 'summary' => 'Not used'];
                        $isCoreCategory = $isTransactionCategory && in_array($option->value, $coreCategoryValues, true);
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $option->value }}</strong>
                            @if ($isCoreCategory)
                                <br><span class="hg-muted">Core template category</span>
                            @endif
                        </td>
                        <td>{{ $option->label }}</td>
                        @if ($isTransactionCategory)
                            <td><strong>{{ $option->metadata['voucher_prefix'] ?? '—' }}</strong></td>
                            <td>{{ $option->metadata['money_label'] ?? 'Money Account' }}</td>
                        @endif
                        <td>{{ $option->sort_order }}</td>
                        <td>
                            @if ($optionUsage['count'] > 0)
                                <strong>{{ number_format($optionUsage['count']) }}</strong><br>
                                <span class="hg-muted">{{ $optionUsage['summary'] }}</span>
                            @else
                                <span class="hg-muted">Not used</span>
                            @endif
                        </td>
                        <td>
                            <span class="hg-badge {{ $option->is_active ? 'on' : 'off' }}">
                                {{ $option->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div class="hg-actions">
                                @if($canManage)
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small"
                                    data-setup-open="edit"
                                    data-setup-target="master-data-modal"
                                    data-edit-title="Edit {{ $singularTitle }}"
                                    data-draft-edit-key="master-data-{{ $configuration['section'] }}.edit.{{ $option->id }}"
                                    data-update-url="{{ route('master.update', [$configuration['section'], $option]) }}"
                                    data-values="{{ json_encode([
                                        'record_id' => $option->id,
                                        'value' => $option->value,
                                        'label' => $option->label,
                                        'money_label' => $option->metadata['money_label'] ?? 'Money Account',
                                        'voucher_prefix' => $option->metadata['voucher_prefix'] ?? '',
                                        'sort_order' => $option->sort_order,
                                        'is_active' => $option->is_active ? '1' : '0',
                                    ]) }}"
                                >Edit</button>
                                @endif

                                @if ($configuration['deletable'] && ! $isCoreCategory)
                                    @if($canDelete)
                                    <form
                                        method="POST"
                                        action="{{ route('master.destroy', [$configuration['section'], $option]) }}"
                                     data-safe-delete-form>
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
                    @endphp
                    <tr class="hg-table-draft-row">
                        <td><strong>{{ $fields['value'] ?? 'Draft' }}</strong><br><span class="hg-muted">{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span></td>
                        <td>{{ $fields['label'] ?? 'Draft '.$singularTitle }}</td>
                        @if ($isTransactionCategory)
                            <td><strong>{{ $fields['voucher_prefix'] ?? '—' }}</strong></td>
                            <td>{{ $fields['money_label'] ?? 'Money Account' }}</td>
                        @endif
                        <td>{{ $fields['sort_order'] ?? '—' }}</td>
                        <td><span class="hg-muted">Drafts are not used anywhere.</span></td>
                        <td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
                        <td><div class="hg-actions">@if($canManage) @if($isEditDraft)<button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>@else<button type="button" class="hg-btn hg-btn-small" data-setup-open="create" data-setup-target="master-data-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, ['record_id'=>'','value'=>'','label'=>'','money_label'=>'Money Account','voucher_prefix'=>'','sort_order'=>(($options->max('sort_order') ?? 0) + 10),'is_active'=>'1'])) }}">Continue</button>@endif @endif<form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($canManage)

    <x-accounting.setup-modal
        id="master-data-modal"
        :show="$reopenModal"
        :title="$editingOption ? 'Edit '.$singularTitle : 'Add '.$singularTitle"
        :store-url="$configuration['creatable'] ? route('master.store', $configuration['section']) : '#'"
        :create-title="'Add '.$singularTitle"
    >
        <form
            method="POST"
            action="{{ $editingOption
                ? route('master.update', [$configuration['section'], $editingOption])
                : ($configuration['creatable'] ? route('master.store', $configuration['section']) : '#') }}"
            class="hg-form-grid"
            data-setup-form
            data-draft-form
            data-draft-defer
            data-draft-key-base="master-data-{{ $configuration['section'] }}"
            data-draft-key="{{ $editingOption ? 'master-data-'.$configuration['section'].'.edit.'.$editingOption->id : 'master-data-'.$configuration['section'].'.create' }}"
            data-draft-title="{{ $singularTitle }}"
        >
            @csrf
            <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingOption)>
            <input type="hidden" name="setup_modal" value="master-{{ $configuration['section'] }}">
            <input type="hidden" name="record_id" value="{{ old('record_id') }}">

            <div class="hg-field">
                <label for="master-value">Internal Value <span class="hg-required">*</span></label>
                <input
                    id="master-value"
                    name="value"
                    value="{{ old('value', $editingOption?->value) }}"
                    maxlength="{{ $isTransactionCategory ? 30 : 60 }}"
                    @if (! empty($configuration['value_placeholder']))
                        placeholder="{{ $configuration['value_placeholder'] }}"
                    @endif
                    required
                >
                @error('value')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="hg-field">
                <label for="master-label">Display Label <span class="hg-required">*</span></label>
                <input
                    id="master-label"
                    name="label"
                    value="{{ old('label', $editingOption?->label) }}"
                    maxlength="120"
                    required
                >
                @error('label')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            @if ($isTransactionCategory)
                <div class="hg-field">
                    <label for="master-voucher-prefix">Voucher Prefix <span class="hg-required">*</span></label>
                    <input
                        id="master-voucher-prefix"
                        name="voucher_prefix"
                        value="{{ old('voucher_prefix', $editingOption?->metadata['voucher_prefix'] ?? '') }}"
                        maxlength="10"
                        pattern="[A-Za-z0-9]{2,10}"
                        required
                    >
                    @error('voucher_prefix')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="master-money-label">Money Field Label <span class="hg-required">*</span></label>
                    <input
                        id="master-money-label"
                        name="money_label"
                        value="{{ old('money_label', $editingOption?->metadata['money_label'] ?? 'Money Account') }}"
                        maxlength="120"
                        required
                    >
                    @error('money_label')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>
            @endif

            <div class="hg-field">
                <label for="master-sort-order">Sort Order <span class="hg-required">*</span></label>
                <input
                    id="master-sort-order"
                    type="number"
                    min="0"
                    max="65535"
                    name="sort_order"
                    value="{{ old('sort_order', $editingOption?->sort_order ?? 10) }}"
                    required
                >
                @error('sort_order')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="hg-field full">
                <input type="hidden" name="is_active" value="0">
                <label class="hg-checkbox-label" for="master-active">
                    <input
                        id="master-active"
                        type="checkbox"
                        name="is_active"
                        value="1"
                        @checked((bool) old('is_active', $editingOption?->is_active ?? true))
                    >
                    Active
                </label>
                @error('is_active')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            @error('master_data')
                <div class="hg-field full"><small class="hg-field-error">{{ $message }}</small></div>
            @enderror

            <div class="hg-field full">
                <x-accounting.form-actions :submit-label="'Save '.$singularTitle" />
            </div>
        </form>
    </x-accounting.setup-modal>

    @endif
</x-layouts::accounting>
