<?php

namespace App\Services\Accounting;

use App\Models\FormDraft;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FormDraftService
{
    private const MAX_SERIALIZED_BYTES = 200000;

    public function find(User $user, string $draftKey): ?FormDraft
    {
        return FormDraft::query()
            ->ownedBy($user)
            ->where('draft_key', $this->normalizeKey($draftKey))
            ->first();
    }

    public function save(User $user, string $draftKey, array $payload, ?string $title, ?string $routeName): FormDraft
    {
        $normalizedPayload = [
            'fields' => $this->sanitizeFields((array) Arr::get($payload, 'fields', [])),
            'omitted_files' => (bool) Arr::get($payload, 'omitted_files', false),
            'omitted_sensitive' => (bool) Arr::get($payload, 'omitted_sensitive', false),
        ];

        $encoded = json_encode($normalizedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > self::MAX_SERIALIZED_BYTES) {
            throw ValidationException::withMessages([
                'payload' => 'This form contains too much draft data. Remove unusually long text and try again.',
            ]);
        }

        return FormDraft::query()->updateOrCreate(
            [
                'company_id' => (int) $user->company_id,
                'user_id' => (int) $user->id,
                'draft_key' => $this->normalizeKey($draftKey),
            ],
            [
                'title' => filled($title) ? trim((string) $title) : null,
                'route_name' => filled($routeName) ? trim((string) $routeName) : null,
                'payload' => $normalizedPayload,
            ],
        );
    }

    public function delete(User $user, string $draftKey): bool
    {
        return FormDraft::query()
            ->ownedBy($user)
            ->where('draft_key', $this->normalizeKey($draftKey))
            ->delete() > 0;
    }

    public function normalizeKey(string $draftKey): string
    {
        $draftKey = trim($draftKey);

        if ($draftKey === '' || ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,190}\z/', $draftKey)) {
            throw ValidationException::withMessages([
                'draft_key' => 'The draft key is invalid.',
            ]);
        }

        return $draftKey;
    }

    private function sanitizeFields(array $fields): array
    {
        $sanitized = [];

        foreach ($fields as $name => $value) {
            $name = trim((string) $name);
            if ($name === '' || strlen($name) > 191) {
                continue;
            }

            if (preg_match('/(?:password|passcode|secret|request_token|^_token$)/i', $name)) {
                continue;
            }

            $sanitized[$name] = $this->sanitizeValue($value);
        }

        return $sanitized;
    }

    private function sanitizeValue(mixed $value, int $depth = 0): string|bool|int|float|array|null
    {
        if (is_array($value)) {
            if ($depth >= 4) {
                return [];
            }

            return array_slice(array_map(
                fn (mixed $item) => $this->sanitizeValue($item, $depth + 1),
                $value,
            ), 0, 250);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        $string = (string) $value;

        return function_exists('mb_substr')
            ? mb_substr($string, 0, 10000)
            : substr($string, 0, 10000);
    }
}
