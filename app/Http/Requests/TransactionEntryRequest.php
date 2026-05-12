<?php

namespace App\Http\Requests;

use App\Models\SettlementType;
use App\Models\TransactionHead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransactionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'voucher_type' => $this->voucher_type ?: 'Auto Select',
            'party_id' => $this->party_id ?: null,
            'cash_bank_account_id' => $this->cash_bank_account_id ?: null,
            'amount' => round((float) str_replace(',', '', (string) $this->amount), 2),
            'status' => $this->status ?: 'Posted',
            'reference' => $this->reference ?: null,
            'notes' => $this->notes ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'voucher_date' => ['required', 'date'],
            'voucher_type' => ['nullable', 'string', 'max:100'],

            'transaction_head_id' => [
                'required',
                'integer',
                Rule::exists('transaction_heads', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'settlement_type_id' => [
                'required',
                'integer',
                Rule::exists('settlement_types', 'id')
                    ->where(fn ($query) => $query->where('status', 'Active')),
            ],

            'party_id' => [
                'nullable',
                'integer',
                Rule::exists('parties', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'cash_bank_account_id' => [
                'nullable',
                'integer',
                Rule::exists('cash_bank_accounts', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'status' => [
                'required',
                Rule::in(['Draft', 'Posted']),
            ],

            'attachment' => ['nullable', 'file', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $head = TransactionHead::query()
                ->with('settlementTypes')
                ->find($this->integer('transaction_head_id'));

            $settlement = SettlementType::query()
                ->find($this->integer('settlement_type_id'));

            if (!$head || !$settlement) {
                return;
            }

            $allowedSettlementIds = $head->settlementTypes
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (!in_array((int) $settlement->id, $allowedSettlementIds, true)) {
                $validator->errors()->add(
                    'settlement_type_id',
                    'Selected Settlement Type is not allowed for this Transaction Head.'
                );
            }

            if ($head->requires_party && !$this->party_id) {
                $validator->errors()->add(
                    'party_id',
                    'Party / Person is required for this Transaction Head.'
                );
            }

            if ($head->requires_reference && !$this->reference) {
                $validator->errors()->add(
                    'reference',
                    'Reference is required for this Transaction Head.'
                );
            }

            $settlementCode = strtoupper((string) $settlement->code);
            $settlementName = strtolower((string) $settlement->name);

            $requiresCashBank = in_array($settlementCode, ['CASH', 'BANK'], true)
                || in_array($settlementName, ['cash', 'bank'], true);

            if ($requiresCashBank && !$this->cash_bank_account_id) {
                $validator->errors()->add(
                    'cash_bank_account_id',
                    'Paid From / Received In is required for Cash or Bank settlement.'
                );
            }
        }];
    }
}
