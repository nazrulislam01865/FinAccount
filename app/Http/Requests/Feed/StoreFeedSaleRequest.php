<?php

namespace App\Http\Requests\Feed;

use App\Support\CompanyContext;
use App\Support\TransactionTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedSaleRequest extends FormRequest
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
                    ->whereRaw('LOWER(category) = ?', [strtolower(TransactionTypes::SALE)])
                    ->where('is_active', true)),
            ],
            'party_id' => [
                'required', 'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('type', 'Customer')
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
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'overall_discount' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'transport_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
            'other_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
            'paid_amount' => ['required', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
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
            'transaction_attachments' => ['nullable', 'array', 'max:5'],
            'transaction_attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => $this->filled('reference') ? trim((string) $this->input('reference')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'overall_discount' => $this->sanitizePercentageInput($this->input('overall_discount', 0)),
            'transport_cost' => $this->input('transport_cost', 0),
            'other_cost' => $this->input('other_cost', 0),
        ]);
    }

    private function sanitizePercentageInput(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return trim(str_replace(['%', ','], '', (string) $value));
    }
}
