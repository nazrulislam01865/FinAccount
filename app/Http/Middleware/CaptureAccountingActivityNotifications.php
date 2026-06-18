<?php

namespace App\Http\Middleware;

use App\Services\Notifications\AccountingActivityDescriptor;
use App\Services\Notifications\AccountingNotificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CaptureAccountingActivityNotifications
{
    private const EXCLUDED_ROUTES = [
        'accounting.profile',
        'accounting.profile.photo',
        'accounting.profile.photo.update',
        'accounting.profile.password',
        'accounting.notifications.index',
        'accounting.notifications.feed',
        'accounting.notifications.read-all',
        'accounting.notifications.read',
        'accounting.notifications.pusher-auth',
        'accounting.form-drafts.show',
        'accounting.form-drafts.store',
        'accounting.form-drafts.destroy',
    ];

    public function __construct(
        private readonly AccountingNotificationService $notifications,
        private readonly AccountingActivityDescriptor $activityDescriptor,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldNotify($request, $response)) {
            return $response;
        }

        try {
            $activity = $this->activityDescriptor->describe($request);
            if ($activity !== null) {
                $this->notifications->notifyCompanyAdministrators(
                    (int) $request->user()->company_id,
                    $activity,
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function shouldNotify(Request $request, Response $response): bool
    {
        if (! $request->user() || $request->isMethod('GET') || $response->getStatusCode() >= 400) {
            return false;
        }

        $routeName = (string) $request->route()?->getName();

        return $routeName !== ''
            && ! in_array($routeName, self::EXCLUDED_ROUTES, true)
            && $this->activityDescriptor->supports($request);
    }
}
