@php
    $modalRecordId = (int) old('record_id', 0);
    $editingSequence = $modalRecordId > 0 ? $sequences->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'voucher-sequence' || $addOnlyMode;
    $formMode = old('form_mode', $editingSequence ? 'edit' : 'create');
    $isCreateMode = $formMode === 'create';
    $canManage = auth()->user()?->canAccounting('voucher_numbering.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('voucher-sequences');
@endphp

<x-layouts::accounting title="Voucher Numbering">
    <div class="hg-page-header">
        <div>
            <div class="hg-page-kicker">Master / Transaction Setup</div>
            <h1>Voucher Numbering</h1>
        </div>

        @if ($availableCategories->isNotEmpty())
            @if($canManage)
            <button
                type="button"
                class="hg-btn hg-btn-primary"
                data-setup-open="create"
                data-setup-target="voucher-sequence-modal"
                data-defaults="{{ json_encode([
                    'form_mode' => 'create',
                    'record_id' => '',
                    'category' => '',
                    'prefix' => '',
                    'next_number' => 1,
                    'padding' => 4,
                    'is_active' => '1',
                ]) }}"
            >+ Add Voucher Numbering</button>
            @endif
        @else
            <div class="hg-actions">
                <button type="button" class="hg-btn hg-btn-primary" disabled>+ Add Voucher Numbering</button>
                <a class="hg-btn" href="{{ route('master.index', 'transaction-categories') }}">Add Transaction Type First</a>
            </div>
        @endif
    </div>

    <div class="hg-notice">
        <strong>Safe relationship handling:</strong> if a transaction category is deleted, its voucher sequence remains in the database as inactive and unlinked. Edit the sequence, choose another active category, and reactivate it.
    </div>
    <div class="hg-spacer"></div>

    @if ($sequences->isEmpty() && $draftRows->isEmpty())
        <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No voucher numbering has been configured.' }}</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Category</th>
                    <th>Prefix</th>
                    <th>Next Number</th>
                    <th>Number Length</th>
                    <th>Next Voucher Preview</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($sequences as $sequence)
                    <tr>
                        <td>
                            @if ($sequence->category)
                                <strong>{{ $categoryLabels[$sequence->category] ?? $sequence->category }}</strong><br>
                                <span class="hg-muted">{{ $sequence->category }}</span>
                            @else
                                <strong class="hg-required">Relationship removed</strong><br>
                                <span class="hg-muted">Edit and select a transaction category</span>
                            @endif
                        </td>
                        <td><span class="hg-code-chip">{{ $sequence->prefix }}</span></td>
                        <td>{{ number_format($sequence->next_number) }}</td>
                        <td>{{ $sequence->padding }} digits</td>
                        <td><strong>{{ $sequence->prefix }}-{{ str_pad((string) $sequence->next_number, $sequence->padding, '0', STR_PAD_LEFT) }}</strong></td>
                        <td><span class="hg-badge {{ $sequence->is_active ? 'on' : 'off' }}">{{ $sequence->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="hg-actions">
                                @if($canManage)
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small"
                                    data-setup-open="edit"
                                    data-setup-target="voucher-sequence-modal"
                                    data-edit-title="Edit Voucher Numbering"
                                    data-draft-edit-key="voucher-sequences.edit.{{ $sequence->id }}"
                                    data-update-url="{{ route('master.voucher-sequences.update', $sequence) }}"
                                    data-values="{{ json_encode([
                                        'form_mode' => 'edit',
                                        'record_id' => $sequence->id,
                                        'category' => $sequence->category,
                                        'prefix' => $sequence->prefix,
                                        'next_number' => $sequence->next_number,
                                        'padding' => $sequence->padding,
                                        'is_active' => $sequence->is_active ? '1' : '0',
                                    ]) }}"
                                >Edit</button>
                                @endif
                                @if($canDelete)
                                <form method="POST" action="{{ route('master.voucher-sequences.destroy', $sequence) }}" data-safe-delete-form>
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
                        <td><strong>{{ $categoryLabels[$fields['category'] ?? ''] ?? ($fields['category'] ?? 'Draft Category') }}</strong><br><span class="hg-muted">{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span></td>
                        <td><span class="hg-code-chip">{{ $fields['prefix'] ?? '—' }}</span></td>
                        <td>{{ number_format((int) ($fields['next_number'] ?? 1)) }}</td>
                        <td>{{ $fields['padding'] ?? 4 }} digits</td>
                        <td><strong>{{ ($fields['prefix'] ?? 'DRAFT') }}-{{ str_pad((string) ($fields['next_number'] ?? 1), (int) ($fields['padding'] ?? 4), '0', STR_PAD_LEFT) }}</strong></td>
                        <td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
                        <td><div class="hg-actions">@if($canManage) @if($isEditDraft)<button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>@else<button type="button" class="hg-btn hg-btn-small" data-setup-open="create" data-setup-target="voucher-sequence-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, ['form_mode'=>'create','record_id'=>'','category'=>'','prefix'=>'','next_number'=>1,'padding'=>4,'is_active'=>'1'])) }}">Continue</button>@endif @endif<form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($canManage)

    <x-accounting.setup-modal
        id="voucher-sequence-modal"
        :show="$reopenModal"
        :title="$editingSequence ? 'Edit Voucher Numbering' : 'Add Voucher Numbering'"
        :store-url="route('master.voucher-sequences.store')"
        create-title="Add Voucher Numbering"
    >
        <form
            method="POST"
            action="{{ $editingSequence ? route('master.voucher-sequences.update', $editingSequence) : route('master.voucher-sequences.store') }}"
            class="hg-form-grid"
            data-setup-form
            data-draft-form
            data-draft-defer
            data-draft-key-base="voucher-sequences"
            data-draft-key="{{ $editingSequence ? 'voucher-sequences.edit.'.$editingSequence->id : 'voucher-sequences.create' }}"
            data-draft-title="Voucher Numbering"
        >
            @csrf
            <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingSequence)>
            <input type="hidden" name="setup_modal" value="voucher-sequence">
            <input type="hidden" name="form_mode" value="{{ $formMode }}">
            <input type="hidden" name="record_id" value="{{ old('record_id') }}">

            <div class="hg-field full">
                <label for="sequence-category">Transaction Type <span class="hg-required">*</span></label>
                <select id="sequence-category" name="category" required>
                    <option value="">Select transaction category</option>
                    @foreach ($transactionCategories as $categoryOption)
                        <option value="{{ $categoryOption->value }}" @selected(old('category', $editingSequence?->category) === $categoryOption->value)>{{ $categoryOption->label }} ({{ $categoryOption->value }})</option>
                    @endforeach
                </select>
                @error('category')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="hg-field">
                <label for="sequence-prefix">Prefix <span class="hg-required">*</span></label>
                <input id="sequence-prefix" name="prefix" value="{{ old('prefix', $editingSequence?->prefix) }}" minlength="2" maxlength="10" placeholder="Uppercase letters, numbers, hyphens, and underscores only." required>
                @error('prefix')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="hg-field">
                <label for="sequence-next-number">Next Number <span class="hg-required">*</span></label>
                <input id="sequence-next-number" type="number" min="1" name="next_number" value="{{ old('next_number', $editingSequence?->next_number ?? 1) }}" required>
                @error('next_number')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="hg-field">
                <label for="sequence-padding">Number Length <span class="hg-required">*</span></label>
                <select id="sequence-padding" name="padding" required>
                    @for ($padding = 2; $padding <= 10; $padding++)
                        <option value="{{ $padding }}" @selected((int) old('padding', $editingSequence?->padding ?? 4) === $padding)>{{ $padding }} digits</option>
                    @endfor
                </select>
                @error('padding')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="hg-field">
                <input type="hidden" name="is_active" value="0">
                <label class="hg-checkbox-label" for="sequence-active">
                    <input id="sequence-active" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $editingSequence?->is_active ?? true))>
                    Active
                </label>
                @error('is_active')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="hg-field full"><x-accounting.form-actions submit-label="Save Numbering" /></div>
        </form>
    </x-accounting.setup-modal>

    @endif
</x-layouts::accounting>
