@extends('layouts.app')

@section('title', 'Voucher Numbering Setup | FinAcco')

@section('content')
@php
    $defaultPrefixesForJs = $defaultPrefixes ?? [
        'Payment Voucher' => 'PV',
        'Receipt Voucher' => 'RV',
        'Journal Voucher' => 'JV',
        'Contra / Transfer Voucher' => 'CV',
        'Draft Voucher' => 'DR',
    ];

    $defaultUsedForForJs = $voucherTypes ?? [
        'Payment Voucher' => 'Cash/bank payments',
        'Receipt Voucher' => 'Cash/bank receipts',
        'Journal Voucher' => 'Due, adjustment, opening balance',
        'Contra / Transfer Voucher' => 'Cash to bank or bank to bank transfer',
        'Draft Voucher' => 'Unposted draft transactions',
    ];

    $voucherTypeFilterOptions = collect($rules ?? [])
        ->pluck('voucher_type')
        ->merge(array_keys($defaultPrefixesForJs))
        ->filter()
        ->unique()
        ->values();

    $currentYearValue = $currentYear ?? now()->format('Y');
@endphp

<div class="page-title">
    <div>
        <h2>Voucher Numbering Setup</h2>
        <p>Configure automatic voucher numbers for payment, receipt, journal, transfer, and draft transactions.</p>
    </div>

    <div class="quick-actions">
        <button class="btn-outline" id="testAllBtn" type="button">
            Test All Formats
        </button>

        <button class="btn-primary" id="addBtn" type="button">
            + Add Voucher Type
        </button>
    </div>
</div>

<div class="layout">
    <div class="left-stack">
        <div class="card toolbar">
            <div class="field search-field">
                <label>Search</label>
                <span>⌕</span>
                <input id="searchInput" placeholder="Search voucher type, prefix, format...">
            </div>

            <div>
                <label>Voucher Type</label>
                <select id="typeFilter">
                    <option>All Types</option>
                    @foreach($voucherTypeFilterOptions as $type)
                        <option>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Status</label>
                <select id="statusFilter">
                    <option>All Status</option>
                    <option>Active</option>
                    <option>Inactive</option>
                </select>
            </div>

            <button class="btn-outline" id="resetBtn" type="button">
                Reset
            </button>
        </div>

        <div class="card table-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Voucher Type</th>
                            <th>Prefix</th>
                            <th>Format</th>
                            <th>First Number</th>
                            <th>Next Number</th>
                            <th>Used For</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>

                    <tbody id="voucherTable">
                        @forelse($rules as $rule)
                            <tr
                                data-id="{{ $rule->id }}"
                                data-type="{{ $rule->voucher_type }}"
                                data-financial-year="{{ $rule->financial_year_id }}"
                                data-prefix="{{ $rule->prefix }}"
                                data-format="{{ $rule->format_template }}"
                                data-first="{{ $rule->starting_number }}"
                                data-length="{{ $rule->number_length }}"
                                data-next="{{ $rule->next_number }}"
                                data-reset="{{ $rule->reset_every_year ? 1 : 0 }}"
                                data-used="{{ e($rule->used_for) }}"
                                data-status="{{ $rule->status }}"
                                data-update-url="{{ url('/api/voucher-numbering/' . $rule->id) }}"
                            >
                                <td class="type-name">
                                    {{ $rule->voucher_type }}
                                </td>

                                <td>
                                    <span class="prefix">{{ $rule->prefix }}</span>
                                </td>

                                <td>
                                    <span class="format">{{ $rule->format_template }}</span>
                                </td>

                                <td class="sample">
                                    {{ $rule->first_voucher_number }}
                                </td>

                                <td class="sample">
                                    {{ $rule->next_voucher_number }}
                                </td>

                                <td>
                                    {{ $rule->used_for }}
                                </td>

                                <td>
                                    <span class="badge {{ $rule->status === 'Active' ? 'badge-active' : 'badge-draft' }}">
                                        {{ $rule->status }}
                                    </span>
                                </td>

                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn edit-btn" type="button" title="Edit">
                                            ✎
                                        </button>

                                        <button class="icon-btn test-row-btn" type="button" title="Test">
                                            ✓
                                        </button>

                                        <form
                                            method="POST"
                                            action="{{ url('/setup/voucher-numbering/' . $rule->id) }}"
                                            onsubmit="return confirm('Delete this voucher numbering rule?')"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button class="icon-btn delete-btn" type="submit" title="Delete">
                                                ⋮
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty="true">
                                <td colspan="8" style="text-align:center;padding:24px;color:var(--muted)">
                                    No voucher numbering rules found. Add the default voucher formats.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span id="footerText">
                    Showing {{ $rules->count() }} of {{ $rules->count() }} voucher types
                </span>

                <div class="pagination">
                    <button class="page-btn" type="button">‹</button>
                    <button class="page-btn active" type="button">1</button>
                    <button class="page-btn" type="button">›</button>
                </div>
            </div>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card side-card">
            <div class="progress">
                <div class="ring">
                    <div class="ring-inner">
                        8
                        <small>of 8</small>
                    </div>
                </div>

                <div>
                    <h3 style="margin:0 0 6px">Setup Progress</h3>
                    <strong style="font-size:24px;color:var(--primary)">100%</strong>
                    <p style="margin:4px 0 0;color:var(--muted);font-size:13px">Complete</p>
                </div>
            </div>

            <div class="setup-list">
                <div class="setup-row">
                    <span class="check">✓</span>
                    <div>
                        <strong>Company Setup</strong>
                        <span>Completed</span>
                    </div>
                </div>

                <div class="setup-row">
                    <span class="check">✓</span>
                    <div>
                        <strong>Cash / Bank Setup</strong>
                        <span>Completed</span>
                    </div>
                </div>

                <div class="setup-row">
                    <span class="check">✓</span>
                    <div>
                        <strong>Chart of Accounts</strong>
                        <span>Completed</span>
                    </div>
                </div>

                <div class="setup-row">
                    <span class="check">✓</span>
                    <div>
                        <strong>Ledger Mapping</strong>
                        <span>Completed</span>
                    </div>
                </div>

                <div class="setup-row">
                    <span class="check current">8</span>
                    <div>
                        <strong>Voucher Numbering</strong>
                        <span>In Progress</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card form-card">
            <h3>Create / Edit Voucher Format</h3>

            <form
                class="form-grid"
                id="voucherForm"
                data-frontend-form
                data-action="{{ url('/api/voucher-numbering') }}"
                data-store-url="{{ url('/api/voucher-numbering') }}"
                data-success="Voucher numbering rule saved successfully."
            >
                @csrf

                <input type="hidden" name="_method" id="formMethod" value="POST">

                <div>
                    <label>Voucher Type <span class="required">*</span></label>
                    <input
                        id="voucherType"
                        name="voucher_type"
                        value="Payment Voucher"
                        placeholder="Example: Payment Voucher"
                        required
                    >

                    <div class="hint">
                        Examples: Payment Voucher, Receipt Voucher, Journal Voucher, Contra / Transfer Voucher, Draft Voucher
                    </div>
                </div>

                <div>
                    <label>Financial Year <span class="required">*</span></label>
                    <select id="financialYearId" name="financial_year_id" required>
                        @foreach($financialYears as $financialYear)
                            <option
                                value="{{ $financialYear->id }}"
                                @selected($currentFinancialYear?->id === $financialYear->id)
                            >
                                {{ $financialYear->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="two-col">
                    <div>
                        <label>Prefix <span class="required">*</span></label>
                        <input
                            id="prefixInput"
                            name="prefix"
                            value="PV"
                            maxlength="10"
                            required
                        >
                    </div>

                    <div>
                        <label>First Number <span class="required">*</span></label>
                        <input
                            id="firstNumber"
                            name="starting_number"
                            type="number"
                            value="1"
                            min="1"
                            required
                        >
                    </div>
                </div>

                <div class="two-col">
                    <div>
                        <label>Number Length <span class="required">*</span></label>
                        <input
                            id="numberLength"
                            name="number_length"
                            type="number"
                            value="5"
                            min="3"
                            max="10"
                            required
                        >
                    </div>

                    <div>
                        <label>Reset Every Year <span class="required">*</span></label>
                        <select id="resetEveryYear" name="reset_every_year" required>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label>Format <span class="required">*</span></label>
                    <input
                        id="formatInput"
                        name="format_template"
                        value="PV-{YYYY}-{00000}"
                        required
                    >

                    <div class="hint">
                        Use tokens like {YYYY}, {YY}, {MM}, {00000}
                    </div>
                </div>

                <div>
                    <label>Used For</label>
                    <textarea id="usedFor" name="used_for">Cash/bank payments</textarea>
                </div>

                <div>
                    <label>Status</label>
                    <select id="statusInput" name="status">
                        <option>Active</option>
                        <option>Inactive</option>
                    </select>
                </div>

                <div class="preview-box">
                    <span>Generated Preview</span>
                    <strong id="previewNo">PV-{{ $currentYearValue }}-00001</strong>
                </div>

                <div>
                    <label>Quick Tokens</label>

                    <div class="token-list">
                        <button type="button" class="token" data-token="{YYYY}">
                            {YYYY}
                        </button>

                        <button type="button" class="token" data-token="{YY}">
                            {YY}
                        </button>

                        <button type="button" class="token" data-token="{MM}">
                            {MM}
                        </button>

                        <button type="button" class="token" data-token="{00000}">
                            {00000}
                        </button>

                        <button type="button" class="token" data-token="{0000}">
                            {0000}
                        </button>
                    </div>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost" id="clearBtn">
                        Clear
                    </button>

                    <button type="button" class="btn-outline" id="testBtn">
                        Test Format
                    </button>

                    <button type="submit" class="btn-primary">
                        Save Voucher Format
                    </button>
                </div>
            </form>
        </div>

        <div class="card side-card">
            <h3>Numbering Summary</h3>

            <div class="summary-list">
                <div class="summary-row">
                    <span>Active Formats</span>
                    <strong id="activeCount">{{ $activeCount }}</strong>
                </div>

                <div class="summary-row">
                    <span>Current Year</span>
                    <strong>{{ $currentYearValue }}</strong>
                </div>

                <div class="summary-row">
                    <span>Number Padding</span>
                    <strong id="paddingSummary">5 Digits</strong>
                </div>

                <div class="summary-row">
                    <span>Duplicate Prefix</span>
                    <strong class="badge {{ $duplicatePrefixIssue ? 'badge-warning' : 'badge-active' }}">
                        {{ $duplicatePrefixIssue ? 'Issue Found' : 'No Issue' }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="card tip-card">
            <div class="tip-icon">💡</div>

            <div>
                <strong>Product rule</strong>
                <p>
                    Voucher number should be generated only after saving or posting.
                    Draft transactions can use DR numbers until they are posted.
                </p>
            </div>
        </div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('voucherTable');
    const form = document.getElementById('voucherForm');

    const currentYear = '{{ $currentYearValue }}';

    const defaultPrefixes = @json($defaultPrefixesForJs);
    const defaultUsedFor = @json($defaultUsedForForJs);

    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');
    const footerText = document.getElementById('footerText');
    const activeCount = document.getElementById('activeCount');

    const voucherType = document.getElementById('voucherType');
    const financialYearId = document.getElementById('financialYearId');
    const prefixInput = document.getElementById('prefixInput');
    const firstNumber = document.getElementById('firstNumber');
    const numberLength = document.getElementById('numberLength');
    const resetEveryYear = document.getElementById('resetEveryYear');
    const formatInput = document.getElementById('formatInput');
    const usedFor = document.getElementById('usedFor');
    const statusInput = document.getElementById('statusInput');
    const previewNo = document.getElementById('previewNo');
    const formMethod = document.getElementById('formMethod');

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        alert(message);
    }

    function pad(number, length) {
        return String(number).padStart(length, '0');
    }

    function normalizeText(value) {
        return String(value || '').trim();
    }

    function generate(format, number) {
        let output = format
            .replaceAll('{YYYY}', currentYear)
            .replaceAll('{YY}', currentYear.slice(-2))
            .replaceAll('{MM}', String(new Date().getMonth() + 1).padStart(2, '0'));

        const match = output.match(/\{0+\}/);

        if (match) {
            output = output.replace(match[0], pad(number, match[0].length - 2));
        }

        return output;
    }

    function zeroToken(length) {
        return `{${'0'.repeat(Number(length || 5))}}`;
    }

    function visibleRows() {
        return Array.from(tbody.rows).filter((row) => row.dataset.empty !== 'true');
    }

    function currentEditingRow() {
        if (formMethod.value !== 'PUT') {
            return null;
        }

        return visibleRows().find((row) => row.dataset.updateUrl === form.dataset.action) || null;
    }

    function findExistingRule(type, yearId) {
        const normalizedType = normalizeText(type).toLowerCase();

        return visibleRows().find((row) => {
            return normalizeText(row.dataset.type).toLowerCase() === normalizedType
                && String(row.dataset.financialYear) === String(yearId);
        });
    }

    function findDuplicatePrefix(prefix, yearId, ignoredRow = null) {
        const normalizedPrefix = normalizeText(prefix).toUpperCase();

        return visibleRows().find((row) => {
            return row !== ignoredRow
                && normalizeText(row.dataset.prefix).toUpperCase() === normalizedPrefix
                && String(row.dataset.financialYear) === String(yearId);
        });
    }

    function switchToCreateMode() {
        form.dataset.action = form.dataset.storeUrl;
        formMethod.value = 'POST';
    }

    function defaultPrefixForType(type) {
        const normalizedType = normalizeText(type).toLowerCase();

        const key = Object.keys(defaultPrefixes).find((item) => {
            return item.toLowerCase() === normalizedType;
        });

        return key ? defaultPrefixes[key] : '';
    }

    function defaultUsedForType(type) {
        const normalizedType = normalizeText(type).toLowerCase();

        const key = Object.keys(defaultUsedFor).find((item) => {
            return item.toLowerCase() === normalizedType;
        });

        return key ? defaultUsedFor[key] : '';
    }

    function syncDefaultsFromVoucherType() {
        const type = normalizeText(voucherType.value);

        if (!type) {
            return;
        }

        const defaultPrefix = defaultPrefixForType(type);
        const defaultUsage = defaultUsedForType(type);

        if (defaultPrefix && !prefixInput.value) {
            prefixInput.value = defaultPrefix;
        }

        if (defaultUsage && !usedFor.value) {
            usedFor.value = defaultUsage;
        }
    }

    function syncFormatFromPrefix(force = false) {
        const prefix = normalizeText(prefixInput.value).toUpperCase();

        prefixInput.value = prefix;

        if (!prefix) {
            return;
        }

        if (force || !formatInput.value || !formatInput.value.startsWith(prefix)) {
            formatInput.value = `${prefix}-{YYYY}-${zeroToken(numberLength.value)}`;
        }
    }

    function updatePreview() {
        syncDefaultsFromVoucherType();
        syncFormatFromPrefix(false);

        const fallbackPrefix = prefixInput.value || 'PV';
        const fallbackFormat = `${fallbackPrefix}-{YYYY}-${zeroToken(numberLength.value)}`;
        const format = formatInput.value || fallbackFormat;
        const number = Number(firstNumber.value || 1);

        previewNo.textContent = generate(format, number);

        const paddingSummary = document.getElementById('paddingSummary');

        if (paddingSummary) {
            paddingSummary.textContent = `${numberLength.value || 5} Digits`;
        }
    }

    function loadForEdit(row, silent = false) {
        if (!row) {
            switchToCreateMode();
            return;
        }

        form.dataset.action = row.dataset.updateUrl;
        formMethod.value = 'PUT';

        voucherType.value = row.dataset.type || '';
        financialYearId.value = row.dataset.financialYear || '';
        prefixInput.value = row.dataset.prefix || '';
        firstNumber.value = row.dataset.first || 1;
        numberLength.value = row.dataset.length || 5;
        resetEveryYear.value = row.dataset.reset || '1';
        formatInput.value = row.dataset.format || '';
        usedFor.value = row.dataset.used || '';
        statusInput.value = row.dataset.status || 'Active';

        updatePreview();

        if (!silent) {
            form.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });

            showToast('Voucher format loaded for editing.');
        }
    }

    function autoLoadExistingRule() {
        const existingRow = findExistingRule(voucherType.value, financialYearId.value);
        const editingRow = currentEditingRow();

        if (existingRow && existingRow !== editingRow) {
            loadForEdit(existingRow, true);
            showToast(`${voucherType.value} already exists for this financial year. Loaded for editing.`);
            return true;
        }

        if (!editingRow) {
            switchToCreateMode();
        }

        return false;
    }

    function filterRows() {
        const query = searchInput.value.toLowerCase().trim();
        const selectedType = typeFilter.value;
        const selectedStatus = statusFilter.value;

        let visible = 0;
        let total = 0;
        let active = 0;

        Array.from(tbody.rows).forEach((row) => {
            if (row.dataset.empty === 'true') {
                row.style.display = tbody.rows.length === 1 ? '' : 'none';
                return;
            }

            total++;

            const show =
                (!query || row.innerText.toLowerCase().includes(query)) &&
                (selectedType === 'All Types' || row.dataset.type === selectedType) &&
                (selectedStatus === 'All Status' || row.dataset.status === selectedStatus);

            row.style.display = show ? '' : 'none';

            if (show) {
                visible++;
            }

            if (row.dataset.status === 'Active') {
                active++;
            }
        });

        footerText.textContent = `Showing ${visible} of ${total} voucher types`;
        activeCount.textContent = String(active);
    }

    function clearForm() {
        form.reset();
        switchToCreateMode();

        voucherType.value = '';
        prefixInput.value = '';
        firstNumber.value = 1;
        numberLength.value = 5;
        resetEveryYear.value = '1';
        formatInput.value = '';
        usedFor.value = '';
        statusInput.value = 'Active';

        updatePreview();
        voucherType.focus();
    }

    Array.from(tbody.querySelectorAll('.edit-btn')).forEach((button) => {
        button.addEventListener('click', () => {
            loadForEdit(button.closest('tr'));
        });
    });

    Array.from(tbody.querySelectorAll('.test-row-btn')).forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            showToast(`Next voucher will be ${generate(row.dataset.format, Number(row.dataset.next))}`);
        });
    });

    voucherType.addEventListener('blur', () => {
        if (!voucherType.value) {
            return;
        }

        const exists = autoLoadExistingRule();

        if (!exists) {
            syncDefaultsFromVoucherType();
            syncFormatFromPrefix(true);
            updatePreview();
        }
    });

    voucherType.addEventListener('input', () => {
        syncDefaultsFromVoucherType();
        updatePreview();
    });

    financialYearId.addEventListener('change', () => {
        autoLoadExistingRule();
    });

    prefixInput.addEventListener('input', () => {
        syncFormatFromPrefix(true);
        updatePreview();
    });

    numberLength.addEventListener('input', () => {
        syncFormatFromPrefix(true);
        updatePreview();
    });

    [formatInput, firstNumber].forEach((input) => {
        input.addEventListener('input', updatePreview);
    });

    document.querySelectorAll('.token').forEach((token) => {
        token.addEventListener('click', () => {
            formatInput.value += token.dataset.token;
            updatePreview();
        });
    });

    [searchInput, typeFilter, statusFilter].forEach((element) => {
        element.addEventListener('input', filterRows);
        element.addEventListener('change', filterRows);
    });

    document.getElementById('resetBtn').addEventListener('click', () => {
        searchInput.value = '';
        typeFilter.value = 'All Types';
        statusFilter.value = 'All Status';
        filterRows();
        showToast('Filters reset.');
    });

    document.getElementById('clearBtn').addEventListener('click', () => {
        clearForm();
        showToast('Form cleared.');
    });

    document.getElementById('addBtn').addEventListener('click', () => {
        clearForm();
        showToast('Enter a voucher type to create or edit its numbering rule.');
    });

    document.getElementById('testBtn').addEventListener('click', () => {
        updatePreview();
        showToast(`Preview: ${previewNo.textContent}`);
    });

    document.getElementById('testAllBtn').addEventListener('click', () => {
        showToast('All visible voucher formats are ready for testing.');
    });

    form.addEventListener('submit', (event) => {
        const selectedType = normalizeText(voucherType.value);
        const selectedYearId = financialYearId.value;
        const existingRow = findExistingRule(selectedType, selectedYearId);
        const editingRow = currentEditingRow();

        if (!selectedType) {
            event.preventDefault();
            voucherType.focus();
            showToast('Voucher Type is required.');
            return;
        }

        if (existingRow && existingRow !== editingRow) {
            event.preventDefault();
            loadForEdit(existingRow, true);
            showToast(`${selectedType} already exists. The existing rule is now loaded for editing.`);
            return;
        }

        const prefix = normalizeText(prefixInput.value).toUpperCase();
        const duplicatePrefix = findDuplicatePrefix(prefix, selectedYearId, editingRow);

        if (duplicatePrefix) {
            event.preventDefault();
            showToast('Duplicate prefix found. Please use a unique prefix for this financial year.');
            return;
        }

        const format = formatInput.value;
        const zeroMatches = format.match(/\{0+\}/g) || [];

        if (zeroMatches.length !== 1) {
            event.preventDefault();
            showToast('Format must contain exactly one number token such as {00000}.');
            return;
        }

        if ((zeroMatches[0].length - 2) !== Number(numberLength.value)) {
            event.preventDefault();
            showToast('Number Length must match the zero token in Format.');
            return;
        }

        if (!format.includes('{YYYY}') && !format.includes('{YY}')) {
            event.preventDefault();
            showToast('Format must contain {YYYY} or {YY}.');
            return;
        }

        if (!format.startsWith(prefix)) {
            event.preventDefault();
            showToast('Format must start with the selected prefix.');
        }
    });

    filterRows();
    updatePreview();
});
</script>
@endpush
