@extends('layouts.app')

@section('title', 'Opening Balance Setup | Accounting System')

@section('content')
@php
    $rows = $openingBalances->isNotEmpty()
        ? $openingBalances->map(fn ($balance) => [
            'account' => $balance->account,
            'party_id' => $balance->party_id,
            'debit_opening' => $balance->debit_opening,
            'credit_opening' => $balance->credit_opening,
            'remarks' => $balance->remarks,
        ])
        : $seedOpeningRows;

    if ($rows->isEmpty()) {
        $rows = collect([
            [
                'account' => null,
                'party_id' => null,
                'debit_opening' => 0,
                'credit_opening' => 0,
                'remarks' => null,
            ],
        ]);
    }

    $accountPayload = $accounts->map(fn ($account) => [
        'id' => $account->id,
        'account_code' => $account->account_code,
        'account_name' => $account->account_name,
        'display_name' => $account->display_name,
        'account_type' => $account->accountType?->name ?? 'Asset',
        'normal_balance' => $account->accountType?->normal_balance ?? 'Debit',
    ])->values();

    $partyPayload = $parties->map(fn ($party) => [
        'id' => $party->id,
        'party_name' => $party->party_name,
        'party_code' => $party->party_code,
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
@endphp

<div class="page-title">
    <div>
        <span class="page-label">Opening Balance Setup</span>
        <h2>Opening Balance Setup</h2>
        <p>Rows are auto-loaded from active posting ledger accounts. Enter only the opening debit or credit amounts before going live.</p>
    </div>
</div>

@include('partials.setup-progress', ['current' => 7])

<form
    id="openingBalanceForm"
    data-frontend-form
    data-action="{{ route('api.opening-balances.store') }}"
    data-success="Opening balance saved."
>
    @csrf

    <input type="hidden" name="status" id="openingStatus" value="Draft">

    <div class="layout">
        <div class="left-stack">
            <div class="card toolbar opening-toolbar">
            <div>
                <label>Financial Year <span class="required">*</span></label>

                <select name="financial_year_id" id="financialYearId" required>
                    <option value="">Select Financial Year</option>
                    @foreach($financialYears as $financialYear)
                        <option
                            value="{{ $financialYear->id }}"
                            @selected($currentFinancialYear?->id === $financialYear->id)
                        >
                            {{ $financialYear->name }} ({{ optional($financialYear->start_date)->format('d M Y') }} - {{ optional($financialYear->end_date)->format('d M Y') }})
                        </option>
                    @endforeach
                </select>
            </div>

                <div>
                    <label>Branch / Location</label>
                    <select name="branch_location" id="branchLocation">
                        @foreach($branches as $branch)
                            <option value="{{ $branch }}" @selected($branchLocation === $branch)>
                                {{ $branch }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button class="btn-outline" id="importBtn" type="button">
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
                        <p>Active posting ledger accounts are shown automatically. Party rows are generated from linked Party / Person setup when available.</p>
                    </div>

                    <button class="btn-outline" id="downloadBtn" type="button">
                        ⇩ Download Template
                    </button>
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
                            </tr>
                        </thead>

                        <tbody id="balanceTable">
                            @foreach($rows as $index => $row)
                                @php
                                    $account = $row['account'];
                                    $type = $account?->accountType?->name ?? 'Asset';
                                    $normalBalance = $account?->accountType?->normal_balance ?? 'Debit';
                                @endphp

                                <tr data-type="{{ $type }}" data-normal-balance="{{ $normalBalance }}">
                                    <td>{{ $index + 1 }}</td>

                                    <td>
                                        <input
                                            class="code-input account-code"
                                            value="{{ $account?->account_code }}"
                                            readonly
                                        >
                                    </td>

                                    <td>
                                        <select
                                            class="account-select"
                                            name="items[{{ $index }}][account_id]"
                                            required
                                        >
                                            <option value="">Select Account</option>
                                            @foreach($accounts as $optionAccount)
                                                <option
                                                    value="{{ $optionAccount->id }}"
                                                    @selected($account?->id === $optionAccount->id)
                                                >
                                                    {{ $optionAccount->display_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <span class="badge account-type {{ $badgeClass($type) }}">
                                            {{ $type }}
                                        </span>
                                    </td>

                                    <td>
                                        <select
                                            class="party-select"
                                            name="items[{{ $index }}][party_id]"
                                        >
                                            <option value="">—</option>
                                            @foreach($parties as $party)
                                                <option
                                                    value="{{ $party->id }}"
                                                    data-linked-account="{{ $party->linked_ledger_account_id }}"
                                                    @selected((int) $row['party_id'] === (int) $party->id)
                                                >
                                                    {{ $party->party_code }} - {{ $party->party_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <input
                                            class="money-input debit"
                                            name="items[{{ $index }}][debit_opening]"
                                            value="{{ number_format((float) $row['debit_opening'], 2, '.', '') }}"
                                            inputmode="decimal"
                                            @readonly($normalBalance === 'Credit')
                                        >
                                    </td>

                                    <td>
                                        <input
                                            class="money-input credit"
                                            name="items[{{ $index }}][credit_opening]"
                                            value="{{ number_format((float) $row['credit_opening'], 2, '.', '') }}"
                                            inputmode="decimal"
                                            @readonly($normalBalance === 'Debit')
                                        >
                                    </td>

                                    <td class="net-dr">0.00 Dr</td>

                                    <td>
                                        <input
                                            class="remarks-input"
                                            name="items[{{ $index }}][remarks]"
                                            value="{{ $row['remarks'] }}"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="add-row" id="addRowBtn">
                    + Add Extra Row
                </div>

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
                        <button class="btn-outline" id="saveDraftBtn" type="submit">
                            Save Draft
                        </button>

                        <button class="btn-primary" id="finishBtn" type="submit">
                            Save & Finish Setup ✓
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <aside class="right-stack">

            <div class="card summary-card">
                <h3>Balance Summary</h3>

                <div class="summary-row">
                    <span>Total Debit</span>
                    <strong id="sideDebit">BDT 0.00</strong>
                </div>

                <div class="summary-row">
                    <span>Total Credit</span>
                    <strong id="sideCredit">BDT 0.00</strong>
                </div>

                <div class="summary-row">
                    <span>Difference</span>
                    <strong id="sideDifference" class="green">BDT 0.00</strong>
                </div>
            </div>

            <div class="card ready-card">
                <h3>Go-live Readiness</h3>

                <div class="ready-list">
                    <div><span class="checkmark">✓</span>Chart of Accounts Created</div>
                    <div><span class="checkmark">✓</span>Parties / Sub-ledgers Added</div>
                    <div><span class="checkmark">✓</span>Ledger Mapping Completed</div>
                    <div><span class="checkmark">✓</span>Opening Balances Entered</div>
                    <div><span class="checkmark">✓</span>Balances Validated</div>
                </div>
            </div>

            <div class="card import-card">
                <h3>Import from Excel</h3>
                <p>Upload your opening balances using our Excel template.</p>

                <button class="btn-outline" type="button" style="width:100%" id="uploadExcelBtn">
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const accounts = @json($accountPayload);
    const parties = @json($partyPayload);

    const tbody = document.getElementById('balanceTable');
    const form = document.getElementById('openingBalanceForm');
    const statusInput = document.getElementById('openingStatus');

    const branchLocation = document.getElementById('branchLocation');
    const financialYear = document.getElementById('financialYearId');

    const saveDraftBtn = document.getElementById('saveDraftBtn');
    const finishBtn = document.getElementById('finishBtn');

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

    function accountOptions(selectedId = '') {
        return [
            '<option value="">Select Account</option>',
            ...accounts.map((account) => {
                const selected = String(account.id) === String(selectedId) ? 'selected' : '';

                return `<option value="${account.id}" ${selected}>${account.display_name}</option>`;
            }),
        ].join('');
    }

    function partyOptions(accountId, selectedId = '') {
        const rows = linkedParties(accountId);

        if (rows.length === 0) {
            return '<option value="">Default / Not Applicable</option>';
        }

        return [
            '<option value="">Select Party</option>',
            ...rows.map((party) => {
                const selected = String(party.id) === String(selectedId) ? 'selected' : '';

                return `<option value="${party.id}" data-linked-account="${party.linked_ledger_account_id}" ${selected}>${party.party_code} - ${party.party_name}</option>`;
            }),
        ].join('');
    }

    function updateRowIndexes() {
        Array.from(tbody.rows).forEach((row, index) => {
            row.children[0].textContent = String(index + 1);

            row.querySelector('.account-select').name = `items[${index}][account_id]`;
            row.querySelector('.party-select').name = `items[${index}][party_id]`;
            row.querySelector('.debit').name = `items[${index}][debit_opening]`;
            row.querySelector('.credit').name = `items[${index}][credit_opening]`;
            row.querySelector('.remarks-input').name = `items[${index}][remarks]`;
        });
    }

    function applyNormalBalance(row, account) {
        const debit = row.querySelector('.debit');
        const credit = row.querySelector('.credit');

        if ((account?.normal_balance || 'Debit') === 'Debit') {
            credit.value = '0.00';
            credit.readOnly = true;
            debit.readOnly = false;
        } else {
            debit.value = '0.00';
            debit.readOnly = true;
            credit.readOnly = false;
        }
    }

    function updateAccountInfo(row, keepParty = false) {
        const accountSelect = row.querySelector('.account-select');
        const account = accountById(accountSelect.value);

        row.querySelector('.account-code').value = account?.account_code || '';

        const type = account?.account_type || 'Asset';
        const badge = row.querySelector('.account-type');

        badge.textContent = type;
        badge.className = `badge account-type ${badgeClass(type)}`;

        row.dataset.type = type;
        row.dataset.normalBalance = account?.normal_balance || 'Debit';

        const partySelect = row.querySelector('.party-select');
        const oldParty = keepParty ? partySelect.value : '';

        partySelect.innerHTML = partyOptions(account?.id || '', oldParty);

        const hasLinkedParties = linkedParties(account?.id || '').length > 0;
        partySelect.required = hasLinkedParties;
        partySelect.disabled = !hasLinkedParties;

        applyNormalBalance(row, account);
        recalc();
    }

    function recalc() {
        let totalDebit = 0;
        let totalCredit = 0;

        Array.from(tbody.rows).forEach((row) => {
            const debit = parseMoney(row.querySelector('.debit').value);
            const credit = parseMoney(row.querySelector('.credit').value);

            totalDebit += debit;
            totalCredit += credit;

            const net = debit - credit;
            const netCell = row.children[7];

            netCell.className = net >= 0 ? 'net-dr' : 'net-cr';
            netCell.textContent = `${money(Math.abs(net))}${net >= 0 ? ' Dr' : ' Cr'}`;
        });

        const difference = totalDebit - totalCredit;

        document.getElementById('totalDebit').textContent = money(totalDebit);
        document.getElementById('totalCredit').textContent = money(totalCredit);
        document.getElementById('difference').textContent = `${money(difference)} BDT`;

        document.getElementById('sideDebit').textContent = `BDT ${money(totalDebit)}`;
        document.getElementById('sideCredit').textContent = `BDT ${money(totalCredit)}`;
        document.getElementById('sideDifference').textContent = `BDT ${money(difference)}`;
        document.getElementById('sideDifference').className = Math.abs(difference) < 0.01 ? 'green' : 'red';

        const matchBox = document.getElementById('matchBox');

        if (Math.abs(difference) < 0.01) {
            matchBox.className = 'match-box';
            matchBox.innerHTML = '✓ Balances Matched<br><small>Difference is 0.00 BDT</small>';
        } else {
            matchBox.className = 'match-box error';
            matchBox.innerHTML = `⚠ Balances Not Matched<br><small>Difference is ${money(difference)} BDT</small>`;
        }

        return difference;
    }

    function bindRow(row) {
        row.querySelector('.account-select').addEventListener('change', () => {
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
        });
    }

    function addRow() {
        const index = tbody.rows.length;

        const row = document.createElement('tr');
        row.dataset.type = 'Asset';
        row.dataset.normalBalance = 'Debit';

        row.innerHTML = `
            <td>${index + 1}</td>
            <td><input class="code-input account-code" value="" readonly></td>
            <td><select class="account-select" name="items[${index}][account_id]" required>${accountOptions('')}</select></td>
            <td><span class="badge account-type ${badgeClass('Asset')}">Asset</span></td>
            <td><select class="party-select" name="items[${index}][party_id]"><option value="">Default / Not Applicable</option></select></td>
            <td><input class="money-input debit" name="items[${index}][debit_opening]" value="0.00" inputmode="decimal"></td>
            <td><input class="money-input credit" name="items[${index}][credit_opening]" value="0.00" inputmode="decimal"></td>
            <td class="net-dr">0.00 Dr</td>
            <td><input class="remarks-input" name="items[${index}][remarks]" value=""></td>
        `;

        tbody.appendChild(row);
        bindRow(row);
        updateAccountInfo(row, false);
        updateRowIndexes();
        recalc();

        showToast('Extra opening balance row added.');
    }

    Array.from(tbody.rows).forEach((row) => {
        bindRow(row);
        updateAccountInfo(row, true);
    });

    document.getElementById('addRowBtn').addEventListener('click', addRow);

    document.getElementById('validateBtn').addEventListener('click', () => {
        const difference = recalc();

        showToast(
            Math.abs(difference) < 0.01
                ? 'Balances validated successfully.'
                : 'Balance difference found. Please adjust debit or credit.'
        );
    });

    document.getElementById('downloadBtn').addEventListener('click', () => {
        showToast('Excel template download will be added in import phase.');
    });

    document.getElementById('importBtn').addEventListener('click', () => {
        showToast('Excel import will be added in import phase.');
    });

    document.getElementById('uploadExcelBtn').addEventListener('click', () => {
        showToast('Excel upload will be added in import phase.');
    });

    document.getElementById('cancelBtn').addEventListener('click', () => {
        window.location.reload();
    });

    saveDraftBtn.addEventListener('click', () => {
        statusInput.value = 'Draft';
    });

    finishBtn.addEventListener('click', () => {
        statusInput.value = 'Final';
    });

    if (financialYear) {
        financialYear.addEventListener('change', () => {
            const params = new URLSearchParams(window.location.search);
            params.set('financial_year_id', financialYear.value);
            params.set('branch_location', branchLocation.value);
            window.location.href = `${window.location.pathname}?${params.toString()}`;
        });
    }

    branchLocation.addEventListener('change', () => {
        const params = new URLSearchParams();

        if (financialYear?.value) {
            params.set('financial_year_id', financialYear.value);
        }

        if (branchLocation.value) {
            params.set('branch_location', branchLocation.value);
        }

        window.location.href = `${window.location.pathname}?${params.toString()}`;
    });

    form.addEventListener('submit', (event) => {
        const difference = recalc();

        if (Math.abs(difference) >= 0.01) {
            event.preventDefault();
            showToast('Total debit opening balance must equal total credit opening balance.');
            return;
        }

        const rows = Array.from(tbody.rows);

        const hasAmount = rows.some((row) => {
            return parseMoney(row.querySelector('.debit').value) > 0
                || parseMoney(row.querySelector('.credit').value) > 0;
        });

        if (!hasAmount) {
            event.preventDefault();
            showToast('At least one opening balance row must have a debit or credit amount.');
            return;
        }

        const invalidPartyRow = rows.find((row) => {
            const party = row.querySelector('.party-select');

            return party.required && !party.value;
        });

        if (invalidPartyRow) {
            event.preventDefault();
            invalidPartyRow.querySelector('.party-select').focus();
            showToast('Party / Sub-ledger is required for this ledger account.');
        }
    });

    recalc();
});
</script>
@endpush
