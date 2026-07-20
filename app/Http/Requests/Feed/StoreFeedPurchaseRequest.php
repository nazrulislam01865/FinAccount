<?php

namespace App\Http\Requests\Feed;

use App\Support\CompanyContext;
use App\Support\TransactionTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFeedPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccounting('transactions.manage') ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $decimalPlaces = CompanyContext::decimalPlaces();

        return [
            'transaction_date' => ['required', 'date'],
            'transaction_head_id' => [
                'required', 'integer',
                Rule::exists('transaction_heads', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->whereRaw('LOWER(category) = ?', [strtolower(TransactionTypes::PURCHASE)])
                    ->where('is_active', true)),
            ],
            'party_id' => [
                'required', 'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('type', 'Supplier')
                    ->where('is_active', true)),
            ],
            'tracking_unit_id' => [
                'required', 'integer',
                Rule::exists('feed_warehouses', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'money_account_id' => [
                'nullable', 'integer',
                Rule::exists('money_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'overall_discount' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'transport_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
            'other_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
            'cost_allocation' => ['nullable', Rule::in(['value', 'quantity'])],
            'paid_amount' => ['required', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
            'payments' => ['nullable', 'array', 'max:10'],
            'payments.*.money_account_id' => [
                'nullable', 'integer',
                Rule::exists('money_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'payments.*.amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,'.$decimalPlaces],
            'request_token' => ['required', 'uuid'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => [
                'required', 'integer',
                Rule::exists('feed_items', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'lines.*.unit' => ['required', Rule::in(['BAG', 'KG'])],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'lines.*.rate' => ['required', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
            'lines.*.batch_no' => ['nullable', 'string', 'max:100'],
            'lines.*.expiry_date' => ['nullable', 'date'],
            'transaction_attachments' => ['nullable', 'array', 'max:5'],
            'transaction_attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payments = $this->normalizedPayments();

        $this->merge([
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'overall_discount' => $this->sanitizePercentageInput($this->input('overall_discount', 0)),
            'transport_cost' => $this->input('transport_cost', 0),
            'other_cost' => $this->input('other_cost', 0),
            'cost_allocation' => $this->input('cost_allocation', 'value'),
            'payments' => $payments,
            'paid_amount' => $this->paymentTotal($payments),
            'money_account_id' => $payments[0]['money_account_id'] ?? null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $accountIds = [];

            foreach ((array) $this->input('payments', []) as $index => $payment) {
                if (! is_array($payment)) {
                    continue;
                }

                $accountId = $payment['money_account_id'] ?? null;
                $amount = $payment['amount'] ?? null;
                $reference = $payment['reference'] ?? null;

                if ((filled($amount) || filled($reference)) && blank($accountId)) {
                    $validator->errors()->add("payments.$index.money_account_id", 'Select the account used for this payment.');
                }

                if ((filled($accountId) || filled($reference)) && blank($amount)) {
                    $validator->errors()->add("payments.$index.amount", 'Enter the amount paid through this account.');
                }

                if (filled($accountId)) {
                    $accountIds[] = (int) $accountId;
                }
            }

            if (count($accountIds) !== count(array_unique($accountIds))) {
                $validator->errors()->add('payments', 'Use each payment account only once. Combine amounts paid through the same account.');
            }
        });
    }

    /** @return array<int, array{money_account_id:mixed,reference:mixed,amount:mixed}> */
    private function normalizedPayments(): array
    {
        $payments = $this->input('payments');

        if (! is_array($payments)) {
            $legacyAmount = $this->input('paid_amount');
            $legacyAccount = $this->input('money_account_id');
            $payments = filled($legacyAccount) || (is_numeric($legacyAmount) && (float) $legacyAmount > 0)
                ? [['money_account_id' => $legacyAccount, 'reference' => null, 'amount' => $legacyAmount]]
                : [];
        }

        return collect($payments)
            ->map(fn ($payment): array => [
                'money_account_id' => is_array($payment) ? ($payment['money_account_id'] ?? null) : null,
                'reference' => is_array($payment) && filled($payment['reference'] ?? null) ? trim((string) $payment['reference']) : null,
                'amount' => is_array($payment) ? ($payment['amount'] ?? null) : null,
            ])
            ->filter(fn (array $payment): bool => filled($payment['money_account_id']) || filled($payment['reference']) || filled($payment['amount']))
            ->values()
            ->all();
    }

    /** @param array<int, array{money_account_id:mixed,reference:mixed,amount:mixed}> $payments */
    private function paymentTotal(array $payments): string
    {
        $total = collect($payments)->sum(fn (array $payment): float => is_numeric($payment['amount'])
            ? (float) $payment['amount']
            : 0.0);

        return number_format(round($total, CompanyContext::decimalPlaces()), CompanyContext::decimalPlaces(), '.', '');
    }

    private function sanitizePercentageInput(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return trim(str_replace(['%', ','], '', (string) $value));
    }
}
