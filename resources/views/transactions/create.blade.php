@php
    $transaction = $transaction ?? null;
    $isEditing = $transaction !== null;
    $formAction = $isEditing
        ? route('transactions.update', $transaction)
        : route('transactions.store');
    $categoryRepairRequired = $categoryRepairRequired ?? false;
@endphp

<x-layouts::accounting title="Transaction Entry">
    <div class="hg-page-header">
        <div>
            <h1>Record {{ $categoryOption?->label ?? $category }} Transaction</h1>
        </div>
    </div>

    @if (! $isEditing || $categoryRepairRequired)
        <div class="hg-tabs">
            @foreach ($transactionCategories as $categoryTab)
                <a
                    href="{{ $isEditing
                        ? route('transactions.edit', [$transaction, 'category' => $categoryTab->value])
                        : route('transactions.create', ['category' => $categoryTab->value]) }}"
                    class="{{ $category === $categoryTab->value ? 'active' : '' }}"
                >{{ $categoryTab->label }}</a>
            @endforeach
        </div>
    @endif

    @if ($isEditing && $transaction->status === 'incomplete')
        <div class="hg-notice" style="margin-bottom:14px">
            <strong>This transaction is incomplete.</strong>
            A related setup record was deleted. Select valid active dependencies and update the transaction to rebuild its journal and return it to Posted status.
        </div>
    @endif

    <div class="hg-grid hg-grid-2 hg-entry-grid" data-transaction-entry>
        <section class="hg-card">
            <form method="POST" action="{{ $formAction }}" class="hg-form-grid" data-transaction-form>
                @csrf
                @if ($isEditing)
                    @method('PUT')
                @else
                    <input type="hidden" name="request_token" value="{{ $requestToken }}">
                @endif

                <input type="hidden" name="category" value="{{ $category }}">

                <div class="hg-field">
                    <label for="transaction_date">Date <span class="hg-required">*</span></label>
                    <input
                        id="transaction_date"
                        name="transaction_date"
                        type="date"
                        value="{{ old('transaction_date', $isEditing ? $transaction->transaction_date->format('Y-m-d') : now()->toDateString()) }}"
                        required
                    >
                    @error('transaction_date')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="transaction_head_id">Transaction Head <span class="hg-required">*</span></label>
                    <select id="transaction_head_id" name="transaction_head_id" required>
                        <option value="">
                            {{ $transactionHeads->isEmpty()
                                ? 'No active '.($categoryOption?->label ?? $category).' transaction head available'
                                : 'Select transaction head' }}
                        </option>
                        @foreach ($transactionHeads as $head)
                            <option
                                value="{{ $head->id }}"
                                @selected((string) old('transaction_head_id', $isEditing ? $transaction->transaction_head_id : '') === (string) $head->id)
                            >
                                {{ $head->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('transaction_head_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field" id="money-field">
                    <label for="money_account_id">
                        <span id="money-label">{{ $categoryOption?->metadata['money_label'] ?? 'Money Account' }}</span>
                        <span class="hg-required">*</span>
                    </label>
                    <select id="money_account_id" name="money_account_id">
                        <option value="">
                            {{ $moneyAccounts->isEmpty() ? 'No active money account available' : 'Select money account' }}
                        </option>
                        @foreach ($moneyAccounts as $moneyAccount)
                            <option
                                value="{{ $moneyAccount->id }}"
                                @selected((string) old('money_account_id', $isEditing ? $transaction->money_account_id : '') === (string) $moneyAccount->id)
                            >
                                {{ $moneyAccount->name }} — {{ $moneyKindLabels[$moneyAccount->kind] ?? $moneyAccount->kind }}
                            </option>
                        @endforeach
                    </select>
                    @error('money_account_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field" id="party-field">
                    <label for="party_id">Party <span class="hg-required">*</span></label>
                    <select id="party_id" name="party_id">
                        <option value="">Select party</option>
                        @foreach ($parties as $party)
                            <option
                                value="{{ $party->id }}"
                                data-party-type="{{ $party->type }}"
                                @selected((string) old('party_id', $isEditing ? $transaction->party_id : '') === (string) $party->id)
                            >
                                {{ $party->code }} — {{ $party->name }} ({{ $partyTypeLabels[$party->type] ?? $party->type }})
                            </option>
                        @endforeach
                    </select>
                    @error('party_id')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="amount">Amount (BDT) <span class="hg-required">*</span></label>
                    <input
                        id="amount"
                        name="amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        value="{{ old('amount', $isEditing ? $transaction->amount : '') }}"
                        required
                    >
                    @error('amount')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field">
                    <label for="reference">Reference</label>
                    <input
                        id="reference"
                        name="reference"
                        value="{{ old('reference', $isEditing ? $transaction->reference : '') }}"
                        placeholder="Invoice, bill, receipt or loan ref"
                    >
                    @error('reference')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Short business description">{{ old('description', $isEditing ? $transaction->description : '') }}</textarea>
                    @error('description')<small class="hg-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="hg-field full">
                    <div class="hg-actions">
                        <a class="hg-btn" href="{{ route('transactions.create', ['category' => $category]) }}">Clear</a>
                        <button class="hg-btn hg-btn-primary" type="submit" data-submit-button>
                            {{ $isEditing ? 'Update Transaction' : 'Post Transaction' }}
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <section class="hg-card">
            <h2 class="hg-card-title">Automatic Journal Preview</h2>
            <div id="journal-preview" data-preview-url="{{ route('transactions.preview') }}">
                @include('transactions.partials.preview-empty')
            </div>
        </section>
    </div>

    <template id="journal-preview-empty-template">
        @include('transactions.partials.preview-empty')
    </template>
</x-layouts::accounting>
