<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PrintedDocumentBrand
{
    /** @param array<string,mixed> $company */
    public static function company(array $company = []): array
    {
        $fixed = (array) config('document_company', []);

        return array_merge($company, [
            'name' => (string) ($fixed['name'] ?? 'BASHIR AGRO'),
            'short_name' => (string) ($fixed['short_name'] ?? 'BA'),
            'address' => (string) ($fixed['address'] ?? ''),
            'phone' => (string) ($fixed['phone'] ?? ''),
            // Company email is intentionally excluded from printed documents.
            'email' => null,
            'website' => (string) ($fixed['website'] ?? ''),
            // Use the bundled Bashir Agro logo on every printed document so cloud
            // storage paths, stale company uploads, or missing symlinks cannot replace it.
            'logo_path' => (string) ($fixed['logo_path'] ?? 'images/receipts/bashir-agro-favicon.jpg'),
        ]);
    }

    public static function logoFilePath(mixed $path = null): ?string
    {
        $configuredFallback = (string) config('document_company.logo_path', 'images/receipts/bashir-agro-favicon.jpg');
        $candidates = [];

        foreach ([$path, $configuredFallback] as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $candidate = trim($candidate);
            if (str_starts_with($candidate, 'data:') || preg_match('#^https?://#i', $candidate)) {
                continue;
            }

            if (str_starts_with($candidate, DIRECTORY_SEPARATOR)) {
                $candidates[] = $candidate;
            }

            $normalized = ltrim($candidate, '/');
            $normalized = preg_replace('#^(public/|storage/)#', '', $normalized) ?: $normalized;

            try {
                $candidates[] = Storage::disk('public')->path($normalized);
            } catch (\Throwable) {
                // Continue through local fallbacks.
            }

            $candidates[] = storage_path('app/public/'.$normalized);
            $candidates[] = public_path($candidate);
            $candidates[] = public_path($normalized);
            $candidates[] = public_path('storage/'.$normalized);
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function logoDataUri(mixed $path = null): ?string
    {
        if (is_string($path) && str_starts_with(trim($path), 'data:image/')) {
            return trim($path);
        }

        $file = self::logoFilePath($path);
        if (! $file) {
            return null;
        }

        $bytes = @file_get_contents($file);
        if ($bytes === false) {
            return null;
        }

        $mime = @mime_content_type($file) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
