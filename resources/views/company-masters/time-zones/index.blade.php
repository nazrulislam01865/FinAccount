@php
    $modalRecordId = (int) old('record_id', 0);
    $editingRecord = $modalRecordId > 0 ? $timeZones->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'time-zone' || $addOnlyMode;
    $canManage = auth()->user()?->canAccounting('time_zones.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('time-zones');
@endphp

<x-layouts::accounting title="Time Zones">
    <div class="hg-page-header"><div><div class="hg-page-kicker">Company Setup Master</div><h1>Time Zones</h1><p>Company time zones applied to transaction defaults and displayed timestamps.</p></div>
        @if($canManage)<button class="hg-btn hg-btn-primary" type="button" data-setup-open="create" data-setup-target="time-zone-modal" data-defaults="{{ json_encode(['record_id'=>'','utc_offset'=>'UTC+06:00','php_timezone'=>'Asia/Dhaka','sort_order'=>'10','is_default'=>'0','is_active'=>'1']) }}">+ Add Time Zone</button>@endif
    </div>

    @if($timeZones->isEmpty() && $draftRows->isEmpty())<div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No Time Zones found.' }}</div>
    @else<div class="hg-table-wrap"><table class="hg-table"><thead><tr><th>Code</th><th>Name</th><th>UTC Offset</th><th>PHP Time Zone</th><th>Usage</th><th>Default</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @foreach($timeZones as $record)<tr>
        <td><strong>{{ $record->code }}</strong></td><td>{{ $record->name }}</td><td>{{ $record->utc_offset }}</td><td>{{ $record->php_timezone }}</td><td>{{ number_format($usage[$record->id] ?? 0) }}</td>
        <td><span class="hg-badge {{ $record->is_default ? 'sales' : 'off' }}">{{ $record->is_default ? 'Default' : 'No' }}</span></td><td><span class="hg-badge {{ $record->is_active ? 'on' : 'off' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span></td>
        <td><div class="hg-actions">
            @if($canManage)<button class="hg-btn hg-btn-small" type="button" data-setup-open="edit" data-setup-target="time-zone-modal" data-edit-title="Edit Time Zone" data-draft-edit-key="time-zones.edit.{{ $record->id }}" data-update-url="{{ route('master.time-zones.update', $record) }}" data-values="{{ json_encode(['record_id'=>$record->id,'code'=>$record->code,'name'=>$record->name,'utc_offset'=>$record->utc_offset,'php_timezone'=>$record->php_timezone,'sort_order'=>$record->sort_order,'is_default'=>$record->is_default?'1':'0','is_active'=>$record->is_active?'1':'0']) }}">Edit</button>@endif
            @if($canDelete)<form method="POST" action="{{ route('master.time-zones.destroy', $record) }}" data-safe-delete-form>@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button></form>@endif
        </div></td>
    </tr>@endforeach
    @foreach($draftRows as $draft)@php $fields=\App\Support\VisibleFormDrafts::fields($draft); $isEditDraft=\App\Support\VisibleFormDrafts::isEdit($draft); @endphp<tr class="hg-table-draft-row">
        <td><strong>{{ $fields['code'] ?? 'Draft' }}</strong><br><span class="hg-muted">{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span></td><td>{{ $fields['name'] ?? 'Draft Time Zone' }}</td><td>{{ $fields['utc_offset'] ?? '—' }}</td><td>{{ $fields['php_timezone'] ?? '—' }}</td><td><span class="hg-muted">Drafts are not used.</span></td><td><span class="hg-badge off">No</span></td><td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
        <td><div class="hg-actions">@if($canManage) @if($isEditDraft)<button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>@else<button class="hg-btn hg-btn-small" type="button" data-setup-open="create" data-setup-target="time-zone-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, ['record_id'=>'','utc_offset'=>'UTC+06:00','php_timezone'=>'Asia/Dhaka','sort_order'=>'10','is_default'=>'0','is_active'=>'1'])) }}">Continue</button>@endif @endif<form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form></div></td>
    </tr>@endforeach
    </tbody></table></div>@endif

    @if($canManage)
    <x-accounting.setup-modal id="time-zone-modal" :show="$reopenModal" :title="$editingRecord ? 'Edit Time Zone' : 'Add Time Zone'" :store-url="route('master.time-zones.store')" create-title="Add Time Zone">
        <form method="POST" action="{{ $editingRecord ? route('master.time-zones.update', $editingRecord) : route('master.time-zones.store') }}" class="hg-form-grid" data-setup-form data-draft-form data-draft-defer data-draft-key-base="time-zones" data-draft-key="{{ $editingRecord ? 'time-zones.edit.'.$editingRecord->id : 'time-zones.create' }}" data-draft-title="Time Zone">
            @csrf<input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingRecord)><input type="hidden" name="setup_modal" value="time-zone"><input type="hidden" name="record_id" value="{{ old('record_id') }}">
            <div class="hg-field"><label for="tz-code">Code <span class="hg-required">*</span></label><input id="tz-code" name="code" value="{{ old('code', $editingRecord?->code) }}" maxlength="50" required>@error('code')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field"><label for="tz-name">Name <span class="hg-required">*</span></label><input id="tz-name" name="name" value="{{ old('name', $editingRecord?->name) }}" maxlength="120" required></div>
            <div class="hg-field"><label for="tz-offset">UTC Offset <span class="hg-required">*</span></label><input id="tz-offset" name="utc_offset" value="{{ old('utc_offset', $editingRecord?->utc_offset ?? 'UTC+06:00') }}" placeholder="UTC+06:00" required>@error('utc_offset')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field"><label for="tz-php">PHP Time Zone <span class="hg-required">*</span></label><input id="tz-php" name="php_timezone" value="{{ old('php_timezone', $editingRecord?->php_timezone ?? 'Asia/Dhaka') }}" placeholder="Asia/Dhaka" required>@error('php_timezone')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field"><label for="tz-sort">Sort Order</label><input id="tz-sort" type="number" name="sort_order" min="0" max="65535" value="{{ old('sort_order', $editingRecord?->sort_order ?? 10) }}" required></div>
            <div class="hg-field"><input type="hidden" name="is_default" value="0"><label class="hg-checkbox-label"><input type="checkbox" name="is_default" value="1" @checked((bool) old('is_default', $editingRecord?->is_default ?? false))> Default value</label></div>
            <div class="hg-field full"><input type="hidden" name="is_active" value="0"><label class="hg-checkbox-label"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $editingRecord?->is_active ?? true))> Active</label></div>
            <div class="hg-field full"><x-accounting.form-actions submit-label="Save Time Zone" /></div>
        </form>
    </x-accounting.setup-modal>
    @endif
</x-layouts::accounting>
