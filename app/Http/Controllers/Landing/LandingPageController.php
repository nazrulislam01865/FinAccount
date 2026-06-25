<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Http\Requests\LandingPageInquiryRequest;
use App\Models\LandingPageInquiry;
use App\Support\LandingPageCaptcha;
use App\Support\LandingPageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $systemUserCanPreview = $user
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('landing-page.manage');
        $canPreview = $request->boolean('preview')
            && (Auth::guard('landing_admin')->check() || $systemUserCanPreview);

        abort_if(! LandingPageContent::isPublished() && ! $canPreview, 404);

        return view('landing.show', [
            'landing' => LandingPageContent::current($canPreview),
            'isPreview' => $canPreview,
        ]);
    }


    public function captchaChallenge(Request $request): JsonResponse
    {
        abort_unless(
            (bool) data_get(LandingPageContent::current(), 'contact.captcha.enabled', true),
            404
        );

        return response()->json([
            'success' => true,
            'data' => LandingPageCaptcha::create($request),
        ]);
    }

    public function storeInquiry(LandingPageInquiryRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $inquiry = LandingPageInquiry::query()->create([
            'name' => $validated['name'],
            'business_name' => $validated['business_name'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'email' => $validated['email'] ?? null,
            'message' => $validated['message'] ?? null,
            'status' => LandingPageInquiry::STATUS_NEW,
            'metadata' => [
                'source' => 'landing_page',
                'submitted_at' => now()->toDateTimeString(),
                'captcha_verified' => (bool) data_get(LandingPageContent::current(), 'contact.captcha.enabled', true),
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
