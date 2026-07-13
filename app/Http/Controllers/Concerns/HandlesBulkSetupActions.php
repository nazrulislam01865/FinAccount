<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Accounting\SafeDelete\DeletionPlan;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait HandlesBulkSetupActions
{
    /**
     * @template TModel of Model
     * @param class-string<TModel> $modelClass
     * @param array<int, string> $with
     * @return array{0:string,1:Collection<int,TModel>}
     */
    protected function resolveBulkCompanyRecords(
        Request $request,
        string $modelClass,
        string $entityLabel,
        array $with = [],
    ): array {
        $validated = $request->validate([
            'bulk_action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ], [
            'bulk_action.required' => 'Choose a bulk action first.',
            'bulk_action.in' => 'The selected bulk action is not supported.',
            'record_ids.required' => 'Select at least one '.$entityLabel.' record.',
            'record_ids.min' => 'Select at least one '.$entityLabel.' record.',
        ]);

        $ids = collect($validated['record_ids'])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $records = $modelClass::query()
            ->with($with)
            ->where('company_id', $request->user()->company_id)
            ->whereIn('id', $ids)
            ->get();

        if ($records->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'record_ids' => 'One or more selected '.$entityLabel.' records are invalid for this company.',
            ]);
        }

        return [(string) $validated['bulk_action'], $records];
    }

    protected function ensureBulkDeletePermission(Request $request): void
    {
        abort_unless(
            $request->user()?->canDeleteAccountingRecords(),
            403,
            'You are not allowed to permanently delete accounting records.',
        );
    }

    /**
     * @param Collection<int, Model> $records
     * @param Closure(Model): DeletionPlan $inspect
     */
    protected function buildBulkDeletionPlan(
        Collection $records,
        Closure $inspect,
        string $entityType,
        string $entityLabel,
        string $deleteEffect,
        string $confirmationText,
    ): DeletionPlan {
        $dependencies = [];

        foreach ($records as $record) {
            foreach ($inspect($record)->dependencies as $dependency) {
                $label = $dependency['label'];

                if (! isset($dependencies[$label])) {
                    $dependencies[$label] = [
                        'label' => $label,
                        'count' => 0,
                        'effect' => $dependency['effect'],
                    ];
                }

                $dependencies[$label]['count'] += (int) $dependency['count'];
            }
        }

        return new DeletionPlan(
            $entityType,
            number_format($records->count()).' selected '.$entityLabel.' '.($records->count() === 1 ? 'record' : 'records'),
            array_values(array_filter(
                $dependencies,
                fn (array $dependency): bool => (int) $dependency['count'] > 0,
            )),
            $deleteEffect,
            $confirmationText,
        );
    }
}
