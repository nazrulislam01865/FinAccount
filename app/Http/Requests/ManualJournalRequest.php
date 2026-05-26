<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('transactions.journal.create');
    }

    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->map(function ($line) {
                return [
                    'ledger_id' => (int) ($line['ledger_id'] ?? 0),
                    'party_id' => ($line['party_id'] ?? null) ?: null,
                    'debit_amount' => $this->money($line['debit_amount'] ?? 0),
                    'credit_amount' => $this->money($line['credit_amount'] ?? 0),
                    'line_narration' => $this->blankToNull($line['line_narration'] ?? null),
                ];
            })
            ->filter(fn (array $line): bool => $line['ledger_id'] > 0 || $line['debit_amount'] > 0 || $line['credit_amount'] > 0)
            ->values()
            ->all();

        $this->merge([
            'journal_date' => $this->journal_date ?: now()->toDateString(),
            'reference' => $this->blankToNull($this->reference),
            'narration' => $this->blankToNull($this->narration),
            'status' => $this->status ?: 'Posted',
            'lines' => $lines,
        ]);
    }

    public function rules(): array
    {
        return [
            'journal_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:150'],
            'narration' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['Draft', 'Posted'])],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.ledger_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->where('posting_allowed', true)
                        ->where(function ($where) {
                            $where->where('coa_level', 4)
                                ->orWhere('account_level', 'Ledger');
                        })
                        ->whereNull('deleted_at')),
            ],
            'lines.*.party_id' => [
                'nullable',
                'integer',
                Rule::exists('parties', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],
            'lines.*.debit_amount' => ['required', 'numeric', 'min:0'],
            'lines.*.credit_amount' => ['required', 'numeric', 'min:0'],
            'lines.*.line_narration' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.min' => 'Manual Journal needs at least two debit/credit lines.',
            'lines.*.ledger_id.required' => 'Each journal line must select a Level 4 posting ledger.',
        ];
    }

    private function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace([',', 'BDT', '৳', ' '], '', $value);
        }

        return round((float) $value, 2);
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : ($value === null ? null : (string) $value);
    }
}
