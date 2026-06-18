<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        return filled($previousSessionId) && $previousSessionId !== $currentSessionId;
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

    private function isAvailable(Model $user): bool
    {
        return Schema::hasTable($user->getTable())
            && Schema::hasColumn($user->getTable(), 'active_session_id');
    }
}
