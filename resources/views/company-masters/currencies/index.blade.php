@php
    $modalRecordId = (int) old('record_id', 0);
    $editingRecord = $modalRecordId > 0 ? $currencies->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'currency' || $addOnlyMode;
    $canManage = auth()->user()?->canAccounting('currencies.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('currencies');
@endphp

<x-layouts::accounting title="Currencies">
    <div class="hg-page-header"><div><div class="hg-page-kicker">Company Setup Master</div><h1>Currencies</h1><p>Currency codes, symbols, and decimal precision used across accounting screens.</p></div>
        @if($canManage)<button class="hg-btn hg-btn-primary" type="button" data-setup-open="create" data-setup-target="currency-modal" data-defaults="{{ json_encode(['record_id'=>'','decimal_places'=>'2','sort_order'=>'10','is_default'=>'0','is_active'=>'1']) }}">+ Add Currency</button>@endif
    </div>

    @if($currencies->isEmpty() && $draftRows->isEmpty())<div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No Currencies found.' }}</div>
    @else<div class="hg-table-wrap"><table class="hg-table"><thead><tr><th>Code</th><th>Name</th><th>Symbol</th><th>Decimals</th><th>Usage</th><th>Default</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @foreach($currencies as $record)<tr>
        <td><strong>{{ $record->code }}</strong></td><td>{{ $record->name }}</td><td>{{ $record->symbol ?: '—' }}</td><td>{{ $record->decimal_places }}</td><td>{{ number_format($usage[$record->id] ?? 0) }}</td>
        <td><span class="hg-badge {{ $record->is_default ? 'sales' : 'off' }}">{{ $record->is_default ? 'Default' : 'No' }}</span></td><td><span class="hg-badge {{ $record->is_active ? 'on' : 'off' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span></td>
        <td><div class="hg-actions">
            @if($canManage)<button class="hg-btn hg-btn-small" type="button" data-setup-open="edit" data-setup-target="currency-modal" data-edit-title="Edit Currency" data-draft-edit-key="currencies.edit.{{ $record->id }}" data-update-url="{{ route('master.currencies.update', $record) }}" data-values="{{ json_encode(['record_id'=>$record->id,'code'=>$record->code,'name'=>$record->name,'symbol'=>$record->symbol,'decimal_places'=>$record->decimal_places,'sort_order'=>$record->sort_order,'is_default'=>$record->is_default?'1':'0','is_active'=>$record->is_active?'1':'0']) }}">Edit</button>@endif
            @if($canDelete)<form method="POST" action="{{ route('master.currencies.destroy', $record) }}" data-safe-delete-form>@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button></form>@endif
        </div></td>
    </tr>@endforeach
    @foreach($draftRows as $draft)@php $fields=\App\Support\VisibleFormDrafts::fields($draft); $isEditDraft=\App\Support\VisibleFormDrafts::isEdit($draft); @endphp<tr class="hg-table-draft-row">
        <td><strong>{{ $fields['code'] ?? 'Draft' }}</strong><br><span class="hg-muted">{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span></td><td>{{ $fields['name'] ?? 'Draft Currency' }}</td><td>{{ $fields['symbol'] ?? '—' }}</td><td>{{ $fields['decimal_places'] ?? '—' }}</td><td><span class="hg-muted">Drafts are not used.</span></td><td><span class="hg-badge off">No</span></td><td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
        <td><div class="hg-actions">@if($canManage) @if($isEditDraft)<button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>@else<button class="hg-btn hg-btn-small" type="button" data-setup-open="create" data-setup-target="currency-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, ['record_id'=>'','decimal_places'=>'2','sort_order'=>'10','is_default'=>'0','is_active'=>'1'])) }}">Continue</button>@endif @endif<form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form></div></td>
    </tr>@endforeach
    </tbody></table></div>@endif

    @if($canManage)
    <x-accounting.setup-modal id="currency-modal" :show="$reopenModal" :title="$editingRecord ? 'Edit Currency' : 'Add Currency'" :store-url="route('master.currencies.store')" create-title="Add Currency">
        <form method="POST" action="{{ $editingRecord ? route('master.currencies.update', $editingRecord) : route('master.currencies.store') }}" class="hg-form-grid" data-setup-form data-draft-form data-draft-defer data-draft-key-base="currencies" data-draft-key="{{ $editingRecord ? 'currencies.edit.'.$editingRecord->id : 'currencies.create' }}" data-draft-title="Currency">
            @csrf<input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingRecord)><input type="hidden" name="setup_modal" value="currency"><input type="hidden" name="record_id" value="{{ old('record_id') }}">
            <div class="hg-field"><label for="currency-code">ISO Code <span class="hg-required">*</span></label><input id="currency-code" name="code" value="{{ old('code', $editingRecord?->code) }}" minlength="3" maxlength="3" required>@error('code')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field"><label for="currency-name">Name <span class="hg-required">*</span></label><input id="currency-name" name="name" value="{{ old('name', $editingRecord?->name) }}" maxlength="100" required>@error('name')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field"><label for="currency-symbol">Symbol</label><input id="currency-symbol" name="symbol" value="{{ old('symbol', $editingRecord?->symbol) }}" maxlength="12"></div>
            <div class="hg-field"><label for="currency-decimals">Decimal Places</label><input id="currency-decimals" type="number" name="decimal_places" min="0" max="2" value="{{ old('decimal_places', $editingRecord?->decimal_places ?? 2) }}" required></div>
            <div class="hg-field"><label for="currency-sort">Sort Order</label><input id="currency-sort" type="number" name="sort_order" min="0" max="65535" value="{{ old('sort_order', $editingRecord?->sort_order ?? 10) }}" required></div>
            <div class="hg-field"><input type="hidden" name="is_default" value="0"><label class="hg-checkbox-label"><input type="checkbox" name="is_default" value="1" @checked((bool) old('is_default', $editingRecord?->is_default ?? false))> Default value</label></div>
            <div class="hg-field full"><input type="hidden" name="is_active" value="0"><label class="hg-checkbox-label"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $editingRecord?->is_active ?? true))> Active</label></div>
            <div class="hg-field full"><x-accounting.form-actions submit-label="Save Currency" /></div>
        </form>
    </x-accounting.setup-modal>
    @endif
</x-layouts::accounting>
