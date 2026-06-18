<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimeZoneRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->canAccounting('time_zones.manage') ?? false; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('time_zones')->where('company_id', $this->user()?->company_id)],
            'name' => ['required', 'string', 'max:120'],
            'utc_offset' => ['required', 'string', 'max:20', 'regex:/^UTC[+-][0-9]{2}:[0-9]{2}$/'],
            'php_timezone' => ['required', 'string', 'max:100', Rule::in(timezone_identifiers_list()), Rule::unique('time_zones')->where('company_id', $this->user()?->company_id)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'utc_offset' => strtoupper(trim((string) $this->input('utc_offset'))),
            'php_timezone' => trim((string) $this->input('php_timezone')),
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
