<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingOptions;
use App\Models\AccountingOption;
use App\Models\Feed\FeedBusinessArea;
use App\Models\Feed\FeedBusinessTrackingUnit;
use App\Models\Transaction;
use App\Support\CompanyContext;
use App\Support\SaleSellingTypes;
use App\Support\TransactionTypes;
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
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($companyId): void {
                    if (! SaleSellingTypes::isSaleCategory($this->input('category'))) {
                        return;
                    }

                    $sellingType = SaleSellingTypes::normalize($value);
                    if ($sellingType === null || SaleSellingTypes::isOthers($sellingType)) {
                        return;
                    }

                    $hasActiveBusinessAreas = FeedBusinessArea::query()
                        ->where('company_id', $companyId)
                        ->where('is_active', true)
                        ->exists();

                    $exists = FeedBusinessArea::query()
                        ->where('company_id', $companyId)
                        ->where('code', $sellingType)
                        ->where('is_active', true)
                        ->exists();

                    $isBuiltInFeed = $sellingType === SaleSellingTypes::FEED;
                    $isBuiltInFallback = ! $hasActiveBusinessAreas
                        && array_key_exists($sellingType, SaleSellingTypes::labels())
                        && ! SaleSellingTypes::isOthers($sellingType);

                    if (! $exists && ! $isBuiltInFeed && ! $isBuiltInFallback) {
                        $fail('The selected what are you selling is not an active business area.');
                    }
                },
            ],
            'tracking_unit_id' => [
                Rule::requiredIf(fn (): bool => SaleSellingTypes::isSaleCategory($this->input('category'))
                    && SaleSellingTypes::requiresWarehouse($this->input('selling_type'))),
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($companyId): void {
                    $sellingType = SaleSellingTypes::normalize($this->input('selling_type'));
                    if (! SaleSellingTypes::isSaleCategory($this->input('category')) || SaleSellingTypes::isOthers($sellingType) || ! filled($value)) {
                        return;
                    }

                    $exists = FeedBusinessTrackingUnit::query()
                        ->where('company_id', $companyId)
                        ->where('business_area', $sellingType)
                        ->whereKey((int) $value)
                        ->where(function ($query): void {
                            $query->where('is_active', true);

                            $transaction = $this->route('transaction');
                            if ($transaction instanceof Transaction && $transaction->tracking_unit_id) {
                                $query->orWhere('id', $transaction->tracking_unit_id);
                            }
                        })
                        ->exists();

                    if (! $exists) {
                        $fail('The selected location is not active for this business area.');
                    }
                },
            ],
            'settlement_type' => ['nullable', $this->activeAccountingOption(AccountingOption::GROUP_SETTLEMENT_TYPE)],
            'transaction_date' => ['required', 'date'],
            'transaction_head_id' => [
                Rule::requiredIf(fn (): bool => ! $this->isTransferCategory()),
                'nullable', 'integer',
                Rule::exists('transaction_heads', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->whereRaw('LOWER(category) = ?', [strtolower($category)])
                    ->where('is_active', true)
                    ->where('code', 'not like', 'SYS-FEED-%')
                    ->whereNotNull('posting_account_id')),
            ],
            'money_account_id' => [
                Rule::requiredIf(fn (): bool => $this->isTransferCategory()),
                'nullable', 'integer',
                Rule::exists('money_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNotNull('chart_of_account_id')),
            ],
            'transfer_to_money_account_id' => [
                Rule::requiredIf(fn (): bool => $this->isTransferCategory()),
                'nullable', 'integer', 'different:money_account_id',
                Rule::exists('money_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNotNull('chart_of_account_id')),
            ],
            'party_id' => [
                Rule::requiredIf(fn (): bool => SaleSellingTypes::isSaleCategory($this->input('category'))),
                'nullable', 'integer',
                Rule::exists('parties', 'id')->where(function ($query) use ($companyId, $category): void {
                    $query->where('company_id', $companyId)
                        ->where('is_active', true);

                    if (SaleSellingTypes::isSaleCategory($category)) {
                        $query->where('type', 'Customer');
                    }
                }),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'lte:amount', 'decimal:0,'.CompanyContext::decimalPlaces()],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'transaction_attachments' => ['nullable', 'array', 'max:5'],
            'transaction_attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt'],
        ];
    }

    private function isTransferCategory(): bool
    {
        $category = (string) $this->input('category');
        if ($category === '') {
            return false;
        }

        $option = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->where('value', $category)
            ->first();

        $metadata = is_array($option?->metadata) ? $option->metadata : [];

        return TransactionTypes::flow($category, $metadata) === TransactionTypes::FLOW_TRANSFER;
    }

    private function categoryIsTransfer(?string $category): bool
    {
        if (! filled($category)) {
            return false;
        }

        $option = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->where('value', $category)
            ->first();

        $metadata = is_array($option?->metadata) ? $option->metadata : [];

        return TransactionTypes::flow((string) $category, $metadata) === TransactionTypes::FLOW_TRANSFER;
    }

    public function attributes(): array
    {
        return [
            'selling_type' => 'what are you selling',
            'tracking_unit_id' => 'location / godown',
            'transaction_head_id' => 'transaction head',
            'money_account_id' => 'pay from account',
            'transfer_to_money_account_id' => 'pay to account',
        ];
    }

    protected function prepareForValidation(): void
    {
        $category = $this->canonicalActiveAccountingOption(
            AccountingOption::GROUP_TRANSACTION_CATEGORY,
            $this->input('category'),
        );
        $sellingType = SaleSellingTypes::normalize($this->input('selling_type'));
        $locationRequired = SaleSellingTypes::isSaleCategory($category)
            && SaleSellingTypes::requiresWarehouse($sellingType);

        $payload = [
            'category' => $category,
            'selling_type' => SaleSellingTypes::isSaleCategory($category) ? $sellingType : null,
            'tracking_unit_id' => $locationRequired && filled($this->input('tracking_unit_id'))
                ? $this->input('tracking_unit_id')
                : null,
            'settlement_type' => filled($this->input('settlement_type'))
                ? strtoupper(trim((string) $this->input('settlement_type')))
                : null,
            'reference' => filled($this->input('reference')) ? trim((string) $this->input('reference')) : null,
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
        ];

        if ($this->categoryIsTransfer($category)) {
            $payload['transaction_head_id'] = null;
            $payload['party_id'] = null;
            $payload['selling_type'] = null;
            $payload['tracking_unit_id'] = null;
            $payload['settlement_type'] = TransactionTypes::CASH;
            $payload['paid_amount'] = $this->input('amount');
        }

        $this->merge($payload);
    }
}
