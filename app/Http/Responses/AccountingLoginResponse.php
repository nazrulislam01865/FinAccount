<?php

namespace App\Http\Responses;

use App\Support\AccountingRbac;
use App\Support\ActiveLoginSession;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class AccountingLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();
        $request->session()->put('hisebghor.last_activity_at', now()->timestamp);

        $replacedAnotherSession = app(ActiveLoginSession::class)->claim($request, $user);
        $destination = AccountingRbac::firstAllowedDestination($user);
        $redirect = redirect()->intended(route($destination['route'], $destination['parameters']));

        if ($replacedAnotherSession) {
            $redirect->with(
                'login_notice',
                'Login successful. Another active session for this account was logged out. You are now the only active user for this account.'
            );
        }

        return $redirect;
    }
}
