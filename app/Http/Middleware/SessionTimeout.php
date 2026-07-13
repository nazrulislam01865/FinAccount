<?php

namespace App\Http\Middleware;

use App\Support\ActiveLoginSession;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    public function __construct(private readonly ActiveLoginSession $activeLoginSession)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('landing-admin*')) {
            return $this->handleLandingAdminSession($request, $next);
        }

        if ($this->activeLoginSession->consumeReplacement($request)) {
            return $this->logoutAccountingUser(
                $request,
                'You were logged out because a newer login used the same account. The newer user is now signed in.',
                'You were logged out because a newer login used the same account.',
                false,
                'session-replaced',
                true
            );
        }

        if (! Auth::check()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && method_exists($user, 'isAccountActive') && ! $user->isAccountActive()) {
            return $this->logoutAccountingUser(
                $request,
                'Your account is '.$user->accountStatusLabel().'. Please contact the System Admin.',
                'Your account is '.$user->accountStatusLabel().'.',
                true
            );
        }

        if ($user && ! $this->activeLoginSession->isCurrent($request, $user)) {
            return $this->logoutAccountingUser(
                $request,
                'You were logged out because this account was signed in on another device or browser. Only one active login is allowed per user. If this was not you, change your password immediately.',
                'You were logged out because this account was signed in on another device or browser. Only one active login is allowed per user.',
                false,
                'session-replaced',
                true
            );
        }

        $timeoutMinutes = (int) config('session.inactive_timeout', env('SESSION_INACTIVE_TIMEOUT', 15));

        if ($timeoutMinutes <= 0) {
            return $next($request);
        }

        $now = now()->timestamp;
        $lastActivity = (int) $request->session()->get('hisebghor.last_activity_at', $now);

        if (($now - $lastActivity) >= max(60, $timeoutMinutes * 60)) {
            return $this->logoutAccountingUser(
                $request,
                'Your session expired after '.$timeoutMinutes.' minutes of inactivity. Please sign in again.',
                'Your session expired after '.$timeoutMinutes.' minutes of inactivity.',
                true
            );
        }

        $request->session()->put('hisebghor.last_activity_at', $now);

        return $next($request);
    }

    private function handleLandingAdminSession(Request $request, Closure $next): Response
    {
        if ($this->activeLoginSession->consumeReplacement($request)) {
            return $this->logoutLandingAdmin(
                $request,
                'You were logged out because a newer login used the same Landing Admin account. The newer user is now signed in.',
                'You were logged out because a newer login used the same Landing Admin account.',
                false,
                'session-replaced',
                true
            );
        }

        if (! Auth::guard('landing_admin')->check()) {
            return $next($request);
        }

        $admin = Auth::guard('landing_admin')->user();

        if ($admin && method_exists($admin, 'isActive') && ! $admin->isActive()) {
            return $this->logoutLandingAdmin(
                $request,
                'Landing Admin account is inactive.',
                'Landing Admin account is inactive.',
                true
            );
        }

        if ($admin && ! $this->activeLoginSession->isCurrent($request, $admin)) {
            return $this->logoutLandingAdmin(
                $request,
                'You were logged out because this Landing Admin account was signed in on another device or browser. Only one active login is allowed per user.',
                'You were logged out because this Landing Admin account was signed in on another device or browser.',
                false,
                'session-replaced',
                true
            );
        }

        $timeoutMinutes = (int) config('session.landing_admin_inactive_timeout', env('LANDING_ADMIN_SESSION_INACTIVE_TIMEOUT', 15));

        if ($timeoutMinutes <= 0) {
            return $next($request);
        }

        $now = now()->timestamp;
        $lastActivity = (int) $request->session()->get('landing_admin_last_activity_at', $now);

        if (($now - $lastActivity) >= max(60, $timeoutMinutes * 60)) {
            return $this->logoutLandingAdmin(
                $request,
                'Your Landing Admin session expired after '.$timeoutMinutes.' minutes of inactivity. Please log in again.',
                'Your Landing Admin session expired after '.$timeoutMinutes.' minutes of inactivity.',
                true
            );
        }

        $request->session()->put('landing_admin_last_activity_at', $now);

        return $next($request);
    }

    private function logoutAccountingUser(
        Request $request,
        string $flashMessage,
        string $jsonMessage,
        bool $releaseActiveSession,
        ?string $reason = null,
        bool $persistNotice = false
    ): JsonResponse|RedirectResponse {
        if ($releaseActiveSession) {
            $this->activeLoginSession->release($request, $request->user());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($persistNotice) {
            $request->session()->put('hisebghor.logout_notice', $flashMessage);
        } else {
            $request->session()->flash('status', $flashMessage);
        }

        $loginUrl = route('login', $reason ? ['reason' => $reason] : []);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $jsonMessage,
                'reason' => $reason,
                'redirect' => $loginUrl,
            ], 401);
        }

        return redirect()->to($loginUrl);
    }

    private function logoutLandingAdmin(
        Request $request,
        string $flashMessage,
        string $jsonMessage,
        bool $releaseActiveSession,
        ?string $reason = null,
        bool $persistNotice = false
    ): JsonResponse|RedirectResponse {
        if ($releaseActiveSession) {
            $this->activeLoginSession->release($request, Auth::guard('landing_admin')->user());
        }

        Auth::guard('landing_admin')->logout();
        $request->session()->forget('landing_admin_last_activity_at');
        $request->session()->migrate(true);
        $request->session()->regenerateToken();

        if ($persistNotice) {
            $request->session()->put('landing_admin_logout_notice', $flashMessage);
        } else {
            $request->session()->flash('status', $flashMessage);
        }

        $loginUrl = route('landing-admin.login', $reason ? ['reason' => $reason] : []);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $jsonMessage,
                'reason' => $reason,
                'redirect' => $loginUrl,
            ], 401);
        }

        return redirect()->to($loginUrl);
    }
}
