<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ActiveLoginSession
{
    /**
     * Make the current Laravel session the only active session for this user.
     *
     * Returns true when an older active session was replaced.
     */
    public function claim(Request $request, ?Model $user): bool
    {
        if (! $user || ! $request->hasSession() || ! $this->isAvailable($user)) {
            return false;
        }

        $currentSessionId = (string) $request->session()->getId();
        if ($currentSessionId === '') {
            return false;
        }

        $previousSessionId = DB::transaction(function () use ($user, $currentSessionId): ?string {
            $lockedUser = $user->newQuery()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedUser) {
                return null;
            }

            $previousSessionId = filled($lockedUser->active_session_id)
                ? (string) $lockedUser->active_session_id
                : null;

            if ($previousSessionId !== $currentSessionId) {
                DB::table($user->getTable())
                    ->where($user->getKeyName(), $lockedUser->getKey())
                    ->update(['active_session_id' => $currentSessionId]);
            }

            return $previousSessionId;
        }, 3);

        $user->setAttribute('active_session_id', $currentSessionId);

        $replacedAnotherSession = filled($previousSessionId)
            && $previousSessionId !== $currentSessionId;

        if ($replacedAnotherSession) {
            $this->rememberReplacement((string) $previousSessionId);
            $this->destroyStoredSession($request, (string) $previousSessionId);
        }

        return $replacedAnotherSession;
    }

    /**
     * Confirm that this request still owns the user's active-login marker.
     */
    public function isCurrent(Request $request, ?Model $user): bool
    {
        if (! $user || ! $request->hasSession() || ! $this->isAvailable($user)) {
            return true;
        }

        $currentSessionId = (string) $request->session()->getId();
        if ($currentSessionId === '') {
            return true;
        }

        $activeSessionId = filled($user->active_session_id)
            ? (string) $user->active_session_id
            : null;

        if ($activeSessionId === null) {
            return $this->claimUntrackedSession($user, $currentSessionId);
        }

        return hash_equals($activeSessionId, $currentSessionId);
    }

    /**
     * Return true once for a session that was replaced by a newer login.
     *
     * This marker survives deletion of the old session record, allowing the
     * old browser to receive a clear logout message instead of a 403 page.
     */
    public function consumeReplacement(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $sessionId = (string) $request->session()->getId();
        if ($sessionId === '') {
            return false;
        }

        try {
            return (bool) Cache::pull($this->replacementCacheKey($sessionId), false);
        } catch (Throwable $exception) {
            Log::warning('Unable to read the replaced-login marker.', [
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * Clear the marker only when the current request owns it.
     */
    public function release(Request $request, ?Model $user): void
    {
        if (! $user || ! $request->hasSession() || ! $this->isAvailable($user)) {
            return;
        }

        $currentSessionId = (string) $request->session()->getId();
        if ($currentSessionId === '') {
            return;
        }

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->where('active_session_id', $currentSessionId)
            ->update(['active_session_id' => null]);

        $user->setAttribute('active_session_id', null);
    }

    public function clearForUser(?Model $user): void
    {
        if (! $user || ! $this->isAvailable($user)) {
            return;
        }

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['active_session_id' => null]);

        $user->setAttribute('active_session_id', null);
    }

    private function claimUntrackedSession(Model $user, string $currentSessionId): bool
    {
        $claimedSessionId = DB::transaction(function () use ($user, $currentSessionId): ?string {
            $lockedUser = $user->newQuery()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedUser) {
                return null;
            }

            if (blank($lockedUser->active_session_id)) {
                DB::table($user->getTable())
                    ->where($user->getKeyName(), $lockedUser->getKey())
                    ->update(['active_session_id' => $currentSessionId]);

                return $currentSessionId;
            }

            return (string) $lockedUser->active_session_id;
        }, 3);

        $user->setAttribute('active_session_id', $claimedSessionId);

        return $claimedSessionId !== null && hash_equals($claimedSessionId, $currentSessionId);
    }

    private function rememberReplacement(string $sessionId): void
    {
        try {
            $minutes = max(
                5,
                (int) config('session.lifetime', 120),
                (int) config('session.inactive_timeout', 15),
                (int) config('session.landing_admin_inactive_timeout', 15),
            );

            Cache::put(
                $this->replacementCacheKey($sessionId),
                true,
                now()->addMinutes($minutes + 5),
            );
        } catch (Throwable $exception) {
            Log::warning('Unable to store the replaced-login marker.', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function destroyStoredSession(Request $request, string $sessionId): void
    {
        try {
            $request->session()->getHandler()->destroy($sessionId);
        } catch (Throwable $exception) {
            // The active-session marker still prevents use of the old login if
            // a custom session driver cannot destroy another session directly.
            Log::warning('Unable to immediately destroy the previous login session.', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function replacementCacheKey(string $sessionId): string
    {
        return 'hisebghor:login-session:replaced:'.hash('sha256', $sessionId);
    }

    private function isAvailable(Model $user): bool
    {
        return Schema::hasTable($user->getTable())
            && Schema::hasColumn($user->getTable(), 'active_session_id');
    }
}
