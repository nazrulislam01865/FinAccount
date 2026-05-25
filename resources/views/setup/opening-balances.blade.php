@extends('layouts.app')

@section('title', 'Opening Balance Setup | Accounting System')

@section('content')
@php
    $hasSavedOpeningRows = $openingBalances->isNotEmpty();
    $canManageOpeningBalances = $canManageOpeningBalances ?? (auth()->user()?->hasAnyPermission(['opening-balances.manage']) === true);
    $openingCanEdit = !$openingIsFinal && $canManageOpeningBalances;
    $openingEditLockedMessage = $openingIsFinal
        ? 'Opening balance is finalized and cannot be edited directly.'
        : 'Your role can view Opening Balance Setup, but cannot edit or save opening balances.';

    $rows = $hasSavedOpeningRows
        ? $openingBalances->map(fn ($balance) => [
            'account' => $balance->account,
            'party_id' => $balance->party_id,
            'debit_opening' => $balance->debit_opening,
            'credit_opening' => $balance->credit_opening,
            'remarks' => $balance->remarks,
        ])
        : $seedOpeningRows;

    $accountPayload = $accounts->map(fn ($account) => [
        'id' => $account->id,
        'account_code' => $account->account_code,
        'account_name' => $account->account_name,
        'display_name' => $account->display_name ?: trim($account->account_code . ' - ' . $account->account_name),
        'account_type' => $account->accountType?->name ?? 'Asset',
        'normal_balance' => $account->normal_balance ?: $account->accountType?->normal_balance ?: 'Debit',
        'requires_party' => str_contains(strtoupper($account->account_name), 'RECEIVABLE')
            || str_contains(strtoupper($account->account_name), 'PAYABLE')
            || str_contains(strtoupper($account->account_name), 'ADVANCE TO')
            || str_contains(strtoupper($account->account_name), 'ADVANCE FROM')
            || str_contains(strtoupper($account->account_name), 'CUSTOMER DUE')
            || str_contains(strtoupper($account->account_name), 'SUPPLIER DUE'),
    ])->values();

    $partyPayload = $parties->map(fn ($party) => [
        'id' => $party->id,
        'party_name' => $party->party_name,
        'party_code' => $party->party_code,
        'party_type' => $party->partyType?->name,
        'default_ledger_nature' => $party->default_ledger_nature,
        'linked_ledger_account_id' => $party->linked_ledger_account_id,
    ])->values();

    $badgeClass = function (?string $type) {
        return match ($type) {
            'Asset', 'Expense' => 'badge-asset',
            'Liability' => 'badge-liability',
            'Equity', 'Income' => 'badge-equity',
            default => 'badge-neutral',
        };
    };

    $accountLabel = function ($account) {
        if (!$account) {
            return '—';
        }

        return $account->display_name ?: trim($account->account_code . ' - ' . $account->account_name);
    };

    $partyLookup = $parties->keyBy('id');

    $partyLabel = function ($partyId) use ($partyLookup) {
        if (!$partyId) {
            return '—';
        }

        $party = $partyLookup->get((int) $partyId);

        if (!$party) {
            return '—';
        }

        return trim(($party->party_code ? $party->party_code . ' - ' : '') . $party->party_name);
    };
@endphp

<div class="page-title">
    <div>
        <span class="page-label">Opening Balance Setup</span>
        <h2>Opening Balance Setup</h2>
        <p>Enter starting balances before daily transactions begin. Final posting creates an Opening Voucher.</p>
    </div>
</div>

@include('partials.setup-progress', ['current' => 7])

@if($openingIsFinal)
    <div class="card hint-box" style="margin-bottom:16px">
        <strong>Opening balance is finalized.</strong>
        Posted opening balances cannot be edited directly. Use a reversal or adjustment voucher if correction is required.

        @if($postedOpeningVoucher)
            <div style="margin-top:6px">
                Posted Voucher:
                <strong>{{ $postedOpeningVoucher->voucher_number }}</strong>
                · Total: BDT {{ number_format((float) $postedOpeningVoucher->total_debit, 2) }}
                · Date: {{ optional($postedOpeningVoucher->voucher_date)->format('d M Y') }}
            </div>
        @endif
    </div>
@endif

@if(!$openingCanEdit && !$openingIsFinal)
    <div class="card hint-box" style="margin-bottom:16px;border-color:#fed7aa;background:#fff7ed;color:#9a3412">
        <strong>Read-only access.</strong> Your role can view Opening Balance Setup, but edit/save controls are locked.
    </div>
@endif

<form
    id="openingBalanceForm"
    data-action="{{ route('api.opening-balances.store') }}"
    data-success="Opening balance saved."
    data-opening-can-edit="{{ $openingCanEdit ? '1' : '0' }}"
    data-opening-locked-message="{{ $openingEditLockedMessage }}"
>
    @csrf

    <input type="hidden" name="status" id="openingStatus" value="Draft">

    <div class="opening-summary-cards">
        <div class="card opening-counter-card">
            <span>Total Debit</span>
            <strong id="sideDebit">BDT 0.00</strong>
        </div>

        <div class="card opening-counter-card">
            <span>Total Credit</span>
            <strong id="sideCredit">BDT 0.00</strong>
        </div>

        <div class="card opening-counter-card">
            <span>Difference</span>
            <strong id="sideDifference" class="green">BDT 0.00</strong>
        </div>
    </div>

    <div class="layout">
        <div class="left-stack">
            <div class="card toolbar opening-toolbar">
                <div>
                    <label>Financial Year <span class="required">*</span></label>

                    <select name="financial_year_id" id="financialYearId" required @disabled($openingIsFinal)>
                        <option value="">Select Financial Year</option>

                        @foreach($financialYears as $financialYear)
                            <option
                                value="{{ $financialYear->id }}"
                                @selected($currentFinancialYear?->id === $financialYear->id)
                            >
                                {{ $financialYear->name }}
                                ({{ optional($financialYear->start_date)->format('d M Y') }} - {{ optional($financialYear->end_date)->format('d M Y') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Opening Date <span class="required">*</span></label>
                    <input
                        type="date"
                        name="balance_date"
                        id="balanceDate"
                        value="{{ $balanceDate }}"
                        required
                        @disabled($openingIsFinal)
                    >
                </div>

                <div>
                    <label>Branch / Location</label>
                    <select name="branch_location" id="branchLocation" @disabled($openingIsFinal)>
                        @foreach($branches as $branch)
                            <option value="{{ $branch }}" @selected($branchLocation === $branch)>
                                {{ $branch }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button class="btn-outline" id="importBtn" type="button" @disabled(!$openingCanEdit)>
                    ⇧ Import from Excel
                </button>

                <button class="btn-primary" id="validateBtn" type="button">
                    🛡 Validate Balances
                </button>
            </div>

            <div class="card balance-card">
                <div class="card-head">
                    <div>
                        <h3>Opening Balances</h3>
                        <p>
                            Draft rows are view-only until you click <strong>Edit</strong>. Click <strong>+ Add New Row</strong> to enter another opening balance line.
                        </p>
                    </div>

                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button class="btn-outline" id="downloadBtn" type="button">
                            ⇩ Download Template
                        </button>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Account Code</th>
                                <th>Account Name</th>
                                <th>Account Type</th>
                                <th>Party / Sub-ledger</th>
                                <th>Debit Opening<br>(BDT)</th>
                                <th>Credit Opening<br>(BDT)</th>
                                <th>Net Balance<br>(BDT)</th>
                                <th>Remarks</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody id="balanceTable">
                            @forelse($rows as $index => $row)
                                @php
                                    $account = $row['account'];
                                    $type = $account?->accountType?->name ?? 'Asset';
                                    $normalBalance = $account?->normal_balance ?: $account?->accountType?->normal_balance ?: 'Debit';
                                    $debit = (float) ($row['debit_opening'] ?? 0);
                                    $credit = (float) ($row['credit_opening'] ?? 0);
                                    $net = $debit - $credit;
                                @endphp

                                <tr
                                    class="opening-view-row"
                                    data-mode="view"
                                    data-type="{{ $type }}"
                                    data-normal-balance="{{ $normalBalance }}"
                                >
                                    <td>{{ $index + 1 }}</td>

                                    <td>
                                        <span class="view-only-value code-display">{{ $account?->account_code ?: '—' }}</span>
                                        <input
                                            type="hidden"
                                            class="item-account"
                                            name="items[{{ $index }}][account_id]"
                                            value="{{ $account?->id }}"
                                        >
                                    </td>

                                    <td>
                                        <span class="view-only-value account-display">{{ $accountLabel($account) }}</span>
                                    </td>

                                    <td>
                                        <span class="badge account-type {{ $badgeClass($type) }}">
                                            {{ $type }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="view-only-value party-display">{{ $partyLabel($row['party_id']) }}</span>
                                        <input
                                            type="hidden"
                                            class="item-party"
                                            name="items[{{ $index }}][party_id]"
                                            value="{{ $row['party_id'] }}"
                                        >
                                    </td>

                                    <td class="amount-cell">
                                        <span class="view-only-value">{{ number_format($debit, 2) }}</span>
                                        <input
                                            type="hidden"
                                            class="money-input debit"
                                            name="items[{{ $index }}][debit_opening]"
                                            value="{{ number_format($debit, 2, '.', '') }}"
                                        >
                                    </td>

                                    <td class="amount-cell">
                                        <span class="view-only-value">{{ number_format($credit, 2) }}</span>
                                        <input
                                            type="hidden"
                                            class="money-input credit"
                                            name="items[{{ $index }}][credit_opening]"
                                            value="{{ number_format($credit, 2, '.', '') }}"
                                        >
                                    </td>

                                    <td class="{{ $net >= 0 ? 'net-dr' : 'net-cr' }} net-balance">
                                        {{ number_format(abs($net), 2) }} {{ $net >= 0 ? 'Dr' : 'Cr' }}
                                    </td>

                                    <td>
                                        <span class="view-only-value remarks-display">{{ $row['remarks'] ?: '—' }}</span>
                                        <input
                                            type="hidden"
                                            class="item-remarks"
                                            name="items[{{ $index }}][remarks]"
                                            value="{{ $row['remarks'] }}"
                                        >
                                    </td>

                                    <td class="action-cell">
                                        <button
                                            class="icon-btn edit-opening-row {{ $openingCanEdit ? '' : 'is-locked' }}"
                                            type="button"
                                            data-opening-action="edit"
                                            title="{{ $openingCanEdit ? 'Edit opening balance row' : $openingEditLockedMessage }}"
                                            data-opening-locked-message="{{ $openingEditLockedMessage }}"
                                            aria-disabled="{{ $openingCanEdit ? 'false' : 'true' }}"
                                        >
                                            ✎
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr data-empty="true">
                                    <td colspan="10" class="opening-empty">
                                        No opening balance rows yet. Click <strong>+ Add New Row</strong> to enter an opening balance.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <button
                    class="add-row"
                    id="addRowBtn"
                    type="button"
                    @disabled(!$openingCanEdit)
                >
                    + Add New Row
                </button>

                <div class="total-strip">
                    <div class="total-box">
                        <strong>Total Debit</strong>
                        <span id="totalDebit">0.00</span>
                    </div>

                    <div class="total-box">
                        <strong>Total Credit</strong>
                        <span id="totalCredit">0.00</span>
                    </div>

                    <div id="matchBox" class="match-box">
                        ✓ Balances Matched<br>
                        <small>Difference is 0.00 BDT</small>
                    </div>
                </div>

                <div class="net-line">
                    ⓘ Net Difference (Debit - Credit):
                    <strong id="difference">0.00 BDT</strong>
                </div>

                <div class="bottom-actions">
                    <button class="btn-ghost" type="button" id="cancelBtn">
                        Cancel
                    </button>

                    <div>
                        <button class="btn-outline" id="saveDraftBtn" type="submit" @disabled(!$openingCanEdit)>
                            Save Draft
                        </button>

                        <button class="btn-primary" id="finishBtn" type="submit" @disabled(!$openingCanEdit)>
                            Post Opening Balance
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <aside class="right-stack">
            <div class="card ready-card">
                <h3>Go-live Readiness</h3>

                <div class="ready-list">
                    <div><span class="checkmark">✓</span>Chart of Accounts Created</div>
                    <div><span class="checkmark">✓</span>Parties / Sub-ledgers Added</div>
                    <div><span class="checkmark">✓</span>Accounting Rules Setup Completed</div>
                    <div><span class="checkmark">✓</span>Opening Balances Entered</div>
                    <div><span class="checkmark">✓</span>Balances Validated</div>
                </div>
            </div>

            <div class="card import-card">
                <h3>Accounting Rules</h3>
                <p style="font-size:13px;line-height:1.5">
                    Asset and Expense accounts normally carry Debit opening balances.
                    Liability, Equity, and Income accounts normally carry Credit opening balances.
                    Total Debit must equal Total Credit before posting.
                </p>
            </div>

            <div class="card import-card">
                <h3>Import from Excel</h3>
                <p>Upload your opening balances using the Excel template.</p>

                <button class="btn-outline" type="button" style="width:100%" id="uploadExcelBtn" @disabled(!$openingCanEdit)>
                    ⇧ Upload Excel File
                </button>

                <p style="margin-top:10px;font-size:12px">
                    .xlsx format only, up to 5MB
                </p>
            </div>
        </aside>
    </div>
</form>
@endsection

@push('styles')
<style>
    .opening-summary-cards {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 16px;
    }

    .opening-counter-card {
        padding: 18px;
        min-height: 96px;
    }

    .opening-counter-card span {
        color: var(--muted);
        display: block;
        font-size: 13px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .opening-counter-card strong {
        display: block;
        font-size: 24px;
        line-height: 1.15;
    }

    @media (max-width: 980px) {
        .opening-summary-cards {
            grid-template-columns: 1fr;
        }
    }

    .opening-view-row {
        background: #fff;
    }

    .opening-edit-row {
        background: #f8fbff;
        box-shadow: inset 3px 0 0 var(--primary);
    }

    .view-only-value {
        display: inline-block;
        color: var(--text);
        font-weight: 700;
        line-height: 1.35;
    }

    .code-display {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
        white-space: nowrap;
    }

    .account-display,
    .party-display,
    .remarks-display {
        max-width: 240px;
        overflow-wrap: anywhere;
    }

    .amount-cell {
        text-align: right;
    }

    .opening-empty {
        padding: 28px 16px !important;
        color: var(--muted);
        text-align: center;
    }

    .action-cell {
        white-space: nowrap;
        text-align: center;
    }

    .action-cell .icon-btn + .icon-btn {
        margin-left: 4px;
    }

    .edit-opening-row.is-locked {
        opacity: .55;
        cursor: not-allowed;
    }

    .opening-edit-row input,
    .opening-edit-row select {
        min-width: 120px;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const accounts = @json($accountPayload);
    const parties = @json($partyPayload);
    const openingIsFinal = @json($openingIsFinal);
    const hasSavedOpeningRows = @json($hasSavedOpeningRows);
    const openingCanEdit = @json($openingCanEdit);

    const tbody = document.getElementById('balanceTable');
    const form = document.getElementById('openingBalanceForm');
    const statusInput = document.getElementById('openingStatus');
    const lockedMessage = form?.dataset.openingLockedMessage || 'Opening balance cannot be edited for your role or current status.';

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    async function parseJsonResponse(response) {
        const raw = await response.text();

        if (!raw) {
            return {};
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            return {
                success: false,
                message: response.ok
                    ? 'Unexpected server response while saving opening balance.'
                    : `Server returned ${response.status}. Check storage/logs/laravel.log for the backend exception.`,
            };
        }
    }

    function firstValidationMessage(errors) {
        if (!errors) {
            return null;
        }

        const first = Object.values(errors)[0];

        return Array.isArray(first) ? first[0] : first;
    }

    const branchLocation = document.getElementById('branchLocation');
    const balanceDate = document.getElementById('balanceDate');
    const financialYear = document.getElementById('financialYearId');

    const saveDraftBtn = document.getElementById('saveDraftBtn');
    const finishBtn = document.getElementById('finishBtn');

    function showLockedMessage() {
        showToast(lockedMessage);
    }

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        alert(message);
    }

    function money(value) {
        return Number(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function parseMoney(value) {
        return Number(String(value || 0).replaceAll(',', '')) || 0;
    }

    function accountById(id) {
        return accounts.find((account) => String(account.id) === String(id));
    }

    function linkedParties(accountId) {
        return parties.filter((party) => {
            return String(party.linked_ledger_account_id) === String(accountId);
        });
    }

    function accountRequiresParty(account) {
        if (!account) {
            return false;
        }

        if (account.requires_party) {
            return true;
        }

        return linkedParties(account.id).length > 0;
    }

    function badgeClass(type) {
        if (type === 'Asset' || type === 'Expense') {
            return 'badge-asset';
        }

        if (type === 'Liability') {
            return 'badge-liability';
        }

        if (type === 'Equity' || type === 'Income') {
            return 'badge-equity';
        }

        return 'badge-neutral';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function accountOptions(selectedId = '') {
        return [
            '<option value="">Select Account</option>',
            ...accounts.map((account) => {
                const selected = String(account.id) === String(selectedId) ? 'selected' : '';

                return `<option value="${account.id}" ${selected}>${escapeHtml(account.display_name)}</option>`;
            }),
        ].join('');
    }

    function partyOptions(accountId, selectedId = '') {
        const rows = linkedParties(accountId);

        if (rows.length === 0) {
            return '<option value="">No linked party found</option>';
        }

        return [
            '<option value="">Select Party</option>',
            ...rows.map((party) => {
                const selected = String(party.id) === String(selectedId) ? 'selected' : '';
                const label = `${party.party_code ? party.party_code + ' - ' : ''}${party.party_name}`;

                return `<option value="${party.id}" data-linked-account="${party.linked_ledger_account_id}" ${selected}>${escapeHtml(label)}</option>`;
            }),
        ].join('');
    }

    function partyLabelById(partyId) {
        if (!partyId) {
            return '—';
        }

        const party = parties.find((row) => String(row.id) === String(partyId));

        if (!party) {
            return '—';
        }

        return `${party.party_code ? party.party_code + ' - ' : ''}${party.party_name}`;
    }

    function rowData(row) {
        const account = row.querySelector('.item-account') || row.querySelector('.account-select');
        const party = row.querySelector('.item-party') || row.querySelector('.party-select');
        const debit = row.querySelector('.debit');
        const credit = row.querySelector('.credit');
        const remarks = row.querySelector('.item-remarks') || row.querySelector('.remarks-input');

        return {
            account_id: account?.value || '',
            party_id: party?.value || '',
            debit_opening: parseMoney(debit?.value).toFixed(2),
            credit_opening: parseMoney(credit?.value).toFixed(2),
            remarks: remarks?.value || '',
        };
    }

    function editableRowHtml(row, data) {
        const index = Math.max(0, dataRows().indexOf(row));

        return `
            <td>${index + 1}</td>
            <td><input class="code-input account-code" value="" readonly></td>
            <td><select class="item-account account-select" name="items[${index}][account_id]" required>${accountOptions(data.account_id)}</select></td>
            <td><span class="badge account-type ${badgeClass('Asset')}">Asset</span></td>
            <td><select class="item-party party-select" name="items[${index}][party_id]"><option value="">Default / Not Applicable</option></select></td>
            <td><input class="money-input debit" name="items[${index}][debit_opening]" value="${parseMoney(data.debit_opening).toFixed(2)}" inputmode="decimal"></td>
            <td><input class="money-input credit" name="items[${index}][credit_opening]" value="${parseMoney(data.credit_opening).toFixed(2)}" inputmode="decimal"></td>
            <td class="net-dr net-balance">0.00 Dr</td>
            <td><input class="item-remarks remarks-input" name="items[${index}][remarks]" value="${escapeHtml(data.remarks)}"></td>
            <td class="action-cell">
                <button class="icon-btn done-opening-row" type="button" data-opening-action="done" title="Keep row changes">✓</button>
                <button class="icon-btn cancel-opening-row" type="button" data-opening-action="cancel" title="Cancel row edit">↺</button>
            </td>
        `;
    }

    function viewRowHtml(row, data) {
        const account = accountById(data.account_id);
        const type = account?.account_type || 'Asset';
        const normalBalance = account?.normal_balance || 'Debit';
        const debit = parseMoney(data.debit_opening);
        const credit = parseMoney(data.credit_opening);
        const net = debit - credit;
        const index = Math.max(0, dataRows().indexOf(row));

        row.dataset.type = type;
        row.dataset.normalBalance = normalBalance;

        return `
            <td>${index + 1}</td>
            <td>
                <span class="view-only-value code-display">${escapeHtml(account?.account_code || '—')}</span>
                <input type="hidden" class="item-account" name="items[${index}][account_id]" value="${escapeHtml(data.account_id)}">
            </td>
            <td>
                <span class="view-only-value account-display">${escapeHtml(account?.display_name || '—')}</span>
            </td>
            <td>
                <span class="badge account-type ${badgeClass(type)}">${escapeHtml(type)}</span>
            </td>
            <td>
                <span class="view-only-value party-display">${escapeHtml(partyLabelById(data.party_id))}</span>
                <input type="hidden" class="item-party" name="items[${index}][party_id]" value="${escapeHtml(data.party_id)}">
            </td>
            <td class="amount-cell">
                <span class="view-only-value">${money(debit)}</span>
                <input type="hidden" class="money-input debit" name="items[${index}][debit_opening]" value="${debit.toFixed(2)}">
            </td>
            <td class="amount-cell">
                <span class="view-only-value">${money(credit)}</span>
                <input type="hidden" class="money-input credit" name="items[${index}][credit_opening]" value="${credit.toFixed(2)}">
            </td>
            <td class="${net >= 0 ? 'net-dr' : 'net-cr'} net-balance">${money(Math.abs(net))} ${net >= 0 ? 'Dr' : 'Cr'}</td>
            <td>
                <span class="view-only-value remarks-display">${data.remarks ? escapeHtml(data.remarks) : '—'}</span>
                <input type="hidden" class="item-remarks" name="items[${index}][remarks]" value="${escapeHtml(data.remarks)}">
            </td>
            <td class="action-cell">
                <button class="icon-btn edit-opening-row ${openingCanEdit ? '' : 'is-locked'}" type="button" data-opening-action="edit" title="Edit opening balance row" aria-disabled="${openingCanEdit ? 'false' : 'true'}">✎</button>
            </td>
        `;
    }

    function makeRowEditable(row, data, keepOriginal = false) {
        if (!openingCanEdit) {
            showLockedMessage();
            return;
        }

        if (keepOriginal) {
            row.dataset.originalRow = JSON.stringify(data);
        } else {
            delete row.dataset.originalRow;
        }

        row.className = 'opening-edit-row';
        row.dataset.mode = 'edit';
        row.innerHTML = editableRowHtml(row, data);

        bindRow(row);
        updateAccountInfo(row, true);

        const partySelect = row.querySelector('.party-select');

        if (partySelect && data.party_id) {
            partySelect.value = data.party_id;
        }

        const debit = row.querySelector('.debit');
        const credit = row.querySelector('.credit');

        if (debit) {
            debit.value = parseMoney(data.debit_opening).toFixed(2);
        }

        if (credit) {
            credit.value = parseMoney(data.credit_opening).toFixed(2);
        }

        updateRowIndexes();
        recalc();
    }

    function makeRowViewOnly(row, data) {
        row.className = 'opening-view-row';
        row.dataset.mode = 'view';
        row.innerHTML = viewRowHtml(row, data);
        delete row.dataset.originalRow;

        updateRowIndexes();
        recalc();
    }

    function ensureEmptyRow() {
        if (dataRows().length > 0) {
            return;
        }

        tbody.innerHTML = `
            <tr data-empty="true">
                <td colspan="10" class="opening-empty">
                    No opening balance rows yet. Click <strong>+ Add New Row</strong> to enter an opening balance.
                </td>
            </tr>
        `;
    }

    function isDataRow(row) {
        return row && row.dataset.empty !== 'true';
    }

    function isEditableRow(row) {
        return isDataRow(row) && row.dataset.mode === 'edit';
    }

    function dataRows() {
        return Array.from(tbody.rows).filter(isDataRow);
    }

    function field(row, selector) {
        return row.querySelector(selector);
    }

    function updateRowIndexes() {
        dataRows().forEach((row, index) => {
            row.children[0].textContent = String(index + 1);

            const account = field(row, '.item-account') || field(row, '.account-select');
            const party = field(row, '.item-party') || field(row, '.party-select');
            const debit = field(row, '.debit');
            const credit = field(row, '.credit');
            const remarks = field(row, '.item-remarks') || field(row, '.remarks-input');

            if (account) {
                account.name = `items[${index}][account_id]`;
            }

            if (party) {
                party.name = `items[${index}][party_id]`;
            }

            if (debit) {
                debit.name = `items[${index}][debit_opening]`;
            }

            if (credit) {
                credit.name = `items[${index}][credit_opening]`;
            }

            if (remarks) {
                remarks.name = `items[${index}][remarks]`;
            }
        });
    }

    function applyNormalBalance(row, account) {
        const debit = row.querySelector('.debit');
        const credit = row.querySelector('.credit');
        const normalBalance = account?.normal_balance || 'Debit';

        if (!debit || !credit) {
            return;
        }

        row.dataset.normalBalance = normalBalance;

        debit.readOnly = false;
        credit.readOnly = false;

        if (normalBalance === 'Debit') {
            credit.value = '0.00';
            credit.readOnly = true;
            debit.readOnly = !openingCanEdit;
        } else {
            debit.value = '0.00';
            debit.readOnly = true;
            credit.readOnly = !openingCanEdit;
        }
    }

    function updateAccountInfo(row, keepParty = false) {
        if (!isEditableRow(row)) {
            return;
        }

        const accountSelect = row.querySelector('.account-select');
        const partySelect = row.querySelector('.party-select');

        if (!accountSelect || !partySelect) {
            return;
        }

        const account = accountById(accountSelect.value);

        row.querySelector('.account-code').value = account?.account_code || '';

        const type = account?.account_type || 'Asset';
        const badge = row.querySelector('.account-type');

        badge.textContent = type;
        badge.className = `badge account-type ${badgeClass(type)}`;

        row.dataset.type = type;
        row.dataset.normalBalance = account?.normal_balance || 'Debit';

        const oldParty = keepParty ? partySelect.value : '';
        const requiresParty = accountRequiresParty(account);
        const availableParties = linkedParties(account?.id || '');

        partySelect.innerHTML = partyOptions(account?.id || '', oldParty);
        partySelect.dataset.partyRequired = requiresParty ? 'true' : 'false';
        partySelect.required = requiresParty && availableParties.length > 0;
        partySelect.disabled = !openingCanEdit || availableParties.length === 0;

        applyNormalBalance(row, account);
        recalc();
    }

    function recalc() {
        let totalDebit = 0;
        let totalCredit = 0;

        dataRows().forEach((row) => {
            const debitInput = row.querySelector('.debit');
            const creditInput = row.querySelector('.credit');

            if (!debitInput || !creditInput) {
                return;
            }

            const debit = parseMoney(debitInput.value);
            const credit = parseMoney(creditInput.value);

            totalDebit += debit;
            totalCredit += credit;

            const net = debit - credit;
            const netCell = row.querySelector('.net-balance') || row.children[7];

            netCell.className = `${net >= 0 ? 'net-dr' : 'net-cr'} net-balance`;
            netCell.textContent = `${money(Math.abs(net))}${net >= 0 ? ' Dr' : ' Cr'}`;
        });

        const difference = totalDebit - totalCredit;
        const absoluteDifference = Math.abs(difference);

        document.getElementById('totalDebit').textContent = money(totalDebit);
        document.getElementById('totalCredit').textContent = money(totalCredit);
        document.getElementById('difference').textContent = `${money(difference)} BDT`;

        document.getElementById('sideDebit').textContent = `BDT ${money(totalDebit)}`;
        document.getElementById('sideCredit').textContent = `BDT ${money(totalCredit)}`;
        document.getElementById('sideDifference').textContent = `BDT ${money(difference)}`;
        document.getElementById('sideDifference').className = absoluteDifference < 0.01 ? 'green' : 'red';

        const matchBox = document.getElementById('matchBox');

        if (absoluteDifference < 0.01) {
            matchBox.className = 'match-box';
            matchBox.innerHTML = '✓ Balances Matched<br><small>Difference is 0.00 BDT</small>';
        } else {
            matchBox.className = 'match-box error';
            matchBox.innerHTML = `⚠ Balances Not Matched<br><small>Difference is ${money(difference)} BDT</small>`;
        }

        return difference;
    }

    function bindRow(row) {
        if (!isEditableRow(row)) {
            return;
        }

        row.querySelector('.account-select')?.addEventListener('change', () => {
            updateAccountInfo(row, false);
        });

        row.querySelectorAll('.money-input').forEach((input) => {
            input.addEventListener('input', () => {
                const currentRow = input.closest('tr');
                const debit = currentRow.querySelector('.debit');
                const credit = currentRow.querySelector('.credit');
                const normalBalance = currentRow.dataset.normalBalance || 'Debit';

                if (normalBalance === 'Debit') {
                    credit.value = '0.00';
                }

                if (normalBalance === 'Credit') {
                    debit.value = '0.00';
                }

                recalc();
            });

            input.addEventListener('blur', () => {
                input.value = parseMoney(input.value).toFixed(2);
                recalc();
            });
        });
    }

    function addRow(accountId = '', debit = 0, credit = 0, partyId = '', remarks = '') {
        if (!openingCanEdit) {
            showLockedMessage();
            return;
        }

        tbody.querySelectorAll('[data-empty="true"]').forEach((emptyRow) => emptyRow.remove());

        const row = document.createElement('tr');

        tbody.appendChild(row);

        makeRowEditable(row, {
            account_id: accountId,
            party_id: partyId,
            debit_opening: debit,
            credit_opening: credit,
            remarks,
        });

        return row;
    }

    function commitEditableRows() {
        dataRows().forEach((row) => {
            if (!isEditableRow(row)) {
                return;
            }

            makeRowViewOnly(row, rowData(row));
        });
    }

    function validateBeforeSubmit(event) {
        const status = statusInput.value;

        if (status === 'Draft') {
            commitEditableRows();
        }

        const difference = recalc();
        const rows = dataRows();

        if (status === 'Final' && Math.abs(difference) >= 0.01) {
            event.preventDefault();
            event.stopImmediatePropagation();

            showToast('Opening balance total debit must equal total credit before final posting. Save as Draft if you are still editing.');
            return false;
        }

        const hasAmount = rows.some((row) => {
            const debit = row.querySelector('.debit');
            const credit = row.querySelector('.credit');

            return parseMoney(debit?.value) > 0
                || parseMoney(credit?.value) > 0;
        });

        if (!hasAmount) {
            event.preventDefault();
            event.stopImmediatePropagation();

            showToast('At least one opening balance row must have a debit or credit amount.');
            return false;
        }

        const invalidPartyRow = rows.find((row) => {
            const party = row.querySelector('.party-select');

            return party && party.dataset.partyRequired === 'true' && !party.value;
        });

        if (invalidPartyRow) {
            event.preventDefault();
            event.stopImmediatePropagation();

            invalidPartyRow.querySelector('.party-select').focus({ preventScroll: true });
            showToast('Party / Sub-ledger is required for receivable, payable, and advance opening balances.');
            return false;
        }

        const unavailablePartyRow = rows.find((row) => {
            const party = row.querySelector('.party-select');

            return party && party.dataset.partyRequired === 'true' && party.disabled;
        });

        if (unavailablePartyRow) {
            event.preventDefault();
            event.stopImmediatePropagation();

            unavailablePartyRow.querySelector('.account-select').focus({ preventScroll: true });
            showToast('This ledger requires a linked party. Create or link the party first in Party / Person Setup.');
            return false;
        }

        if (status === 'Final') {
            const confirmed = confirm('Post opening balance now? Once posted, it cannot be edited directly.');

            if (!confirmed) {
                event.preventDefault();
                event.stopImmediatePropagation();

                return false;
            }
        }

        return true;
    }

    function handleOpeningRowAction(button) {
        if (!button) {
            return;
        }

        if (button.disabled) {
            showLockedMessage();
            return;
        }

        const row = button.closest('tr');

        if (!isDataRow(row)) {
            return;
        }

        const action = button.dataset.openingAction
            || (button.classList.contains('edit-opening-row') ? 'edit' : null)
            || (button.classList.contains('done-opening-row') ? 'done' : null)
            || (button.classList.contains('cancel-opening-row') ? 'cancel' : null);

        if (action === 'edit') {
            if (!openingCanEdit || button.getAttribute('aria-disabled') === 'true') {
                showLockedMessage();
                return;
            }

            makeRowEditable(row, rowData(row), true);
            row.querySelector('.account-select, .debit, .credit, .remarks-input')?.focus({ preventScroll: false });
            showToast('Opening balance row loaded for editing. Click Save Draft to update.');
            return;
        }

        if (action === 'done') {
            makeRowViewOnly(row, rowData(row));
            showToast('Row changes kept. Click Save Draft to update opening balance.');
            return;
        }

        if (action === 'cancel') {
            const original = row.dataset.originalRow
                ? JSON.parse(row.dataset.originalRow)
                : null;

            if (original) {
                makeRowViewOnly(row, original);
                showToast('Row edit cancelled.');
            } else {
                row.remove();
                ensureEmptyRow();
                updateRowIndexes();
                recalc();
                showToast('New opening balance row removed.');
            }
        }
    }

    tbody.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-opening-action], .edit-opening-row, .done-opening-row, .cancel-opening-row');

        if (!button) {
            return;
        }

        event.preventDefault();
        handleOpeningRowAction(button);
    });

    dataRows().forEach((row) => {
        if (isEditableRow(row)) {
            bindRow(row);
            updateAccountInfo(row, true);
        }
    });

    updateRowIndexes();

    document.getElementById('addRowBtn')?.addEventListener('click', () => {
        addRow();
        showToast('New opening balance row added.');
    });


    document.getElementById('validateBtn')?.addEventListener('click', () => {
        const difference = recalc();

        showToast(
            Math.abs(difference) < 0.01
                ? 'Balances validated successfully.'
                : 'Balance difference found. Please adjust debit or credit.'
        );
    });

    document.getElementById('downloadBtn')?.addEventListener('click', () => {
        showToast('Excel template download will be added in import phase.');
    });

    document.getElementById('importBtn')?.addEventListener('click', () => {
        showToast('Excel import will be added in import phase.');
    });

    document.getElementById('uploadExcelBtn')?.addEventListener('click', () => {
        showToast('Excel upload will be added in import phase.');
    });

    document.getElementById('cancelBtn')?.addEventListener('click', () => {
        window.location.reload();
    });

    saveDraftBtn?.addEventListener('click', () => {
        statusInput.value = 'Draft';
    });

    finishBtn?.addEventListener('click', () => {
        statusInput.value = 'Final';
    });

    if (financialYear) {
        financialYear.addEventListener('change', () => {
            const params = new URLSearchParams(window.location.search);

            params.set('financial_year_id', financialYear.value);

            if (branchLocation?.value) {
                params.set('branch_location', branchLocation.value);
            }

            if (balanceDate?.value) {
                params.set('balance_date', balanceDate.value);
            }

            window.location.href = `${window.location.pathname}?${params.toString()}`;
        });
    }

    branchLocation?.addEventListener('change', () => {
        const params = new URLSearchParams();

        if (financialYear?.value) {
            params.set('financial_year_id', financialYear.value);
        }

        if (branchLocation.value) {
            params.set('branch_location', branchLocation.value);
        }

        if (balanceDate?.value) {
            params.set('balance_date', balanceDate.value);
        }

        window.location.href = `${window.location.pathname}?${params.toString()}`;
    });

    async function submitOpeningBalance(event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        if (!openingCanEdit) {
            showLockedMessage();
            return;
        }

        if (event.submitter?.id === 'saveDraftBtn') {
            statusInput.value = 'Draft';
        }

        if (event.submitter?.id === 'finishBtn') {
            statusInput.value = 'Final';
        }

        if (validateBeforeSubmit(event) === false) {
            return;
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const submitButton = event.submitter || form.querySelector('button[type="submit"]');
        const originalText = submitButton?.textContent;

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = statusInput.value === 'Final' ? 'Posting...' : 'Saving...';
        }

        try {
            const response = await fetch(form.dataset.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: new FormData(form),
            });

            const result = await parseJsonResponse(response);

            if (!response.ok || result.success === false) {
                showToast(firstValidationMessage(result.errors) || result.message || 'Opening balance could not be saved.');

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }

                return;
            }

            showToast(result.message || form.dataset.success || 'Opening balance saved.');

            setTimeout(() => {
                window.location.href = result.redirect || window.location.href;
            }, 700);
        } catch (error) {
            console.error(error);
            showToast(error?.message || 'Opening balance save failed. Check storage/logs/laravel.log.');

            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        }
    }

    form.addEventListener('submit', submitOpeningBalance, true);

    if (!openingCanEdit) {
        form.querySelectorAll('input:not([type="hidden"]), select, textarea, button').forEach((element) => {
            if (
                element.id === 'validateBtn'
                || element.id === 'downloadBtn'
                || element.id === 'cancelBtn'
                || element.classList.contains('edit-opening-row')
            ) {
                return;
            }

            element.disabled = true;
        });
    }

    recalc();
});
</script>
@endpush