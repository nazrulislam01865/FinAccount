<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingOptions;
use App\Models\AccountingOption;
use App\Models\Feed\FeedBusinessArea;
use App\Support\CompanyContext;
use App\Support\SaleSellingTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    use ValidatesAccountingOptions;

    public function authorize(): bool
    {
        return $this->user()?->canAccounting('transactions.manage') ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $category = (string) $this->input('category');
        return [
            'category' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_TRANSACTION_CATEGORY)],
            'selling_type' => [
                Rule::requiredIf(fn (): bool => $this->isFeedSaleSelected()),
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($companyId): void {
                    if (! SaleSellingTypes::isSaleCategory($this->input('category'))) {
                        return;
                    }

                    $sellingType = SaleSellingTypes::normalize($value);
                    if ($sellingType === null || SaleSellingTypes::isOthers($sellingType)) {
                        return;
                    }

                    $exists = FeedBusinessArea::query()
                        ->where('company_id', $companyId)
                        ->where('code', $sellingType)
                        ->where('is_active', true)
                        ->exists();

                    if (! $exists) {
                        $fail('The selected what are you selling is not an active business area.');
                    }
                },
            ],
            'tracking_unit_id' => [
                Rule::requiredIf(fn (): bool => $this->isFeedSaleSelected()),
                'nullable',
                'integer',
                Rule::exists('feed_warehouses', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'settlement_type' => ['nullable', $this->activeAccountingOption(AccountingOption::GROUP_SETTLEMENT_TYPE)],
            'transaction_date' => ['required', 'date'],
            'transaction_head_id' => [
                'required', 'integer',
                Rule::exists('transaction_heads', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->whereRaw('LOWER(category) = ?', [strtolower($category)])
                    ->where('is_active', true)
                    ->where('code', 'not like', 'SYS-FEED-%')
                    ->whereNotNull('posting_account_id')),
            ],
            'money_account_id' => [
                'nullable', 'integer',
                Rule::exists('money_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNotNull('chart_of_account_id')),
            ],
            'party_id' => [
                Rule::requiredIf(fn (): bool => $this->isFeedSaleSelected()),
                'nullable', 'integer',
                Rule::exists('parties', 'id')->where(function ($query) use ($companyId, $category): void {
                    $query->where('company_id', $companyId)
                        ->where('is_active', true);

                    if (SaleSellingTypes::isSaleCategory($category)) {
                        $query->where('type', 'Customer');
                    }
                }),
            ],
            'due_settlement' => ['nullable', 'boolean'],
            'due_type' => ['nullable', 'required_if:due_settlement,1', Rule::in(['Receivable', 'Payable'])],
            'due_party_id' => [
                'nullable', 'required_if:due_settlement,1', 'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'due_account_id' => [
                'nullable', 'required_if:due_settlement,1', 'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('level', 3)
                    ->where('is_active', true)),
            ],
            'due_as_of_date' => ['nullable', 'required_if:due_settlement,1', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'lte:amount', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'other_charges' => [Rule::requiredIf(fn (): bool => $this->isFeedSaleSelected()), 'nullable', 'numeric', 'min:0', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'lines' => [Rule::requiredIf(fn (): bool => $this->isFeedSaleSelected()), 'nullable', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => [
                'required_with:lines', 'integer',
                Rule::exists('feed_items', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'lines.*.unit' => ['required_with:lines', Rule::in(['BAG', 'KG'])],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0', 'decimal:0,4'],
            'lines.*.rate' => ['required_with:lines', 'numeric', 'min:0', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'transaction_attachments' => ['nullable', 'array', 'max:5'],
            'transaction_attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt'],
            'request_token' => ['required', 'uuid'],
        ];
    }


    private function isFeedSaleSelected(): bool
    {
        $sellingType = SaleSellingTypes::normalize($this->input('selling_type'));

        return SaleSellingTypes::isSaleCategory($this->input('category'))
            && filled($sellingType)
            && ! SaleSellingTypes::isOthers($sellingType);
    }

    public function attributes(): array
    {
        return [
            'selling_type' => 'what are you selling',
            'tracking_unit_id' => 'location / godown',
        ];
    }

    protected function prepareForValidation(): void
    {
        $dueType = strtolower(trim((string) $this->input('due_type')));
        $category = $this->canonicalActiveAccountingOption(
            AccountingOption::GROUP_TRANSACTION_CATEGORY,
            $this->input('category'),
        );
        $sellingType = SaleSellingTypes::normalize($this->input('selling_type'));
        $warehouseRequired = SaleSellingTypes::isSaleCategory($category)
            && SaleSellingTypes::requiresWarehouse($sellingType);

        $this->merge([
            'category' => $category,
            'selling_type' => SaleSellingTypes::isSaleCategory($category) ? $sellingType : null,
            'tracking_unit_id' => $warehouseRequired && filled($this->input('tracking_unit_id'))
                ? $this->input('tracking_unit_id')
                : null,
            'settlement_type' => filled($this->input('settlement_type'))
                ? strtoupper(trim((string) $this->input('settlement_type')))
                : null,
            'due_settlement' => $this->boolean('due_settlement'),
            'due_type' => match ($dueType) {
                'receivable' => 'Receivable',
                'payable' => 'Payable',
                default => filled($this->input('due_type')) ? trim((string) $this->input('due_type')) : null,
            },
            'other_charges' => $this->input('other_charges', 0),
            'reference' => filled($this->input('reference')) ? trim((string) $this->input('reference')) : null,
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
        ]);
    }
}
