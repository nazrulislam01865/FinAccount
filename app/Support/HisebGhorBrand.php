<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class HisebGhorBrand
{
    private const LOGO_EXTENSIONS = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
    private const FAVICON_EXTENSIONS = ['ico', 'png', 'jpg', 'jpeg', 'webp'];

    public static function data(): array
    {
        $company = self::company();

        return [
            'name' => $company?->short_name ?: config('app.name', 'HisebGhor'),
            'legal_name' => $company?->name ?: config('app.name', 'HisebGhor'),
            'logo_url' => self::logoUrl(),
            'favicon_url' => self::faviconUrl(),
            'footer_owner' => config('app.footer_owner', 'ITQAN Consulting'),
            'footer_owner_url' => config('app.footer_owner_url', 'https://itqanconsulting.com/'),
        ];
    }

    public static function logoPath(): ?string
    {
        $companyPath = self::companyAssetPath('logo_path', self::LOGO_EXTENSIONS);

        return $companyPath ?: self::newestAssetPath('branding/logo', self::LOGO_EXTENSIONS);
    }

    public static function faviconPath(): ?string
    {
        $companyPath = self::companyAssetPath('favicon_path', self::FAVICON_EXTENSIONS);

        return $companyPath ?: self::newestAssetPath('branding/favicon', self::FAVICON_EXTENSIONS);
    }

    public static function logoUrl(): ?string
    {
        return self::versionedRouteUrl(self::logoPath(), 'brand.logo');
    }

    public static function faviconUrl(): ?string
    {
        return self::versionedRouteUrl(self::faviconPath(), 'brand.favicon');
    }

    private static function company(): ?object
    {
        try {
            $user = Auth::user();
            if (! $user?->company_id || ! Schema::hasColumn('companies', 'short_name')) {
                return null;
            }

            return $user->relationLoaded('company')
                ? $user->getRelation('company')
                : $user->company()->first();
        } catch (Throwable) {
            return null;
        }
    }

    private static function companyAssetPath(string $column, array $extensions): ?string
    {
        try {
            $company = self::company();
            $path = $company?->{$column};
            if (! filled($path) || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true)) {
                return null;
            }

            return Storage::disk('public')->exists($path) ? $path : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function newestAssetPath(string $directory, array $extensions): ?string
    {
        $disk = Storage::disk('public');
        try {
            $files = array_values(array_filter($disk->files($directory), static fn (string $path): bool => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true)));
            if ($files === []) {
                return null;
            }
            usort($files, static fn (string $left, string $right): int => $disk->lastModified($right) <=> $disk->lastModified($left));
            return $files[0] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function versionedRouteUrl(?string $path, string $routeName): ?string
    {
        if ($path === null) {
            return null;
        }
        try {
            $disk = Storage::disk('public');
            $version = hash_file('sha256', $disk->path($path)) ?: (string) $disk->lastModified($path);
        } catch (Throwable) {
            $version = (string) time();
        }
        return route($routeName, ['v' => $version], false);
    }
}
