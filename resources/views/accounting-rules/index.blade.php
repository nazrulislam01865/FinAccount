@php
    use App\Models\AccountingRule;

    $modalRecordId = (int) old('record_id', 0);
    $editingRule = $modalRecordId > 0 ? $rules->firstWhere('id', $modalRecordId) : null;
    $reopenModal = old('setup_modal') === 'accounting-rule';
    $sources = [
        AccountingRule::SOURCE_SELECTED_MONEY => 'Selected Money Account',
        AccountingRule::SOURCE_HEAD_ACCOUNT => 'Transaction Head COA',
        AccountingRule::SOURCE_PARTY_RECEIVABLE => 'Party Receivable COA',
        AccountingRule::SOURCE_PARTY_PAYABLE => 'Party Payable COA',
    ];
@endphp

<x-layouts::accounting title="Accounting Rules">
    <div class="hg-page-header">
        <div>
            <h1>Accounting Rules</h1>
            <p>Rules decide debit and credit sources. Keep this setup hidden from daily users and available only to admin/accounting setup users.</p>
        </div>
        <button
            type="button"
            class="hg-btn hg-btn-primary"
            data-setup-open="create"
            data-setup-target="accounting-rule-modal"
            data-defaults="{{ json_encode([
                'record_id' => '',
                'category' => 'Sales',
                'party_type' => 'Any',
                'debit_source' => AccountingRule::SOURCE_HEAD_ACCOUNT,
                'credit_source' => AccountingRule::SOURCE_SELECTED_MONEY,
                'money_required' => '0',
                'party_required' => '0',
                'is_active' => '1',
            ]) }}"
        >+ Add Rule</button>
    </div>

    @if ($rules->isEmpty())
        <div class="hg-empty">No records found.</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Rule</th>
                    <th>Category</th>
                    <th>Debit Source</th>
                    <th>Credit Source</th>
                    <th>Money?</th>
                    <th>Party?</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rules as $rule)
                    <tr>
                        <td><strong>{{ $rule->code }}</strong><br>{{ $rule->name }}</td>
                        <td><span class="hg-badge {{ strtolower($rule->category) }}">{{ $rule->category }}</span></td>
                        <td>{{ $rule->sourceLabel($rule->debit_source) }}</td>
                        <td>{{ $rule->sourceLabel($rule->credit_source) }}</td>
                        <td>{{ $rule->money_required ? 'Yes' : 'No' }}</td>
                        <td>{{ $rule->party_required ? $rule->party_type : 'No' }}</td>
                        <td><span class="hg-badge {{ $rule->is_active ? 'on' : 'off' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="hg-actions">
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small"
                                    data-setup-open="edit"
                                    data-setup-target="accounting-rule-modal"
                                    data-edit-title="Edit Accounting Rule"
                                    data-update-url="{{ route('accounting-rules.update', $rule) }}"
                                    data-values="{{ json_encode([
                                        'record_id' => $rule->id,
                                        'code' => $rule->code,
                                        'name' => $rule->name,
                                        'category' => $rule->category,
                                        'party_type' => $rule->party_type,
                                        'debit_source' => $rule->debit_source,
                                        'credit_source' => $rule->credit_source,
                                        'money_required' => $rule->money_required ? '1' : '0',
                                        'party_required' => $rule->party_required ? '1' : '0',
                                        'is_active' => $rule->is_active ? '1' : '0',
                                    ]) }}"
                                >Edit</button>
                                <form method="POST" action="{{ route('accounting-rules.destroy', $rule) }}" onsubmit="return confirm('Delete this record?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-accounting.setup-modal
        id="accounting-rule-modal"
        :show="$reopenModal"
        :title="$editingRule ? 'Edit Accounting Rule' : 'Add Accounting Rule'"
        :store-url="route('accounting-rules.store')"
        create-title="Add Accounting Rule"
    >
        <form method="POST" action="{{ $editingRule ? route('accounting-rules.update', $editingRule) : route('accounting-rules.store') }}" class="hg-form-grid" data-setup-form>
            @csrf
            <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingRule)>
            <input type="hidden" name="setup_modal" value="accounting-rule">
            <input type="hidden" name="record_id" value="{{ old('record_id') }}">

            <div class="hg-field">
                <label for="rule-code">Code <span class="hg-required">*</span></label>
                <input id="rule-code" name="code" value="{{ old('code', $editingRule?->code) }}" required>
                @error('code')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="rule-name">Name <span class="hg-required">*</span></label>
                <input id="rule-name" name="name" value="{{ old('name', $editingRule?->name) }}" required>
                @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="rule-category">Category</label>
                <select id="rule-category" name="category" required>
                    @foreach (['Sales', 'Payment', 'Liability'] as $category)
                        <option value="{{ $category }}" @selected(old('category', $editingRule?->category ?? 'Sales') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                @error('category')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="rule-party-type">Party Type</label>
                <select id="rule-party-type" name="party_type" required>
                    @foreach (['Any', 'Customer', 'Supplier', 'Worker', 'Owner', 'Lender'] as $partyType)
                        <option value="{{ $partyType }}" @selected(old('party_type', $editingRule?->party_type ?? 'Any') === $partyType)>{{ $partyType }}</option>
                    @endforeach
                </select>
                @error('party_type')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="rule-debit">Debit Source</label>
                <select id="rule-debit" name="debit_source" required>
                    @foreach ($sources as $source => $label)
                        <option value="{{ $source }}" @selected(old('debit_source', $editingRule?->debit_source ?? AccountingRule::SOURCE_HEAD_ACCOUNT) === $source)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('debit_source')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="rule-credit">Credit Source</label>
                <select id="rule-credit" name="credit_source" required>
                    @foreach ($sources as $source => $label)
                        <option value="{{ $source }}" @selected(old('credit_source', $editingRule?->credit_source ?? AccountingRule::SOURCE_SELECTED_MONEY) === $source)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('credit_source')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <input type="hidden" name="money_required" value="0">
                <label class="hg-checkbox-label" for="rule-money-required">
                    <input id="rule-money-required" type="checkbox" name="money_required" value="1" @checked(old('money_required', $editingRule?->money_required ?? false))>
                    Money account required
                </label>
            </div>
            <div class="hg-field">
                <input type="hidden" name="party_required" value="0">
                <label class="hg-checkbox-label" for="rule-party-required">
                    <input id="rule-party-required" type="checkbox" name="party_required" value="1" @checked(old('party_required', $editingRule?->party_required ?? false))>
                    Party required
                </label>
            </div>
            <div class="hg-field full">
                <input type="hidden" name="is_active" value="0">
                <label class="hg-checkbox-label" for="rule-active">
                    <input id="rule-active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingRule?->is_active ?? true))>
                    Active
                </label>
            </div>
            <div class="hg-field full"><button class="hg-btn hg-btn-primary" type="submit">Save</button></div>
        </form>
    </x-accounting.setup-modal>
</x-layouts::accounting>
