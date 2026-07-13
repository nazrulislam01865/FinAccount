<?php

namespace App\Http\Requests\Feed;

use App\Support\CompanyContext;
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
            'party_id' => [
                'required', 'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('type', 'Customer')
                    ->where('is_active', true)),
            ],
            'warehouse_id' => [
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
            'delivery_charge' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
            'overall_discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
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
            'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
            'transaction_attachments' => ['nullable', 'array', 'max:5'],
            'transaction_attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => $this->filled('reference') ? trim((string) $this->input('reference')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'delivery_charge' => $this->input('delivery_charge', 0),
            'overall_discount' => $this->input('overall_discount', 0),
        ]);
    }
}
