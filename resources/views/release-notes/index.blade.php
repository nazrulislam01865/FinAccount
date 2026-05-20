@extends('layouts.app')

@section('title', 'Release Notes | Accounting System')

@section('content')
@php
    $typeBadgeClass = fn (?string $type) => match ($type) {
        'Bug Fix' => 'badge-danger',
        'Report' => 'badge-purple',
        'Configuration' => 'badge-warning',
        'New Feature' => 'badge-primary',
        default => 'badge-cyan',
    };

    $versionBadgeClass = fn (?string $version) => match ($version) {
        'Major' => 'badge-success',
        'Hotfix' => 'badge-danger',
        default => 'badge-neutral',
    };

    $uiBadgeClass = fn (?string $uiFunction) => match ($uiFunction) {
        'UI' => 'badge-cyan',
        'Function' => 'badge-primary',
        default => 'badge-purple',
    };

    $statusBadgeClass = fn (?string $status) => match ($status) {
        'Released' => 'badge-success',
        'In Review' => 'badge-warning',
        default => 'badge-neutral',
    };

    $releaseDates = $releaseItems->pluck('release_date')->filter()->map(fn ($date) => $date->toDateString());
    $defaultFromDate = $releaseDates->min() ?: now()->subDays(7)->toDateString();
    $defaultToDate = $releaseDates->max() ?: now()->toDateString();
    $today = now()->toDateString();
@endphp

<div class="release-page">
<div class="page-title">
    <div>
        <span class="page-label">Release Notes</span>
        <h2>Release Notes</h2>
        <p>Track cloud release items by date, module, UI/function area, release type, impact, owner, and version.</p>
    </div>

    <div class="quick-actions release-actions">
        <button class="btn-outline" type="button" id="releaseExportBtn">⇩ Export CSV</button>
        <button class="btn-ghost" type="button" id="releasePrintBtn">Print</button>
        <button class="btn-primary" type="button" id="releaseNewBtn">+ New Release Item</button>
    </div>
</div>

<div class="card release-banner">
    <div>
        <h3>Release History by Date</h3>
        <p>Multiple release dates can be shown on one page. Each release date appears once as a group header; every finished task is listed below that date.</p>
    </div>

    <div class="release-meta">
        <span class="pill pill-blue">Grouped View</span>
        <span class="pill pill-green">Super Admin Only</span>
        <span class="pill pill-purple">Cloud Release Tracker</span>
    </div>
</div>

<div class="summary-grid release-summary-grid">
    <div class="card summary"><span>Total Items</span><strong id="releaseSumTotal">0</strong><small>Based on current filter</small></div>
    <div class="card summary"><span>Major Release</span><strong class="green-text" id="releaseSumMajor">0</strong><small>Large module/function update</small></div>
    <div class="card summary"><span>Minor Release</span><strong class="blue-text" id="releaseSumMinor">0</strong><small>Small feature or UI update</small></div>
    <div class="card summary"><span>Bug Fix / Hotfix</span><strong class="red-text" id="releaseSumHotfix">0</strong><small>Issue correction</small></div>
    <div class="card summary"><span>Release Dates</span><strong class="purple-text" id="releaseSumDates">0</strong><small>Unique dates in current view</small></div>
</div>

<div class="layout release-layout">
    <div class="left-stack">
        <div class="card filters release-filters" id="releaseFilters">
            <div class="field search-field">
                <label>Search</label>
                <span>⌕</span>
                <input id="releaseSearch" placeholder="Search module, task, function, owner...">
            </div>

            <div>
                <label>From Date</label>
                <input id="releaseFromDate" type="date" value="{{ $defaultFromDate }}">
            </div>

            <div>
                <label>To Date</label>
                <input id="releaseToDate" type="date" value="{{ $defaultToDate }}">
            </div>

            <div>
                <label>Module</label>
                <select id="releaseModuleFilter">
                    <option value="All">All</option>
                    @foreach($modules as $module)
                        <option value="{{ $module }}">{{ $module }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>UI / Function</label>
                <select id="releaseUiFunctionFilter">
                    <option value="All">All</option>
                    @foreach($uiFunctions as $uiFunction)
                        <option value="{{ $uiFunction }}">{{ $uiFunction }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Item Type</label>
                <select id="releaseItemTypeFilter">
                    <option value="All">All</option>
                    @foreach($itemTypes as $itemType)
                        <option value="{{ $itemType }}">{{ $itemType }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Release Version</label>
                <select id="releaseVersionFilter">
                    <option value="All">All</option>
                    @foreach($releaseVersions as $releaseVersion)
                        <option value="{{ $releaseVersion }}">{{ $releaseVersion }}</option>
                    @endforeach
                </select>
            </div>

            <button class="btn-primary" type="button" id="releaseResetBtn">Reset</button>
        </div>

        <div class="card table-card release-table-card">
            <div class="table-head release-table-head">
                <div>
                    <h3>Released Items List</h3>
                    <p id="releaseResultText">Showing release items grouped by date.</p>
                </div>
                <button class="btn-ghost" type="button" data-toast="Release items are already grouped by date.">Group by Date</button>
            </div>

            <div class="table-wrap">
                <table id="releaseItemsTable">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>UI / Function</th>
                            <th>Item Type</th>
                            <th>Task / Item Done</th>
                            <th>User Impact</th>
                            <th>Released By</th>
                            <th>Release Version</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($releaseItems->groupBy(fn ($item) => $item->release_date?->toDateString() ?: 'No Date') as $releaseDate => $itemsForDate)
                            <tr class="release-group" data-release-group data-date="{{ $releaseDate }}" data-label="{{ $releaseDate === 'No Date' ? 'No Date' : \Carbon\Carbon::parse($releaseDate)->format('d M Y') }}">
                                <td colspan="9">
                                    {{ $releaseDate === 'No Date' ? 'No Date' : \Carbon\Carbon::parse($releaseDate)->format('d M Y') }}
                                    — {{ $itemsForDate->count() }} released item(s)
                                    • {{ $itemsForDate->pluck('module')->unique()->implode(', ') }}
                                </td>
                            </tr>

                            @foreach($itemsForDate as $releaseItem)
                                <tr
                                    class="release-row"
                                    data-id="{{ $releaseItem->id }}"
                                    data-date="{{ $releaseItem->release_date?->toDateString() }}"
                                    data-module="{{ e($releaseItem->module) }}"
                                    data-ui-function="{{ e($releaseItem->ui_function) }}"
                                    data-item-type="{{ e($releaseItem->item_type) }}"
                                    data-task="{{ e($releaseItem->task) }}"
                                    data-note="{{ e($releaseItem->note) }}"
                                    data-user-impact="{{ e($releaseItem->user_impact) }}"
                                    data-released-by="{{ e($releaseItem->released_by) }}"
                                    data-release-version="{{ e($releaseItem->release_version) }}"
                                    data-status="{{ e($releaseItem->status) }}"
                                    data-update-url="{{ url('/api/release-notes/' . $releaseItem->id) }}"
                                >
                                    <td class="module strong">{{ $releaseItem->module }}</td>
                                    <td><span class="badge {{ $uiBadgeClass($releaseItem->ui_function) }}">{{ $releaseItem->ui_function }}</span></td>
                                    <td><span class="badge {{ $typeBadgeClass($releaseItem->item_type) }}">{{ $releaseItem->item_type }}</span></td>
                                    <td class="release-task-cell">
                                        <strong>{{ $releaseItem->task }}</strong>
                                        @if($releaseItem->note)
                                            <span>{{ $releaseItem->note }}</span>
                                        @endif
                                    </td>
                                    <td class="impact">{{ $releaseItem->user_impact ?: '—' }}</td>
                                    <td class="owner">{{ $releaseItem->released_by ?: $releaseItem->creator?->name ?: '—' }}</td>
                                    <td><span class="badge {{ $versionBadgeClass($releaseItem->release_version) }}">{{ $releaseItem->release_version }}</span></td>
                                    <td><span class="badge {{ $statusBadgeClass($releaseItem->status) }}">{{ $releaseItem->status }}</span></td>
                                    <td>
                                        <div class="action-cell">
                                            <button class="icon-btn release-edit-btn" type="button" title="Edit">✎</button>

                                            <form
                                                method="POST"
                                                action="{{ url('/release-notes/' . $releaseItem->id) }}"
                                                onsubmit="return confirm('Delete this release item?')"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                        @endforelse

                        <tr id="releaseNoResultsRow" data-empty="true" style="display:none">
                            <td colspan="9" class="empty-state">No release item found for the selected filter.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span id="releaseFooterText">Showing release items</span>
                <div class="pagination">
                    <button class="page-btn" type="button">‹</button>
                    <button class="page-btn active" type="button">1</button>
                    <button class="page-btn" type="button">›</button>
                </div>
            </div>
        </div>
    </div>

    <aside class="right-stack release-form-stack">
        <form
            class="card form-card"
            id="releaseItemForm"
            data-frontend-form
            data-action="{{ route('api.release-notes.store') }}"
            data-create-action="{{ route('api.release-notes.store') }}"
            data-success="Release item saved successfully."
        >
            @csrf

            <div class="panel-head">
                <div>
                    <h3 id="releaseFormTitle">New Release Item</h3>
                    <p class="muted">Only Super Admin can create, edit, and delete release records.</p>
                </div>
                <span class="badge badge-primary" id="releaseFormMode">New</span>
            </div>

            <div class="form-grid">
                <div>
                    <label>Release Date <span class="required">*</span></label>
                    <input type="date" name="release_date" id="releaseDate" value="{{ $today }}" required>
                </div>

                <div>
                    <label>Module <span class="required">*</span></label>
                    <select name="module" id="releaseModule" required>
                        <option value="">Select Module</option>
                        @foreach($modules as $module)
                            <option value="{{ $module }}">{{ $module }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>UI / Function <span class="required">*</span></label>
                    <select name="ui_function" id="releaseUiFunction" required>
                        <option value="">Select</option>
                        @foreach($uiFunctions as $uiFunction)
                            <option value="{{ $uiFunction }}">{{ $uiFunction }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Item Type <span class="required">*</span></label>
                    <select name="item_type" id="releaseItemType" required>
                        <option value="">Select Item Type</option>
                        @foreach($itemTypes as $itemType)
                            <option value="{{ $itemType }}">{{ $itemType }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Task / Item Done <span class="required">*</span></label>
                    <input type="text" name="task" id="releaseTask" maxlength="180" placeholder="Example: Release Notes page added" required>
                </div>

                <div>
                    <label>Task Details</label>
                    <textarea name="note" id="releaseNote" maxlength="2000" placeholder="Short technical or functional note"></textarea>
                </div>

                <div>
                    <label>User Impact</label>
                    <textarea name="user_impact" id="releaseUserImpact" maxlength="2000" placeholder="What changes for the user or admin?"></textarea>
                </div>

                <div>
                    <label>Released By</label>
                    <input type="text" name="released_by" id="releaseBy" maxlength="120" value="{{ auth()->user()?->name }}" placeholder="Team or person name">
                </div>

                <div class="two-col">
                    <div>
                        <label>Version <span class="required">*</span></label>
                        <select name="release_version" id="releaseVersion" required>
                            <option value="">Select</option>
                            @foreach($releaseVersions as $releaseVersion)
                                <option value="{{ $releaseVersion }}">{{ $releaseVersion }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label>Status <span class="required">*</span></label>
                        <select name="status" id="releaseStatus" required>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected($status === 'Released')>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn-ghost" type="button" id="releaseCancelEditBtn" hidden>Cancel Edit</button>
                <button class="btn-primary" type="submit" id="releaseSubmitBtn">Save Release Item</button>
            </div>
        </form>

        <div class="card helper-card">
            <h3>Release Tracking Rule</h3>
            <p>Use one row per finished task. Choose <strong>Major</strong> for large module or accounting-flow changes, <strong>Minor</strong> for small UI/function additions, and <strong>Hotfix</strong> for urgent production fixes.</p>
        </div>
    </aside>
</div>
</div>
@endsection

@push('styles')
<style>
    html,body{overflow-x:hidden}
    .release-page{max-width:100%;overflow-x:hidden}
    .release-page .page-title{align-items:center}
    .quick-actions.release-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;min-width:0}
    .release-banner{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:18px 20px;margin-bottom:16px;background:linear-gradient(135deg,#fff,#f8fbff)}
    .release-banner h3{margin:0 0 5px;font-size:18px}.release-banner p{margin:0;color:var(--muted);font-size:13px;line-height:1.5}.release-meta{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}.pill{display:inline-flex;align-items:center;border-radius:999px;padding:7px 11px;font-size:12px;font-weight:850;white-space:nowrap;background:#f2f4f7;color:#475467}.pill-blue{background:var(--primary-soft);color:var(--primary)}.pill-green{background:var(--success-soft);color:#067647}.pill-purple{background:var(--purple-soft);color:var(--purple)}
    .release-summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:16px}.summary{padding:16px}.summary span{display:block;color:var(--muted);font-size:13px;margin-bottom:8px}.summary strong{font-size:24px;letter-spacing:-.03em}.summary small{display:block;color:var(--muted);font-size:12px;margin-top:6px}.green-text{color:#067647}.red-text{color:#dc2626}.blue-text{color:var(--primary)}.purple-text{color:var(--purple)}
    .release-layout{grid-template-columns:minmax(0,1fr) minmax(360px,390px);gap:22px;max-width:100%;overflow:visible}.release-layout>*{min-width:0}.release-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:14px;padding:16px;align-items:end;max-width:100%;overflow:hidden}.release-filters .search-field{grid-column:span 2;min-width:0}.release-filters .search-field span{top:38px}.release-filters button{width:100%;white-space:nowrap}.release-table-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:18px 20px;border-bottom:1px solid var(--line);background:linear-gradient(135deg,#fff,#f8fbff)}.release-table-head h3{margin:0;font-size:19px}.release-table-head p{margin:5px 0 0;color:var(--muted);font-size:13px}.release-table-card{max-width:100%;overflow:hidden}.release-table-card .table-wrap{max-width:100%;overflow:auto;overscroll-behavior:contain}.release-table-card table{min-width:1180px}.release-table-card thead th,.release-table-card tbody td{padding:14px 15px}.release-group td{background:#eef4ff;color:#1d4ed8;font-weight:900;padding:12px 16px!important}.release-task-cell{max-width:360px;white-space:normal!important}.release-task-cell strong{display:block;color:#101828;margin-bottom:4px}.release-task-cell span{display:block;color:var(--muted);font-size:12px;line-height:1.45}.impact{max-width:240px;white-space:normal!important;line-height:1.45}.owner{font-weight:800;color:#344054;white-space:nowrap}.release-form-stack{min-width:0;width:100%;z-index:1}.release-form-stack .form-card{position:sticky;top:96px;max-height:calc(100vh - 116px);overflow:auto;overscroll-behavior:contain}.release-form-stack .helper-card{padding:18px}.panel-head{gap:12px}.panel-head p{margin:5px 0 0;font-size:12px;line-height:1.45}.release-form-stack textarea{min-height:84px}.release-form-stack .form-grid{gap:13px}.release-form-stack input,.release-form-stack select,.release-form-stack textarea{min-height:42px}
    @media print{.sidebar,.topbar,.release-actions,.release-filters,.release-form-stack,.action-cell,.table-footer{display:none!important}.app{display:block}.content{padding:0}.release-layout{display:block}.release-table-card{box-shadow:none;border:none}.release-table-card table{min-width:0;font-size:11px}.release-banner,.release-summary-grid{break-inside:avoid}}
    @media(max-width:1500px){.release-summary-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.release-filters .search-field{grid-column:1/-1}}
    @media(max-width:1320px){.release-layout{grid-template-columns:1fr}.release-form-stack{grid-template-columns:1fr}.release-form-stack .form-card{position:static;max-height:none;overflow:visible}.release-filters{grid-template-columns:repeat(3,minmax(0,1fr))}.release-filters .search-field{grid-column:1/-1}.release-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.release-banner{display:grid}.release-meta{justify-content:flex-start}}
    @media(max-width:880px){.release-page .page-title{display:grid}.release-filters,.release-summary-grid{grid-template-columns:1fr}.release-filters .search-field{grid-column:1}.release-table-head{display:grid}.quick-actions.release-actions{display:grid;justify-content:stretch}.quick-actions.release-actions button{width:100%}.release-table-card table{min-width:980px}}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const form = document.getElementById('releaseItemForm');
    const table = document.getElementById('releaseItemsTable');
    const rows = Array.from(document.querySelectorAll('.release-row'));
    const groups = Array.from(document.querySelectorAll('[data-release-group]'));
    const noResultsRow = document.getElementById('releaseNoResultsRow');
    const defaultCreateAction = form?.dataset.createAction || form?.dataset.action || '';
    const currentUserName = @json(auth()->user()?->name);
    const defaultFromDate = @json($defaultFromDate);
    const defaultToDate = @json($defaultToDate);
    const today = @json($today);

    const controls = {
        search: document.getElementById('releaseSearch'),
        fromDate: document.getElementById('releaseFromDate'),
        toDate: document.getElementById('releaseToDate'),
        module: document.getElementById('releaseModuleFilter'),
        uiFunction: document.getElementById('releaseUiFunctionFilter'),
        itemType: document.getElementById('releaseItemTypeFilter'),
        version: document.getElementById('releaseVersionFilter'),
    };

    const text = (value) => String(value || '').toLowerCase().trim();

    function matches(row) {
        const q = text(controls.search?.value);
        const from = controls.fromDate?.value || '';
        const to = controls.toDate?.value || '';
        const module = controls.module?.value || 'All';
        const uiFunction = controls.uiFunction?.value || 'All';
        const itemType = controls.itemType?.value || 'All';
        const version = controls.version?.value || 'All';
        const date = row.dataset.date || '';

        return (!q || text(row.innerText).includes(q))
            && (!from || date >= from)
            && (!to || date <= to)
            && (module === 'All' || row.dataset.module === module)
            && (uiFunction === 'All' || row.dataset.uiFunction === uiFunction)
            && (itemType === 'All' || row.dataset.itemType === itemType)
            && (version === 'All' || row.dataset.releaseVersion === version);
    }

    function visibleRows() {
        return rows.filter((row) => row.style.display !== 'none');
    }

    function setSummary(visible) {
        const dates = new Set(visible.map((row) => row.dataset.date).filter(Boolean));
        document.getElementById('releaseSumTotal').textContent = visible.length;
        document.getElementById('releaseSumMajor').textContent = visible.filter((row) => row.dataset.releaseVersion === 'Major').length;
        document.getElementById('releaseSumMinor').textContent = visible.filter((row) => row.dataset.releaseVersion === 'Minor').length;
        document.getElementById('releaseSumHotfix').textContent = visible.filter((row) => row.dataset.releaseVersion === 'Hotfix' || row.dataset.itemType === 'Bug Fix').length;
        document.getElementById('releaseSumDates').textContent = dates.size;
        document.getElementById('releaseFooterText').textContent = `Showing ${visible.length} of ${rows.length} release items across ${dates.size} date(s)`;
        document.getElementById('releaseResultText').textContent = visible.length
            ? 'Showing release items grouped by date. Date is shown once per group.'
            : 'No release item found for selected filter.';
    }

    function applyFilters() {
        let visibleCount = 0;

        rows.forEach((row) => {
            const show = matches(row);
            row.style.display = show ? '' : 'none';
            visibleCount += show ? 1 : 0;
        });

        groups.forEach((group) => {
            const visibleForDate = rows.filter((row) => row.dataset.date === group.dataset.date && row.style.display !== 'none');
            const hasVisibleRow = visibleForDate.length > 0;

            if (hasVisibleRow) {
                const modules = [...new Set(visibleForDate.map((row) => row.dataset.module).filter(Boolean))];
                const cell = group.querySelector('td');

                if (cell) {
                    cell.textContent = `${group.dataset.label || group.dataset.date} — ${visibleForDate.length} released item(s) • ${modules.join(', ')}`;
                }
            }

            group.style.display = hasVisibleRow ? '' : 'none';
        });

        if (noResultsRow) {
            noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
        }

        setSummary(visibleRows());
    }


    function keepReleaseFormVisible(focusTargetId = 'releaseDate') {
        if (!form) {
            return;
        }

        if (window.matchMedia('(max-width: 1320px)').matches) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            form.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.getElementById(focusTargetId)?.focus({ preventScroll: true });
    }

    function resetForm() {
        if (!form) {
            return;
        }

        form.reset();
        form.dataset.action = defaultCreateAction;
        form.action = defaultCreateAction;
        document.getElementById('releaseDate').value = today;
        document.getElementById('releaseBy').value = currentUserName || '';
        document.getElementById('releaseStatus').value = 'Released';
        document.getElementById('releaseFormTitle').textContent = 'New Release Item';
        document.getElementById('releaseFormMode').textContent = 'New';
        document.getElementById('releaseSubmitBtn').textContent = 'Save Release Item';
        document.getElementById('releaseSubmitBtn').dataset.originalText = 'Save Release Item';
        document.getElementById('releaseCancelEditBtn').hidden = true;
    }

    function fillForm(row) {
        if (!form || !row) {
            return;
        }

        form.dataset.action = row.dataset.updateUrl;
        form.action = row.dataset.updateUrl;
        document.getElementById('releaseDate').value = row.dataset.date || today;
        document.getElementById('releaseModule').value = row.dataset.module || '';
        document.getElementById('releaseUiFunction').value = row.dataset.uiFunction || '';
        document.getElementById('releaseItemType').value = row.dataset.itemType || '';
        document.getElementById('releaseTask').value = row.dataset.task || '';
        document.getElementById('releaseNote').value = row.dataset.note || '';
        document.getElementById('releaseUserImpact').value = row.dataset.userImpact || '';
        document.getElementById('releaseBy').value = row.dataset.releasedBy || currentUserName || '';
        document.getElementById('releaseVersion').value = row.dataset.releaseVersion || '';
        document.getElementById('releaseStatus').value = row.dataset.status || 'Released';
        document.getElementById('releaseFormTitle').textContent = 'Edit Release Item';
        document.getElementById('releaseFormMode').textContent = 'Edit';
        document.getElementById('releaseSubmitBtn').textContent = 'Update Release Item';
        document.getElementById('releaseSubmitBtn').dataset.originalText = 'Update Release Item';
        document.getElementById('releaseCancelEditBtn').hidden = false;

        keepReleaseFormVisible('releaseTask');
    }

    function csvValue(value) {
        return `"${String(value || '').replace(/"/g, '""')}"`;
    }

    function exportCsv() {
        const columns = ['Release Date', 'Module', 'UI / Function', 'Item Type', 'Task', 'Task Details', 'User Impact', 'Released By', 'Release Version', 'Status'];
        const lines = [columns.map(csvValue).join(',')];

        visibleRows().forEach((row) => {
            lines.push([
                row.dataset.date,
                row.dataset.module,
                row.dataset.uiFunction,
                row.dataset.itemType,
                row.dataset.task,
                row.dataset.note,
                row.dataset.userImpact,
                row.dataset.releasedBy,
                row.dataset.releaseVersion,
                row.dataset.status,
            ].map(csvValue).join(','));
        });

        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `release-notes-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    }

    Object.values(controls).forEach((control) => {
        control?.addEventListener('input', applyFilters);
        control?.addEventListener('change', applyFilters);
    });

    document.getElementById('releaseResetBtn')?.addEventListener('click', () => {
        controls.search.value = '';
        controls.fromDate.value = defaultFromDate;
        controls.toDate.value = defaultToDate;
        controls.module.value = 'All';
        controls.uiFunction.value = 'All';
        controls.itemType.value = 'All';
        controls.version.value = 'All';
        applyFilters();
        window.AccountingUI?.showToast('Release filters reset.');
    });

    document.getElementById('releaseNewBtn')?.addEventListener('click', () => {
        resetForm();
        keepReleaseFormVisible('releaseDate');
    });

    document.getElementById('releaseCancelEditBtn')?.addEventListener('click', () => {
        resetForm();
        window.AccountingUI?.showToast('Edit cancelled. New release item form is ready.');
    });

    document.getElementById('releaseExportBtn')?.addEventListener('click', exportCsv);
    document.getElementById('releasePrintBtn')?.addEventListener('click', () => window.print());

    table?.addEventListener('click', (event) => {
        const editButton = event.target.closest('.release-edit-btn');

        if (!editButton) {
            return;
        }

        fillForm(editButton.closest('.release-row'));
    });

    resetForm();
    applyFilters();
})();
</script>
@endpush
