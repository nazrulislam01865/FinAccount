<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ActiveLoginSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    public function keepAlive(Request $request, ActiveLoginSession $activeLoginSession): JsonResponse
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isAccountActive') && ! $user->isAccountActive()) {
            $activeLoginSession->release($request, $user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'active' => false,
                'reason' => 'account-inactive',
                'message' => 'Your account is no longer active.',
                'redirect' => route('login'),
            ], 401);
        }

        if ($user && ! $activeLoginSession->isCurrent($request, $user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'active' => false,
                'reason' => 'session-replaced',
                'message' => 'You were logged out because this account was signed in on another device or browser.',
                'redirect' => route('login', ['reason' => 'session-replaced']),
            ], 401);
        }

        $request->session()->put('hisebghor.last_activity_at', now()->timestamp);

        return response()->json(['active' => true]);
    }

    public function timeout(Request $request, ActiveLoginSession $activeLoginSession): JsonResponse|RedirectResponse
    {
        $activeLoginSession->release($request, $request->user());
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('status', 'Your session expired after 15 minutes of inactivity. Please sign in again.');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your session expired after 15 minutes of inactivity.',
                'redirect' => route('login'),
            ]);
        }

        return redirect()->route('login');
    }
}
