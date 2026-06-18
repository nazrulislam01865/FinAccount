<?php

namespace App\Support;

use App\Models\FormDraft;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class VisibleFormDrafts
{
    /**
     * @return Collection<int, FormDraft>
     */
    public static function forBase(string $base, ?User $user = null): Collection
    {
        $user ??= auth()->user();

        if (! $user || ! $user->company_id) {
            return collect();
        }

        return FormDraft::query()
            ->ownedBy($user)
            ->where(function ($query) use ($base): void {
                $query->where('draft_key', $base.'.create')
                    ->orWhere('draft_key', 'like', $base.'.create.%')
                    ->orWhere('draft_key', 'like', $base.'.edit.%');
            })
            ->latest('updated_at')
            ->get();
    }

    /**
     * @return Collection<int, FormDraft>
     */
    public static function forBases(array $bases, ?User $user = null): Collection
    {
        return collect($bases)
            ->flatMap(fn (string $base): Collection => self::forBase($base, $user))
            ->unique('id')
            ->sortByDesc('updated_at')
            ->values();
    }

    /** @return array<string, mixed> */
    public static function fields(FormDraft $draft): array
    {
        return (array) data_get($draft->payload, 'fields', []);
    }

    public static function field(FormDraft $draft, string $name, mixed $default = null): mixed
    {
        $value = data_get(self::fields($draft), $name, $default);

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value;
    }

    public static function boolField(FormDraft $draft, string $name, bool $default = false): bool
    {
        $value = data_get(self::fields($draft), $name, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function label(FormDraft $draft, string $name, string $fallback = 'Draft'): string
    {
        $value = self::field($draft, $name, '');
        $value = is_array($value) ? implode(', ', array_filter($value)) : trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }

    public static function mode(FormDraft $draft): string
    {
        return str_contains($draft->draft_key, '.edit.') ? 'edit' : 'create';
    }

    public static function isEdit(FormDraft $draft): bool
    {
        return self::mode($draft) === 'edit';
    }

    public static function recordId(FormDraft $draft): ?int
    {
        if (preg_match('/\.edit\.(\d+)$/', $draft->draft_key, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public static function transactionCategory(FormDraft $draft): string
    {
        $category = (string) self::field($draft, 'category', '');
        if ($category !== '') {
            return $category;
        }

        if (preg_match('/^transactions\.create\.(.+)$/', $draft->draft_key, $matches)) {
            return Str::of($matches[1])->replace('_', ' ')->title()->toString();
        }

        return '';
    }

    /** @return array<string, mixed> */
    public static function values(FormDraft $draft, array $defaults = []): array
    {
        return array_merge($defaults, self::fields($draft));
    }
}
