<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingOptions;
use App\Models\AccountingOption;
use App\Models\Transaction;
use App\Support\CompanyContext;
use App\Support\SaleSellingTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
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
                Rule::requiredIf(fn (): bool => SaleSellingTypes::isSaleCategory($this->input('category'))),
                'nullable',
                Rule::in(array_keys(SaleSellingTypes::labels())),
            ],
            'warehouse_id' => [
                Rule::requiredIf(fn (): bool => SaleSellingTypes::isSaleCategory($this->input('category'))
                    && SaleSellingTypes::requiresWarehouse($this->input('selling_type'))),
                'nullable',
                'integer',
                Rule::exists('feed_warehouses', 'id')->where(function ($query) use ($companyId): void {
                    $query->where('company_id', $companyId)
                        ->where(function ($warehouseQuery): void {
                            $warehouseQuery->where('is_active', true);

                            $transaction = $this->route('transaction');
                            if ($transaction instanceof Transaction && $transaction->warehouse_id) {
                                $warehouseQuery->orWhere('id', $transaction->warehouse_id);
                            }
                        });
                }),
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
                'nullable', 'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'lte:amount', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'transaction_attachments' => ['nullable', 'array', 'max:5'],
            'transaction_attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt'],
        ];
    }

    public function attributes(): array
    {
        return [
            'selling_type' => 'what are you selling',
            'warehouse_id' => 'location / godown',
        ];
    }

    protected function prepareForValidation(): void
    {
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
            'warehouse_id' => $warehouseRequired && filled($this->input('warehouse_id'))
                ? $this->input('warehouse_id')
                : null,
            'settlement_type' => filled($this->input('settlement_type'))
                ? strtoupper(trim((string) $this->input('settlement_type')))
                : null,
            'reference' => filled($this->input('reference')) ? trim((string) $this->input('reference')) : null,
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
        ]);
    }
}
