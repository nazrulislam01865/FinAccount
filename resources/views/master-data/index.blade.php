@php
    $modalRecordId = (int) old('record_id', 0);
    $editingOption = $modalRecordId > 0 ? $options->firstWhere('id', $modalRecordId) : null;
    $reopenModal = old('setup_modal') === 'master-'.$configuration['section'];
    $isTransactionCategory = $configuration['group'] === \App\Models\AccountingOption::GROUP_TRANSACTION_CATEGORY;
    $coreCategoryValues = $configuration['core_values'] ?? [];
    $singularTitle = str($configuration['title'])->singular();
@endphp

<x-layouts::accounting :title="$configuration['title']">
    <div class="hg-page-header">
        <div>
            <div class="hg-page-kicker">Master / {{ $configuration['menu_group'] }}</div>
            <h1>{{ $configuration['title'] }}</h1>
            <p>{{ $configuration['description'] }}</p>
        </div>

        @if ($configuration['creatable'])
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
                    'sort_order' => (($options->max('sort_order') ?? 0) + 10),
                    'is_active' => '1',
                ]) }}"
            >+ Add {{ $singularTitle }}</button>
        @endif
    </div>

    <div class="hg-info hg-master-notice">
        @if ($isTransactionCategory)
            <strong>Database-driven transaction categories.</strong>
            You may add custom categories for Accounting Rules, Transaction Heads, Transaction Entry, and Voucher Numbering.
            The core internal values Sales, Payment, and Liability remain protected because the template reports depend on them. Custom categories use dependency-aware safe deletion.
        @else
            <strong>Database-driven dropdown.</strong>
            Active values become available immediately in the related setup forms. Delete first shows every dependency; after confirmation, the value is removed permanently and dependent records are detached and made inactive.
        @endif
    </div>
    <div class="hg-spacer"></div>

    @if ($options->isEmpty())
        <div class="hg-empty">No master values found.</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Internal Value</th>
                    <th>Display Label</th>
                    @if ($isTransactionCategory)
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
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small"
                                    data-setup-open="edit"
                                    data-setup-target="master-data-modal"
                                    data-edit-title="Edit {{ $singularTitle }}"
                                    data-update-url="{{ route('master.update', [$configuration['section'], $option]) }}"
                                    data-values="{{ json_encode([
                                        'record_id' => $option->id,
                                        'value' => $option->value,
                                        'label' => $option->label,
                                        'money_label' => $option->metadata['money_label'] ?? 'Money Account',
                                        'sort_order' => $option->sort_order,
                                        'is_active' => $option->is_active ? '1' : '0',
                                    ]) }}"
                                >Edit</button>

                                @if ($configuration['deletable'] && ! $isCoreCategory)
                                    <form
                                        method="POST"
                                        action="{{ route('master.destroy', [$configuration['section'], $option]) }}"
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
                </tbody>
            </table>
        </div>
    @endif

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
                    required
                >
                @if ($isTransactionCategory)
                    <small class="hg-field-help">Use a unique business value. Core values Sales, Payment, and Liability cannot be renamed.</small>
                @else
                    <small class="hg-field-help">Use letters, numbers, spaces, hyphens, or underscores.</small>
                @endif
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
                    <label for="master-money-label">Money Field Label <span class="hg-required">*</span></label>
                    <input
                        id="master-money-label"
                        name="money_label"
                        value="{{ old('money_label', $editingOption?->metadata['money_label'] ?? 'Money Account') }}"
                        maxlength="120"
                        required
                    >
                    <small class="hg-field-help">Example: Receive In, Pay Through, or Money Account.</small>
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
                <button class="hg-btn hg-btn-primary" type="submit">Save</button>
            </div>
        </form>
    </x-accounting.setup-modal>
</x-layouts::accounting>
