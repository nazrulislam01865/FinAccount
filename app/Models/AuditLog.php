<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'auditable_type',
        'auditable_id',
        'event',
        'module',
        'action',
        'old_values',
        'new_values',
        'user_id',
        'ip_address',
        'user_agent',
        'route_name',
        'request_method',
        'request_url',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'auditable_id' => 'integer',
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'auditable_type', 'auditable_id');
    }

    public function getActionLabelAttribute(): string
    {
        return Str::of((string) ($this->action ?: $this->event))
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }

    public function getModuleLabelAttribute(): string
    {
        return (string) ($this->module ?: class_basename((string) $this->auditable_type) ?: 'System');
    }

    public function getSubjectLabelAttribute(): string
    {
        $subject = class_basename((string) $this->auditable_type) ?: (string) $this->auditable_type;
        $id = (int) $this->auditable_id;

        return $id > 0 ? $subject . ' #' . $id : $subject;
    }

    /**
     * @return array<int, string>
     */
    public function changedFields(): array
    {
        $old = is_array($this->old_values) ? $this->old_values : [];
        $new = is_array($this->new_values) ? $this->new_values : [];

        return collect(array_unique(array_merge(array_keys($old), array_keys($new))))
            ->filter(fn ($field) => is_string($field) && $field !== '' && ! $this->isHiddenAuditField($field))
            ->values()
            ->all();
    }

    /**
     * Returns audit values as readable before/after rows for report display and export.
     *
     * @return array<int, array{field:string, old:string, new:string, status:string}>
     */
    public function humanChangeRows(): array
    {
        $old = is_array($this->old_values) ? $this->old_values : [];
        $new = is_array($this->new_values) ? $this->new_values : [];
        $fields = $this->changedFields();

        return collect($fields)
            ->map(function (string $field) use ($old, $new): array {
                $oldExists = array_key_exists($field, $old);
                $newExists = array_key_exists($field, $new);

                return [
                    'field' => $this->humanFieldName($field),
                    'old' => $this->humanValue($old[$field] ?? null),
                    'new' => $this->humanValue($new[$field] ?? null),
                    'status' => $this->changeStatus($oldExists, $newExists, $old[$field] ?? null, $new[$field] ?? null),
                ];
            })
            ->filter(fn (array $row) => $row['old'] !== $row['new'] || $row['status'] !== 'Unchanged')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{field:string, value:string}>
     */
    public function humanMetadataRows(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return collect($metadata)
            ->reject(fn ($value, $field) => ! is_string($field) || $field === '' || $this->isHiddenAuditField($field))
            ->map(fn ($value, string $field): array => [
                'field' => $this->humanFieldName($field),
                'value' => $this->humanValue($value, 300),
            ])
            ->filter(fn (array $row) => $row['value'] !== '—')
            ->values()
            ->all();
    }

    public function humanMetadataSummary(): string
    {
        $rows = $this->humanMetadataRows();

        if ($rows === []) {
            return '';
        }

        return Str::limit(
            collect($rows)
                ->map(fn (array $row) => $row['field'] . ': ' . $row['value'])
                ->implode(' | '),
            32000,
            '...'
        );
    }

    private function changeStatus(bool $oldExists, bool $newExists, mixed $oldValue, mixed $newValue): string
    {
        if (! $oldExists && $newExists) {
            return 'Added';
        }

        if ($oldExists && ! $newExists) {
            return 'Removed';
        }

        if ($this->humanValue($oldValue) === $this->humanValue($newValue)) {
            return 'Unchanged';
        }

        return 'Changed';
    }

    private function humanFieldName(string $field): string
    {
        return Str::of($field)
            ->replace(['.', '_', '-'], ' ')
            ->squish()
            ->headline()
            ->toString();
    }

    private function humanValue(mixed $value, int $limit = 700): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return Str::limit($value, $limit, '...');
        }

        if (is_array($value)) {
            return Str::limit($this->humanArrayValue($value), $limit, '...');
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return Str::limit((string) $value, $limit, '...');
        }

        if (is_object($value)) {
            return class_basename($value);
        }

        return ucfirst(gettype($value));
    }

    private function humanArrayValue(array $value): string
    {
        if ($value === []) {
            return '—';
        }

        if (array_is_list($value)) {
            return collect($value)
                ->map(fn ($item) => $this->humanNestedValue($item))
                ->filter(fn (string $item) => $item !== '—')
                ->implode('; ');
        }

        return collect($value)
            ->reject(fn ($item, $key) => ! is_string($key) || $key === '' || $this->isHiddenAuditField($key))
            ->map(fn ($item, string $key) => $this->humanFieldName($key) . ': ' . $this->humanNestedValue($item))
            ->filter(fn (string $item) => ! str_ends_with($item, ': —'))
            ->implode('; ');
    }

    private function humanNestedValue(mixed $value): string
    {
        if (is_array($value)) {
            return $this->humanArrayValue($value);
        }

        return $this->humanValue($value, 220);
    }

    private function isHiddenAuditField(string $field): bool
    {
        return in_array(strtolower($field), [
            'password',
            'password_confirmation',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ], true);
    }
}
