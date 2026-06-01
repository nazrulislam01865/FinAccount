<?php

namespace App\Observers;

use App\AccountingEngine\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $old = [];
        foreach (array_keys($changes) as $key) {
            $old[$key] = method_exists($model, 'getRawOriginal')
                ? $model->getRawOriginal($key)
                : $model->getOriginal($key);
        }

        $this->record($model, 'updated', $old, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getOriginal(), null);
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored', null, $model->getAttributes());
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    private function record(Model $model, string $event, ?array $oldValues, ?array $newValues): void
    {
        if (! class_exists(AuditTrailService::class)) {
            return;
        }

        app(AuditTrailService::class)->record(
            $model,
            (int) $model->getKey(),
            $event,
            $oldValues,
            $newValues,
            auth()->id()
        );
    }
}
