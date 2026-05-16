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

                <button class="btn-outline" id="importBtn" type="button" @disabled($openingIsFinal)>
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
                            Use only posting ledger accounts. Receivable, payable, and advance balances require Party / Sub-ledger.
                        </p>
                    </div>

                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button class="btn-outline" id="loadSampleBtn" type="button" @disabled($openingIsFinal)>
                            Load PRD Sample
                        </button>

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
                            </tr>
                        </thead>

                        <tbody id="balanceTable">
                            @foreach($rows as $index => $row)
                                @php
                                    $account = $row['account'];
                                    $type = $account?->accountType?->name ?? 'Asset';
                                    $normalBalance = $account?->normal_balance ?: $account?->accountType?->normal_balance ?: 'Debit';
                                @endphp

                                <tr
                                    data-type="{{ $type }}"
                                    data-normal-balance="{{ $normalBalance }}"
                                >
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
                                            @disabled($openingIsFinal)
                                        >
                                            <option value="">Select Account</option>

                                            @foreach($accounts as $optionAccount)
                                                <option
                                                    value="{{ $optionAccount->id }}"
                                                    @selected($account?->id === $optionAccount->id)
                                                >
                                                    {{ $optionAccount->display_name ?: trim($optionAccount->account_code . ' - ' . $optionAccount->account_name) }}
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
                                            @disabled($openingIsFinal)
                                        >
                                            <option value="">—</option>

                                            @foreach($parties as $party)
                                                <option
                                                    value="{{ $party->id }}"
                                                    data-linked-account="{{ $party->linked_ledger_account_id }}"
                                                    @selected((int) $row['party_id'] === (int) $party->id)
                                                >
                                                    {{ $party->party_code ? $party->party_code . ' - ' : '' }}{{ $party->party_name }}
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
                                            @readonly($normalBalance === 'Credit' || $openingIsFinal)
                                        >
                                    </td>

                                    <td>
                                        <input
                                            class="money-input credit"
                                            name="items[{{ $index }}][credit_opening]"
                                            value="{{ number_format((float) $row['credit_opening'], 2, '.', '') }}"
                                            inputmode="decimal"
                                            @readonly($normalBalance === 'Debit' || $openingIsFinal)
                                        >
                                    </td>

                                    <td class="net-dr">0.00 Dr</td>

                                    <td>
                                        <input
                                            class="remarks-input"
                                            name="items[{{ $index }}][remarks]"
                                            value="{{ $row['remarks'] }}"
                                            @readonly($openingIsFinal)
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button
                    class="add-row"
                    id="addRowBtn"
                    type="button"
                    @disabled($openingIsFinal)
                >
                    + Add Extra Row
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
                        <button class="btn-outline" id="saveDraftBtn" type="submit" @disabled($openingIsFinal)>
                            Save Draft
                        </button>

                        <button class="btn-primary" id="finishBtn" type="submit" @disabled($openingIsFinal)>
                            Post Opening Balance
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

                <button class="btn-outline" type="button" style="width:100%" id="uploadExcelBtn" @disabled($openingIsFinal)>
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
    const openingIsFinal = @json($openingIsFinal);

    const tbody = document.getElementById('balanceTable');
    const form = document.getElementById('openingBalanceForm');
    const statusInput = document.getElementById('openingStatus');

    const branchLocation = document.getElementById('branchLocation');
    const balanceDate = document.getElementById('balanceDate');
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
        const normalBalance = account?.normal_balance || 'Debit';

        row.dataset.normalBalance = normalBalance;

        if (normalBalance === 'Debit') {
            credit.value = '0.00';
            credit.readOnly = true;
            debit.readOnly = openingIsFinal;
        } else {
            debit.value = '0.00';
            debit.readOnly = true;
            credit.readOnly = openingIsFinal;
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
        const requiresParty = accountRequiresParty(account);
        const availableParties = linkedParties(account?.id || '');

        partySelect.innerHTML = partyOptions(account?.id || '', oldParty);
        partySelect.dataset.partyRequired = requiresParty ? 'true' : 'false';
        partySelect.required = requiresParty && availableParties.length > 0;
        partySelect.disabled = openingIsFinal || availableParties.length === 0;

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

            input.addEventListener('blur', () => {
                input.value = parseMoney(input.value).toFixed(2);
                recalc();
            });
        });
    }

    function addRow(accountId = '', debit = 0, credit = 0, partyId = '', remarks = '') {
        if (openingIsFinal) {
            showToast('Opening balance is finalized and cannot be edited.');
            return;
        }

        const index = tbody.rows.length;

        const row = document.createElement('tr');
        row.dataset.type = 'Asset';
        row.dataset.normalBalance = 'Debit';

        row.innerHTML = `
            <td>${index + 1}</td>
            <td><input class="code-input account-code" value="" readonly></td>
            <td><select class="account-select" name="items[${index}][account_id]" required>${accountOptions(accountId)}</select></td>
            <td><span class="badge account-type ${badgeClass('Asset')}">Asset</span></td>
            <td><select class="party-select" name="items[${index}][party_id]"><option value="">Default / Not Applicable</option></select></td>
            <td><input class="money-input debit" name="items[${index}][debit_opening]" value="${Number(debit).toFixed(2)}" inputmode="decimal"></td>
            <td><input class="money-input credit" name="items[${index}][credit_opening]" value="${Number(credit).toFixed(2)}" inputmode="decimal"></td>
            <td class="net-dr">0.00 Dr</td>
            <td><input class="remarks-input" name="items[${index}][remarks]" value="${escapeHtml(remarks)}"></td>
        `;

        tbody.appendChild(row);
        bindRow(row);
        updateAccountInfo(row, false);

        const partySelect = row.querySelector('.party-select');

        if (partyId) {
            partySelect.value = partyId;
        }

        updateRowIndexes();
        recalc();

        return row;
    }

    function findAccountByNames(names) {
        const upperNames = names.map((name) => name.toUpperCase());

        return accounts.find((account) => {
            const label = `${account.account_code} ${account.account_name} ${account.display_name}`.toUpperCase();

            return upperNames.some((name) => label.includes(name));
        });
    }

    function firstLinkedParty(accountId) {
        return linkedParties(accountId)[0]?.id || '';
    }

    function loadPrdSample() {
        if (openingIsFinal) {
            showToast('Opening balance is finalized and cannot be edited.');
            return;
        }

        const sample = [
            {
                account: findAccountByNames(['Cash in Hand', 'Cash']),
                debit: 50000,
                credit: 0,
                remarks: 'Sample opening cash balance',
            },
            {
                account: findAccountByNames(['Bank Account', 'Bank']),
                debit: 100000,
                credit: 0,
                remarks: 'Sample opening bank balance',
            },
            {
                account: findAccountByNames(['Accounts Receivable', 'Customer Due', 'Receivable']),
                debit: 20000,
                credit: 0,
                remarks: 'Sample customer opening due',
            },
            {
                account: findAccountByNames(['Inventory / Stock', 'Seed Inventory', 'Stock Value', 'Inventory']),
                debit: 80000,
                credit: 0,
                remarks: 'Sample opening inventory value',
            },
            {
                account: findAccountByNames(['Accounts Payable', 'Supplier Due', 'Payable']),
                debit: 0,
                credit: 30000,
                remarks: 'Sample supplier opening payable',
            },
            {
                account: findAccountByNames(['Owner Capital', 'Capital']),
                debit: 0,
                credit: 220000,
                remarks: 'Sample owner capital balancing figure',
            },
        ].filter((item) => item.account);

        if (sample.length === 0) {
            showToast('No matching chart of accounts found for the PRD sample.');
            return;
        }

        tbody.innerHTML = '';

        sample.forEach((item) => {
            const partyId = firstLinkedParty(item.account.id);

            addRow(
                item.account.id,
                item.debit,
                item.credit,
                partyId,
                item.remarks
            );
        });

        recalc();
        showToast('PRD sample opening balance loaded. Review party rows before posting.');
    }

    function validateBeforeSubmit(event) {
        const difference = recalc();
        const status = statusInput.value;

        if (Math.abs(difference) >= 0.01) {
            event.preventDefault();
            event.stopImmediatePropagation();

            showToast('Opening balance total debit must equal total credit before posting.');
            return false;
        }

        const rows = Array.from(tbody.rows);

        const hasAmount = rows.some((row) => {
            return parseMoney(row.querySelector('.debit').value) > 0
                || parseMoney(row.querySelector('.credit').value) > 0;
        });

        if (!hasAmount) {
            event.preventDefault();
            event.stopImmediatePropagation();

            showToast('At least one opening balance row must have a debit or credit amount.');
            return false;
        }

        const invalidPartyRow = rows.find((row) => {
            const party = row.querySelector('.party-select');

            return party.dataset.partyRequired === 'true' && !party.value;
        });

        if (invalidPartyRow) {
            event.preventDefault();
            event.stopImmediatePropagation();

            invalidPartyRow.querySelector('.party-select').focus();
            showToast('Party / Sub-ledger is required for receivable, payable, and advance opening balances.');
            return false;
        }

        const unavailablePartyRow = rows.find((row) => {
            const party = row.querySelector('.party-select');

            return party.dataset.partyRequired === 'true' && party.disabled;
        });

        if (unavailablePartyRow) {
            event.preventDefault();
            event.stopImmediatePropagation();

            unavailablePartyRow.querySelector('.account-select').focus();
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

    Array.from(tbody.rows).forEach((row) => {
        bindRow(row);
        updateAccountInfo(row, true);
    });

    document.getElementById('addRowBtn')?.addEventListener('click', () => {
        addRow();
        showToast('Extra opening balance row added.');
    });

    document.getElementById('loadSampleBtn')?.addEventListener('click', loadPrdSample);

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

    form.addEventListener('submit', validateBeforeSubmit, true);

    if (openingIsFinal) {
        form.querySelectorAll('input, select, textarea, button').forEach((element) => {
            if (element.id === 'validateBtn' || element.id === 'downloadBtn' || element.id === 'cancelBtn') {
                return;
            }

            element.disabled = true;
        });
    }

    recalc();
});
</script>
@endpush