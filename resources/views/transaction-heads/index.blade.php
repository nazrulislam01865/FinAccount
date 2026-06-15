@php
    $modalRecordId = (int) old('record_id', 0);
    $editingHead = $modalRecordId > 0 ? $transactionHeads->firstWhere('id', $modalRecordId) : null;
    $reopenModal = old('setup_modal') === 'transaction-head';
@endphp

<x-layouts::accounting title="Transaction Heads">
    <div class="hg-page-header">
        <div>
            <h1>Transaction Heads</h1>
            <p>Simple business activities shown to users. Each head is linked to an accounting rule and a posting COA.</p>
        </div>
        <button
            type="button"
            class="hg-btn hg-btn-primary"
            data-setup-open="create"
            data-setup-target="transaction-head-modal"
            data-defaults="{{ json_encode(['record_id' => '', 'category' => 'Sales', 'is_active' => '1']) }}"
        >+ Add Transaction Head</button>
    </div>

    @if ($transactionHeads->isEmpty())
        <div class="hg-empty">No records found.</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Head</th>
                    <th>Category</th>
                    <th>Linked Rule</th>
                    <th>Posting COA</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($transactionHeads as $head)
                    <tr>
                        <td><strong>{{ $head->code }}</strong><br>{{ $head->name }}</td>
                        <td><span class="hg-badge {{ strtolower($head->category) }}">{{ $head->category }}</span></td>
                        <td>{{ $head->accountingRule?->name }}</td>
                        <td>{{ $head->postingAccount?->code }} — {{ $head->postingAccount?->name }}</td>
                        <td><span class="hg-badge {{ $head->is_active ? 'on' : 'off' }}">{{ $head->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="hg-actions">
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small"
                                    data-setup-open="edit"
                                    data-setup-target="transaction-head-modal"
                                    data-edit-title="Edit Transaction Head"
                                    data-update-url="{{ route('transaction-heads.update', $head) }}"
                                    data-values="{{ json_encode([
                                        'record_id' => $head->id,
                                        'code' => $head->code,
                                        'name' => $head->name,
                                        'category' => $head->category,
                                        'accounting_rule_id' => $head->accounting_rule_id,
                                        'posting_account_id' => $head->posting_account_id,
                                        'is_active' => $head->is_active ? '1' : '0',
                                    ]) }}"
                                >Edit</button>
                                <form method="POST" action="{{ route('transaction-heads.destroy', $head) }}" onsubmit="return confirm('Delete this record?')">
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
        id="transaction-head-modal"
        :show="$reopenModal"
        :title="$editingHead ? 'Edit Transaction Head' : 'Add Transaction Head'"
        :store-url="route('transaction-heads.store')"
        create-title="Add Transaction Head"
    >
        <form method="POST" action="{{ $editingHead ? route('transaction-heads.update', $editingHead) : route('transaction-heads.store') }}" class="hg-form-grid" data-setup-form>
            @csrf
            <input type="hidden" name="_method" value="PUT" data-setup-method @disabled(! $editingHead)>
            <input type="hidden" name="setup_modal" value="transaction-head">
            <input type="hidden" name="record_id" value="{{ old('record_id') }}">

            <div class="hg-field">
                <label for="head-code">Code <span class="hg-required">*</span></label>
                <input id="head-code" name="code" value="{{ old('code', $editingHead?->code) }}" required>
                @error('code')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="head-name">Name <span class="hg-required">*</span></label>
                <input id="head-name" name="name" value="{{ old('name', $editingHead?->name) }}" required>
                @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="head-category">Category</label>
                <select id="head-category" name="category" required>
                    @foreach (['Sales', 'Payment', 'Liability'] as $category)
                        <option value="{{ $category }}" @selected(old('category', $editingHead?->category ?? 'Sales') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                @error('category')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field">
                <label for="head-rule">Accounting Rule <span class="hg-required">*</span></label>
                <select id="head-rule" name="accounting_rule_id" required>
                    <option value="">Select rule</option>
                    @foreach ($accountingRules as $rule)
                        <option value="{{ $rule->id }}" @selected((string) old('accounting_rule_id', $editingHead?->accounting_rule_id) === (string) $rule->id)>
                            {{ $rule->code }} — {{ $rule->name }} ({{ $rule->category }})
                        </option>
                    @endforeach
                </select>
                @error('accounting_rule_id')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field full">
                <label for="head-posting">Posting COA <span class="hg-required">*</span></label>
                <select id="head-posting" name="posting_account_id" required>
                    <option value="">Select COA</option>
                    @foreach ($postingAccounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('posting_account_id', $editingHead?->posting_account_id) === (string) $account->id)>
                            {{ $account->code }} — {{ $account->name }} ({{ $account->type }})
                        </option>
                    @endforeach
                </select>
                @error('posting_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
            </div>
            <div class="hg-field full">
                <input type="hidden" name="is_active" value="0">
                <label class="hg-checkbox-label" for="head-active">
                    <input id="head-active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingHead?->is_active ?? true))>
                    Active
                </label>
            </div>
            <div class="hg-field full"><button class="hg-btn hg-btn-primary" type="submit">Save</button></div>
        </form>
    </x-accounting.setup-modal>
</x-layouts::accounting>
