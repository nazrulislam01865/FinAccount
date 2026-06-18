<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->company_id !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:150'],
            'payload' => ['required', 'array'],
            'payload.fields' => ['required', 'array', 'max:300'],
            'payload.omitted_files' => ['sometimes', 'boolean'],
            'payload.omitted_sensitive' => ['sometimes', 'boolean'],
        ];
    }
}
