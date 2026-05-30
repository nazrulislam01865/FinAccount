<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Http\Requests\LandingPageInquiryRequest;
use App\Models\LandingPageInquiry;
use App\Support\LandingPageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $canPreview = $request->boolean('preview') && ($user?->hasPermission('landing-page.manage') ?? false);

        abort_if(!LandingPageContent::isPublished() && !$canPreview, 404);

        return view('landing.show', [
            'landing' => LandingPageContent::current($canPreview),
            'isPreview' => $canPreview,
        ]);
    }

    public function storeInquiry(LandingPageInquiryRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $inquiry = LandingPageInquiry::query()->create([
            ...$validated,
            'status' => LandingPageInquiry::STATUS_NEW,
            'metadata' => [
                'source' => 'landing_page',
                'submitted_at' => now()->toDateTimeString(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Demo request received successfully.',
                'data' => ['id' => $inquiry->id],
            ], 201);
        }

        return back()->with('status', 'Demo request received successfully.');
    }
}
