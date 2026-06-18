<?php

namespace App\Http\Controllers;

use App\Support\HisebGhorBrand;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandAssetController extends Controller
{
    public function logo(): BinaryFileResponse|Response
    {
        return $this->asset(HisebGhorBrand::logoPath());
    }

    public function favicon(): BinaryFileResponse|Response
    {
        return $this->asset(HisebGhorBrand::faviconPath());
    }

    private function asset(?string $path): BinaryFileResponse|Response
    {
        abort_unless($path && Storage::disk('public')->exists($path), 404);
        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
