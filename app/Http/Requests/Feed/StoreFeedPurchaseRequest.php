<?php

namespace App\Http\Requests\Feed;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'external_invoice_no' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'transport_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
            'other_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.$decimalPlaces],
            'cost_allocation' => ['required', Rule::in(['quantity', 'value'])],
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
            'lines.*.batch_no' => ['nullable', 'string', 'max:100'],
            'lines.*.expiry_date' => ['nullable', 'date'],
            'transaction_attachments' => ['nullable', 'array', 'max:5'],
            'transaction_attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'external_invoice_no' => $this->filled('external_invoice_no') ? trim((string) $this->input('external_invoice_no')) : null,
            'reference' => $this->filled('reference') ? trim((string) $this->input('reference')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'transport_cost' => $this->input('transport_cost', 0),
            'other_cost' => $this->input('other_cost', 0),
        ]);
    }
}
