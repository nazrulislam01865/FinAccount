<?php

namespace App\Http\Controllers\Accounting\System;

use App\Http\Controllers\Controller;
use App\Support\HisebGhorBrand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandSettingsController extends Controller
{
    public function index(): View
    {
        return view('system.settings', ['brand' => HisebGhorBrand::data()]);
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:5120'],
        ]);

        $company = $request->user()->company;
        abort_unless($company, 404);
        $path = $validated['logo']->store('companies/'.$company->id.'/branding/logo', 'public');
        $this->replaceCompanyAsset($company->logo_path, $path);
        $company->update(['logo_path' => $path, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Company logo updated successfully.');
    }

    public function updateFavicon(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'favicon' => ['required', 'file', 'mimes:ico,png,jpg,jpeg,webp', 'max:1024'],
        ]);

        $company = $request->user()->company;
        abort_unless($company, 404);
        $path = $validated['favicon']->store('companies/'.$company->id.'/branding/favicon', 'public');
        $this->replaceCompanyAsset($company->favicon_path, $path);
        $company->update(['favicon_path' => $path, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Company favicon updated successfully.');
    }

    private function replaceCompanyAsset(?string $oldPath, string $newPath): void
    {
        if (filled($oldPath) && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }
}
