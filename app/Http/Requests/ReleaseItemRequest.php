<?php

namespace App\Http\Requests;

use App\Models\ReleaseItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReleaseItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('release-notes.manage');
    }

    public function rules(): array
    {
        return [
            'release_date' => ['required', 'date'],
            'module' => ['required', 'string', 'max:100', Rule::in(ReleaseItem::MODULES)],
            'ui_function' => ['required', 'string', 'max:40', Rule::in(ReleaseItem::UI_FUNCTIONS)],
            'item_type' => ['required', 'string', 'max:40', Rule::in(ReleaseItem::ITEM_TYPES)],
            'task' => ['required', 'string', 'max:180'],
            'note' => ['nullable', 'string', 'max:2000'],
            'user_impact' => ['nullable', 'string', 'max:2000'],
            'released_by' => ['nullable', 'string', 'max:120'],
            'release_version' => ['required', 'string', 'max:40', Rule::in(ReleaseItem::RELEASE_VERSIONS)],
            'status' => ['required', 'string', 'max:40', Rule::in(ReleaseItem::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'module.in' => 'Select a valid release module.',
            'ui_function.in' => 'Select UI, Function, or UI + Function.',
            'item_type.in' => 'Select a valid release item type.',
            'release_version.in' => 'Select Major, Minor, or Hotfix.',
            'status.in' => 'Select Draft, In Review, or Released.',
        ];
    }
}
