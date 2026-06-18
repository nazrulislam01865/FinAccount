@php
    $modalRecordId = (int) old('record_id', 0);
    $editingRecord = $modalRecordId > 0 ? $businessTypes->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'business-type' || $addOnlyMode;
    $canManage = auth()->user()?->canAccounting('business_types.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('business-types');
@endphp

<x-layouts::accounting title="Business Types">
    <div class="hg-page-header"><div><div class="hg-page-kicker">Company Setup Master</div><h1>Business Types</h1><p>Reusable company classifications used by Company Setup.</p></div>
        @if($canManage)<button class="hg-btn hg-btn-primary" type="button" data-setup-open="create" data-setup-target="business-type-modal" data-defaults="{{ json_encode(['record_id'=>'','sort_order'=>'10','is_default'=>'0','is_active'=>'1']) }}">+ Add Business Type</button>@endif
    </div>

    @if($businessTypes->isEmpty() && $draftRows->isEmpty())
        <div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No Business Types found.' }}</div>
    @else
        <div class="hg-table-wrap"><table class="hg-table"><thead><tr><th>Code</th><th>Name</th><th>Description</th><th>Usage</th><th>Default</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @foreach($businessTypes as $record)
            <tr>
                <td><strong>{{ $record->code }}</strong></td><td>{{ $record->name }}</td><td>{{ $record->description ?: '—' }}</td>
                <td>{{ number_format($usage[$record->id] ?? 0) }}</td>
                <td><span class="hg-badge {{ $record->is_default ? 'sales' : 'off' }}">{{ $record->is_default ? 'Default' : 'No' }}</span></td>
                <td><span class="hg-badge {{ $record->is_active ? 'on' : 'off' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td><div class="hg-actions">
                    @if($canManage)<button class="hg-btn hg-btn-small" type="button" data-setup-open="edit" data-setup-target="business-type-modal" data-edit-title="Edit Business Type" data-draft-edit-key="business-types.edit.{{ $record->id }}" data-update-url="{{ route('master.business-types.update', $record) }}" data-values="{{ json_encode(['record_id'=>$record->id,'code'=>$record->code,'name'=>$record->name,'description'=>$record->description,'sort_order'=>$record->sort_order,'is_default'=>$record->is_default?'1':'0','is_active'=>$record->is_active?'1':'0']) }}">Edit</button>@endif
                    @if($canDelete)<form method="POST" action="{{ route('master.business-types.destroy', $record) }}" data-safe-delete-form>@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button></form>@endif
                </div></td>
            </tr>
        @endforeach
        @foreach($draftRows as $draft)@php $fields=\App\Support\VisibleFormDrafts::fields($draft); $isEditDraft=\App\Support\VisibleFormDrafts::isEdit($draft); @endphp<tr class="hg-table-draft-row">
            <td><strong>{{ $fields['code'] ?? 'Draft' }}</strong><br><span class="hg-muted">{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span></td><td>{{ $fields['name'] ?? 'Draft Business Type' }}</td><td>{{ $fields['description'] ?? '—' }}</td>
            <td><span class="hg-muted">Drafts are not used.</span></td><td><span class="hg-badge off">No</span></td><td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
            <td><div class="hg-actions">@if($canManage) @if($isEditDraft)<button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>@else<button class="hg-btn hg-btn-small" type="button" data-setup-open="create" data-setup-target="business-type-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, ['record_id'=>'','sort_order'=>'10','is_default'=>'0','is_active'=>'1'])) }}">Continue</button>@endif @endif<form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form></div></td>
        </tr>@endforeach
        </tbody></table></div>
    @endif

    @if($canManage)
    <x-accounting.setup-modal id="business-type-modal" :show="$reopenModal" :title="$editingRecord ? 'Edit Business Type' : 'Add Business Type'" :store-url="route('master.business-types.store')" create-title="Add Business Type">
        <form method="POST" action="{{ $editingRecord ? route('master.business-types.update', $editingRecord) : route('master.business-types.store') }}" class="hg-form-grid" data-setup-form data-draft-form data-draft-defer data-draft-key-base="business-types" data-draft-key="{{ $editingRecord ? 'business-types.edit.'.$editingRecord->id : 'business-types.create' }}" data-draft-title="Business Type">
            @csrf<input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingRecord)><input type="hidden" name="setup_modal" value="business-type"><input type="hidden" name="record_id" value="{{ old('record_id') }}">
            <div class="hg-field"><label for="bt-code">Code <span class="hg-required">*</span></label><input id="bt-code" name="code" value="{{ old('code', $editingRecord?->code) }}" maxlength="30" required>@error('code')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field"><label for="bt-name">Name <span class="hg-required">*</span></label><input id="bt-name" name="name" value="{{ old('name', $editingRecord?->name) }}" maxlength="120" required>@error('name')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field full"><label for="bt-description">Description</label><textarea id="bt-description" name="description" maxlength="1000">{{ old('description', $editingRecord?->description) }}</textarea>@error('description')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field"><label for="bt-sort">Sort Order</label><input id="bt-sort" type="number" name="sort_order" min="0" max="65535" value="{{ old('sort_order', $editingRecord?->sort_order ?? 10) }}" required></div>
            <div class="hg-field"><input type="hidden" name="is_default" value="0"><label class="hg-checkbox-label"><input type="checkbox" name="is_default" value="1" @checked((bool) old('is_default', $editingRecord?->is_default ?? false))> Default value</label></div>
            <div class="hg-field full"><input type="hidden" name="is_active" value="0"><label class="hg-checkbox-label"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $editingRecord?->is_active ?? true))> Active</label></div>
            <div class="hg-field full"><x-accounting.form-actions submit-label="Save Business Type" /></div>
        </form>
    </x-accounting.setup-modal>
    @endif
</x-layouts::accounting>
