@php
    $modalRecordId = (int) old('record_id', 0);
    $editingRecord = $modalRecordId > 0 ? $financialYears->firstWhere('id', $modalRecordId) : null;
    $addOnlyMode = (bool) ($addOnlyMode ?? false);
    $reopenModal = old('setup_modal') === 'financial-year' || $addOnlyMode;
    $canManage = auth()->user()?->canAccounting('financial_years.manage') ?? false;
    $canDelete = $canManage && (auth()->user()?->canDeleteAccountingRecords() ?? false);
    $draftRows = \App\Support\VisibleFormDrafts::forBase('financial-years');
@endphp

<x-layouts::accounting title="Financial Years">
    <div class="hg-page-header"><div><div class="hg-page-kicker">Company Setup Master</div><h1>Financial Years</h1><p>Control open accounting periods, current year selection, and posting lock dates.</p></div>
        @if($canManage)<button class="hg-btn hg-btn-primary" type="button" data-setup-open="create" data-setup-target="financial-year-modal" data-defaults="{{ json_encode(['record_id'=>'','status'=>'open','is_current'=>'0','is_active'=>'1']) }}">+ Add Financial Year</button>@endif
    </div>

    <div class="hg-info" style="margin-bottom:16px">Transactions can only be posted inside an active Open Financial Year. A lock date prevents changes on or before that date.</div>

    @if($financialYears->isEmpty() && $draftRows->isEmpty())<div class="hg-empty">{{ $addOnlyMode ? 'You may add records, but your role is not allowed to view this list.' : 'No Financial Years found.' }}</div>
    @else<div class="hg-table-wrap"><table class="hg-table"><thead><tr><th>Name</th><th>Date Range</th><th>Lock Date</th><th>Transactions</th><th>Current</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @foreach($financialYears as $record)<tr>
        <td><strong>{{ $record->name }}</strong></td><td>{{ $record->start_date->format('d M Y') }} — {{ $record->end_date->format('d M Y') }}</td><td>{{ $record->lock_date?->format('d M Y') ?? 'Not locked' }}</td><td>{{ number_format($transactionUsage[$record->id] ?? 0) }}</td>
        <td><span class="hg-badge {{ $record->is_current ? 'sales' : 'off' }}">{{ $record->is_current ? 'Current' : 'No' }}</span></td>
        <td><span class="hg-badge {{ $record->is_active && $record->status === 'open' ? 'on' : 'off' }}">{{ $statusOptions[$record->status] ?? ucfirst($record->status) }} / {{ $record->is_active ? 'Active' : 'Inactive' }}</span></td>
        <td><div class="hg-actions">
            @if($canManage)<button class="hg-btn hg-btn-small" type="button" data-setup-open="edit" data-setup-target="financial-year-modal" data-edit-title="Edit Financial Year" data-draft-edit-key="financial-years.edit.{{ $record->id }}" data-update-url="{{ route('master.financial-years.update', $record) }}" data-values="{{ json_encode(['record_id'=>$record->id,'name'=>$record->name,'start_date'=>$record->start_date->format('Y-m-d'),'end_date'=>$record->end_date->format('Y-m-d'),'lock_date'=>$record->lock_date?->format('Y-m-d'),'status'=>$record->status,'is_current'=>$record->is_current?'1':'0','is_active'=>$record->is_active?'1':'0']) }}">Edit</button>@endif
            @if($canDelete)<form method="POST" action="{{ route('master.financial-years.destroy', $record) }}" data-safe-delete-form>@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button></form>@endif
        </div></td>
    </tr>@endforeach
    @foreach($draftRows as $draft)@php $fields=\App\Support\VisibleFormDrafts::fields($draft); $isEditDraft=\App\Support\VisibleFormDrafts::isEdit($draft); @endphp<tr class="hg-table-draft-row">
        <td><strong>{{ $fields['name'] ?? 'Draft Financial Year' }}</strong><br><span class="hg-muted">{{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new record' }}</span></td><td>{{ ($fields['start_date'] ?? '—').' — '.($fields['end_date'] ?? '—') }}</td><td>{{ $fields['lock_date'] ?? 'Not locked' }}</td><td><span class="hg-muted">Drafts are not used.</span></td><td><span class="hg-badge off">No</span></td><td><span class="hg-badge draft">Draft</span><br><small>{{ $draft->updated_at?->diffForHumans() }}</small></td>
        <td><div class="hg-actions">@if($canManage) @if($isEditDraft)<button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>@else<button class="hg-btn hg-btn-small" type="button" data-setup-open="create" data-setup-target="financial-year-modal" data-defaults="{{ json_encode(\App\Support\VisibleFormDrafts::values($draft, ['record_id'=>'','status'=>'open','is_current'=>'0','is_active'=>'1'])) }}">Continue</button>@endif @endif<form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form></div></td>
    </tr>@endforeach
    </tbody></table></div>@endif

    @if($canManage)
    <x-accounting.setup-modal id="financial-year-modal" :show="$reopenModal" :title="$editingRecord ? 'Edit Financial Year' : 'Add Financial Year'" :store-url="route('master.financial-years.store')" create-title="Add Financial Year">
        <form method="POST" action="{{ $editingRecord ? route('master.financial-years.update', $editingRecord) : route('master.financial-years.store') }}" class="hg-form-grid" data-setup-form data-draft-form data-draft-defer data-draft-key-base="financial-years" data-draft-key="{{ $editingRecord ? 'financial-years.edit.'.$editingRecord->id : 'financial-years.create' }}" data-draft-title="Financial Year">
            @csrf<input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingRecord)><input type="hidden" name="setup_modal" value="financial-year"><input type="hidden" name="record_id" value="{{ old('record_id') }}">
            <div class="hg-field full"><label for="fy-name">Name <span class="hg-required">*</span></label><input id="fy-name" name="name" value="{{ old('name', $editingRecord?->name) }}" maxlength="100" placeholder="FY 2026-2027" required>@error('name')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field"><label for="fy-start">Start Date <span class="hg-required">*</span></label><input id="fy-start" type="date" name="start_date" value="{{ old('start_date', $editingRecord?->start_date?->format('Y-m-d')) }}" required>@error('start_date')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field"><label for="fy-end">End Date <span class="hg-required">*</span></label><input id="fy-end" type="date" name="end_date" value="{{ old('end_date', $editingRecord?->end_date?->format('Y-m-d')) }}" required>@error('end_date')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field"><label for="fy-lock">Lock Date</label><input id="fy-lock" type="date" name="lock_date" value="{{ old('lock_date', $editingRecord?->lock_date?->format('Y-m-d')) }}">@error('lock_date')<small class="hg-field-error">{{ $message }}</small>@enderror</div>
            <div class="hg-field"><label for="fy-status">Status</label><select id="fy-status" name="status" required>@foreach($statusOptions as $value=>$label)<option value="{{ $value }}" @selected(old('status', $editingRecord?->status ?? 'open') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="hg-field"><input type="hidden" name="is_current" value="0"><label class="hg-checkbox-label"><input type="checkbox" name="is_current" value="1" @checked((bool) old('is_current', $editingRecord?->is_current ?? false))> Current Financial Year</label></div>
            <div class="hg-field"><input type="hidden" name="is_active" value="0"><label class="hg-checkbox-label"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $editingRecord?->is_active ?? true))> Active</label></div>
            <div class="hg-field full"><x-accounting.form-actions submit-label="Save Financial Year" /></div>
        </form>
    </x-accounting.setup-modal>
    @endif
</x-layouts::accounting>
