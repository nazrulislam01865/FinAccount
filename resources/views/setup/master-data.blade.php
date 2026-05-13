@extends('layouts.app')

@section('title', 'Master Data Setup | Accounting System')

@push('styles')
<style>
    /* Master data uses the existing card, table, badge, and form styles without changing global design. */
    .master-data-grid {
        display: grid;
        gap: 22px;
    }

    .master-section {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 330px;
        gap: 22px;
        align-items: start;
    }

    .master-form {
        display: grid;
        gap: 14px;
    }

    .master-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
        border-bottom: 1px solid var(--line);
    }

    .master-card-head h3 {
        margin: 0 0 4px;
        font-size: 17px;
    }

    .master-card-head p {
        margin: 0;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.45;
    }

    .master-form-wrap {
        padding: 20px;
    }

    @media (max-width: 1320px) {
        .master-section {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Master Data</span>
        <h2>Master Data Setup</h2>
        <p>Add or update business types, currencies, settlement types, party types, and financial years used across setup and transaction forms.</p>
    </div>
</div>

<div class="master-data-grid">
    <section class="master-section" id="businessTypesSection">
        <div class="card table-card">
            <div class="master-card-head">
                <div>
                    <h3>Business Types</h3>
                    <p>These values appear in Company Setup business type dropdown.</p>
                </div>
                <span class="badge badge-primary">{{ $businessTypes->count() }} Items</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Default</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($businessTypes as $type)
                            <tr
                                data-id="{{ $type->id }}"
                                data-name="{{ e($type->name) }}"
                                data-code="{{ e($type->code) }}"
                                data-description="{{ e($type->description) }}"
                                data-is-default="{{ $type->is_default ? 1 : 0 }}"
                                data-sort-order="{{ $type->sort_order }}"
                                data-status="{{ $type->status }}"
                                data-update-url="{{ route('api.master-data.business-types.update', $type) }}"
                            >
                                <td class="strong">{{ $type->name }}</td>
                                <td>{{ $type->code }}</td>
                                <td>{{ $type->description ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $type->is_default ? 'badge-active' : 'badge-neutral' }}">
                                        {{ $type->is_default ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td>{{ $type->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $type->status === 'Active' ? 'badge-active' : 'badge-neutral' }}">
                                        {{ $type->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn js-master-edit" type="button" data-target="businessTypeForm" title="Edit">✎</button>
                                        <form method="POST" action="{{ route('setup.master-data.business-types.destroy', $type) }}" data-delete-form onsubmit="return confirm('Delete this business type?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty="true">
                                <td colspan="7" style="text-align:center;padding:24px;color:var(--muted)">No business types found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card form-panel">
            <div class="panel-head">
                <div>
                    <h3>Add / Edit Business Type</h3>
                    <span>Example: Trading Business, Service Business, Fleet Management.</span>
                </div>
            </div>

            <form
                class="form-grid master-form"
                id="businessTypeForm"
                data-frontend-form
                data-action="{{ route('api.master-data.business-types.store') }}"
                data-store-url="{{ route('api.master-data.business-types.store') }}"
                data-success="Business type saved successfully."
            >
                @csrf
                <input type="hidden" name="_method" value="POST">

                <div>
                    <label>Name <span class="required">*</span></label>
                    <input name="name" placeholder="Trading Business" required>
                </div>

                <div>
                    <label>Code <span class="required">*</span></label>
                    <input name="code" placeholder="TRADING" required>
                    <div class="inline-help">Uppercase letters, numbers, and underscores only.</div>
                </div>

                <div>
                    <label>Description</label>
                    <textarea name="description" placeholder="Optional description"></textarea>
                </div>

                <div>
                    <label>Default Business Type</label>
                    <input type="hidden" id="businessTypeDefault" name="is_default" value="0">
                    <div class="switch" data-input="businessTypeDefault"></div>
                    <div class="inline-help">When enabled, other business types are marked non-default.</div>
                </div>

                <div>
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="0">
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost js-master-reset">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </aside>
    </section>

    <section class="master-section" id="currenciesSection">
        <div class="card table-card">
            <div class="master-card-head">
                <div>
                    <h3>Currencies</h3>
                    <p>These values appear in Company Setup currency dropdown.</p>
                </div>
                <span class="badge badge-primary">{{ $currencies->count() }} Items</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Symbol</th>
                            <th>Decimals</th>
                            <th>Default</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($currencies as $currency)
                            <tr
                                data-id="{{ $currency->id }}"
                                data-code="{{ e($currency->code) }}"
                                data-name="{{ e($currency->name) }}"
                                data-symbol="{{ e($currency->symbol) }}"
                                data-decimal-places="{{ $currency->decimal_places }}"
                                data-is-default="{{ $currency->is_default ? 1 : 0 }}"
                                data-sort-order="{{ $currency->sort_order }}"
                                data-status="{{ $currency->status }}"
                                data-update-url="{{ route('api.master-data.currencies.update', $currency) }}"
                            >
                                <td class="strong">{{ $currency->code }}</td>
                                <td>{{ $currency->name }}</td>
                                <td>{{ $currency->symbol ?: '—' }}</td>
                                <td>{{ $currency->decimal_places }}</td>
                                <td>
                                    <span class="badge {{ $currency->is_default ? 'badge-active' : 'badge-neutral' }}">
                                        {{ $currency->is_default ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td>{{ $currency->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $currency->status === 'Active' ? 'badge-active' : 'badge-neutral' }}">
                                        {{ $currency->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn js-master-edit" type="button" data-target="currencyForm" title="Edit">✎</button>
                                        <form method="POST" action="{{ route('setup.master-data.currencies.destroy', $currency) }}" data-delete-form onsubmit="return confirm('Delete this currency?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty="true">
                                <td colspan="8" style="text-align:center;padding:24px;color:var(--muted)">No currencies found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card form-panel">
            <div class="panel-head">
                <div>
                    <h3>Add / Edit Currency</h3>
                    <span>Example: BDT, USD, EUR.</span>
                </div>
            </div>

            <form
                class="form-grid master-form"
                id="currencyForm"
                data-frontend-form
                data-action="{{ route('api.master-data.currencies.store') }}"
                data-store-url="{{ route('api.master-data.currencies.store') }}"
                data-success="Currency saved successfully."
            >
                @csrf
                <input type="hidden" name="_method" value="POST">

                <div>
                    <label>Code <span class="required">*</span></label>
                    <input name="code" maxlength="3" placeholder="BDT" required>
                    <div class="inline-help">Use a 3-letter ISO code such as BDT or USD.</div>
                </div>

                <div>
                    <label>Name <span class="required">*</span></label>
                    <input name="name" placeholder="Bangladeshi Taka" required>
                </div>

                <div>
                    <label>Symbol</label>
                    <input name="symbol" maxlength="10" placeholder="৳">
                </div>

                <div>
                    <label>Decimal Places <span class="required">*</span></label>
                    <input type="number" name="decimal_places" min="0" max="6" value="2" required>
                </div>

                <div>
                    <label>Default Currency</label>
                    <input type="hidden" id="currencyDefault" name="is_default" value="0">
                    <div class="switch" data-input="currencyDefault"></div>
                    <div class="inline-help">When enabled, other currencies are marked non-default.</div>
                </div>

                <div>
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="0">
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost js-master-reset">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </aside>
    </section>

    <section class="master-section" id="settlementTypesSection">
        <div class="card table-card">
            <div class="master-card-head">
                <div>
                    <h3>Settlement Types</h3>
                    <p>These values appear in Transaction Head, Ledger Mapping, and Add Transaction forms.</p>
                </div>
                <span class="badge badge-primary">{{ $settlementTypes->count() }} Items</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($settlementTypes as $type)
                            <tr
                                data-id="{{ $type->id }}"
                                data-name="{{ e($type->name) }}"
                                data-code="{{ e($type->code) }}"
                                data-sort-order="{{ $type->sort_order }}"
                                data-status="{{ $type->status }}"
                                data-update-url="{{ route('api.master-data.settlement-types.update', $type) }}"
                            >
                                <td class="strong">{{ $type->name }}</td>
                                <td>{{ $type->code }}</td>
                                <td>{{ $type->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $type->status === 'Active' ? 'badge-active' : 'badge-neutral' }}">
                                        {{ $type->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn js-master-edit" type="button" data-target="settlementTypeForm" title="Edit">✎</button>
                                        <form method="POST" action="{{ route('setup.master-data.settlement-types.destroy', $type) }}" data-delete-form onsubmit="return confirm('Delete this settlement type?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty="true">
                                <td colspan="5" style="text-align:center;padding:24px;color:var(--muted)">No settlement types found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card form-panel">
            <div class="panel-head">
                <div>
                    <h3>Add / Edit Settlement Type</h3>
                    <span>Example: Cash, Bank, Due, Adjustment.</span>
                </div>
            </div>

            <form
                class="form-grid master-form"
                id="settlementTypeForm"
                data-frontend-form
                data-action="{{ route('api.master-data.settlement-types.store') }}"
                data-store-url="{{ route('api.master-data.settlement-types.store') }}"
                data-success="Settlement type saved successfully."
            >
                @csrf
                <input type="hidden" name="_method" value="POST">

                <div>
                    <label>Name <span class="required">*</span></label>
                    <input name="name" placeholder="Cash" required>
                </div>

                <div>
                    <label>Code <span class="required">*</span></label>
                    <input name="code" placeholder="CASH" required>
                    <div class="inline-help">Uppercase letters, numbers, and underscores only.</div>
                </div>

                <div>
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="0">
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost js-master-reset">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </aside>
    </section>

    <section class="master-section" id="partyTypesSection">
        <div class="card table-card">
            <div class="master-card-head">
                <div>
                    <h3>Party Types</h3>
                    <p>These values appear in Party / Person Setup and Transaction Head default party type.</p>
                </div>
                <span class="badge badge-primary">{{ $partyTypes->count() }} Items</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Default Ledger</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($partyTypes as $type)
                            <tr
                                data-id="{{ $type->id }}"
                                data-name="{{ e($type->name) }}"
                                data-code="{{ e($type->code) }}"
                                data-default-ledger-account-id="{{ $type->default_ledger_account_id }}"
                                data-sort-order="{{ $type->sort_order }}"
                                data-status="{{ $type->status }}"
                                data-update-url="{{ route('api.master-data.party-types.update', $type) }}"
                            >
                                <td class="strong">{{ $type->name }}</td>
                                <td>{{ $type->code }}</td>
                                <td>{{ $type->defaultLedger?->display_name ?? '—' }}</td>
                                <td>{{ $type->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $type->status === 'Active' ? 'badge-active' : 'badge-neutral' }}">
                                        {{ $type->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn js-master-edit" type="button" data-target="partyTypeForm" title="Edit">✎</button>
                                        <form method="POST" action="{{ route('setup.master-data.party-types.destroy', $type) }}" data-delete-form onsubmit="return confirm('Delete this party type?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty="true">
                                <td colspan="6" style="text-align:center;padding:24px;color:var(--muted)">No party types found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card form-panel">
            <div class="panel-head">
                <div>
                    <h3>Add / Edit Party Type</h3>
                    <span>Example: Employee, Supplier, Customer, Vendor.</span>
                </div>
            </div>

            <form
                class="form-grid master-form"
                id="partyTypeForm"
                data-frontend-form
                data-action="{{ route('api.master-data.party-types.store') }}"
                data-store-url="{{ route('api.master-data.party-types.store') }}"
                data-success="Party type saved successfully."
            >
                @csrf
                <input type="hidden" name="_method" value="POST">

                <div>
                    <label>Name <span class="required">*</span></label>
                    <input name="name" placeholder="Supplier" required>
                </div>

                <div>
                    <label>Code <span class="required">*</span></label>
                    <input name="code" placeholder="SUP" required>
                </div>

                <div>
                    <label>Default Ledger / Group</label>
                    <select name="default_ledger_account_id">
                        <option value="">Select Default Ledger</option>
                        @foreach($ledgerAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="0">
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost js-master-reset">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </aside>
    </section>

    <section class="master-section" id="financialYearsSection">
        <div class="card table-card">
            <div class="master-card-head">
                <div>
                    <h3>Financial Years</h3>
                    <p>These values appear in Opening Balance and Voucher Numbering setup.</p>
                </div>
                <span class="badge badge-primary">{{ $financialYears->count() }} Items</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Active Year</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($financialYears as $year)
                            <tr
                                data-id="{{ $year->id }}"
                                data-name="{{ e($year->name) }}"
                                data-start-date="{{ optional($year->start_date)->format('Y-m-d') }}"
                                data-end-date="{{ optional($year->end_date)->format('Y-m-d') }}"
                                data-is-active="{{ $year->is_active ? 1 : 0 }}"
                                data-status="{{ $year->status }}"
                                data-update-url="{{ route('api.master-data.financial-years.update', $year) }}"
                            >
                                <td class="strong">{{ $year->name }}</td>
                                <td>{{ optional($year->start_date)->format('d M Y') }}</td>
                                <td>{{ optional($year->end_date)->format('d M Y') }}</td>
                                <td>
                                    <span class="badge {{ $year->is_active ? 'badge-active' : 'badge-neutral' }}">
                                        {{ $year->is_active ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $year->status === 'Active' ? 'badge-active' : 'badge-neutral' }}">
                                        {{ $year->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="icon-btn js-master-edit" type="button" data-target="financialYearForm" title="Edit">✎</button>
                                        <form method="POST" action="{{ route('setup.master-data.financial-years.destroy', $year) }}" data-delete-form onsubmit="return confirm('Delete this financial year?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr data-empty="true">
                                <td colspan="6" style="text-align:center;padding:24px;color:var(--muted)">No financial years found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="card form-panel">
            <div class="panel-head">
                <div>
                    <h3>Add / Edit Financial Year</h3>
                    <span>Example: 2026-2027 from 01 Jul 2026 to 30 Jun 2027.</span>
                </div>
            </div>

            <form
                class="form-grid master-form"
                id="financialYearForm"
                data-frontend-form
                data-action="{{ route('api.master-data.financial-years.store') }}"
                data-store-url="{{ route('api.master-data.financial-years.store') }}"
                data-success="Financial year saved successfully."
            >
                @csrf
                <input type="hidden" name="_method" value="POST">

                <div>
                    <label>Name</label>
                    <input name="name" placeholder="2026-2027">
                    <div class="inline-help">Leave blank to generate from start and end dates.</div>
                </div>

                <div>
                    <label>Start Date <span class="required">*</span></label>
                    <input type="date" name="start_date" required>
                </div>

                <div>
                    <label>End Date <span class="required">*</span></label>
                    <input type="date" name="end_date" required>
                </div>

                <div>
                    <label>Active Financial Year</label>
                    <input type="hidden" id="financialYearActive" name="is_active" value="0">
                    <div class="switch" data-input="financialYearActive"></div>
                    <div class="inline-help">Only one financial year should be active at a time.</div>
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost js-master-reset">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </aside>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    /* Edit buttons reuse the same form design and only switch the backend endpoint. */
    document.querySelectorAll('.js-master-edit').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            const form = document.getElementById(button.dataset.target);

            if (!row || !form) {
                return;
            }

            form.dataset.action = row.dataset.updateUrl;
            form.querySelector('[name="_method"]').value = 'PUT';

            Object.entries(row.dataset).forEach(([key, value]) => {
                const name = key.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`);
                const field = form.querySelector(`[name="${name}"]`);

                if (!field) {
                    return;
                }

                field.value = value || '';
            });

            const switchInput = form.querySelector('input[type="hidden"][name="is_active"]');
            const switchElement = switchInput?.nextElementSibling;

            if (switchInput && switchElement?.classList.contains('switch')) {
                switchElement.classList.toggle('on', Number(row.dataset.isActive || 0) === 1);
                switchInput.value = switchElement.classList.contains('on') ? '1' : '0';
            }

            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    /* Cancel returns the form to create mode without changing the page layout. */
    document.querySelectorAll('.js-master-reset').forEach((button) => {
        button.addEventListener('click', () => {
            const form = button.closest('form');

            if (!form) {
                return;
            }

            form.reset();
            form.dataset.action = form.dataset.storeUrl;
            form.querySelector('[name="_method"]').value = 'POST';

            form.querySelectorAll('.switch[data-input]').forEach((switchElement) => {
                switchElement.classList.remove('on');
                const input = document.getElementById(switchElement.dataset.input);

                if (input) {
                    input.value = '0';
                }
            });
        });
    });
});
</script>
@endpush
