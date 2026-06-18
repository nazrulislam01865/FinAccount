<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Support\ActiveLoginSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 401);

        $user->loadMissing(['accountingRole', 'company']);

        return view('profile.index', [
            'profileUser' => $user,
        ]);
    }

    public function photo(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $path = trim((string) ($user->profile_photo_path ?? ''));
        abort_if($path === '' || ! Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, headers: [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('profilePhoto', [
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'profile_photo.required' => 'Please choose a profile picture.',
            'profile_photo.image' => 'The selected file must be a valid image.',
            'profile_photo.mimes' => 'The profile picture must be a JPG, JPEG, PNG, or WebP image.',
            'profile_photo.max' => 'The profile picture may not be larger than 2 MB.',
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        $directory = 'profiles/'.(int) $user->getKey();
        $newPath = $validated['profile_photo']->store($directory, 'public');

        if (! is_string($newPath) || $newPath === '') {
            throw ValidationException::withMessages([
                'profile_photo' => 'The profile picture could not be uploaded. Please try again.',
            ])->errorBag('profilePhoto');
        }

        $oldPath = trim((string) ($user->profile_photo_path ?? ''));

        try {
            DB::transaction(function () use ($user, $newPath): void {
                $user->forceFill(['profile_photo_path' => $newPath])->save();
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPath);
            throw $exception;
        }

        if ($oldPath !== '' && $oldPath !== $newPath && str_starts_with($oldPath, $directory.'/')) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()
            ->route('accounting.profile')
            ->with('profile_status', 'Profile picture updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('passwordUpdate', [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', Password::min(8), 'confirmed'],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'new_password.required' => 'Please enter a new password.',
            'new_password.confirmed' => 'The new password and confirmation do not match.',
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        if (! Hash::check($validated['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ])->errorBag('passwordUpdate');
        }

        if (Hash::check($validated['new_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'new_password' => 'The new password must be different from the current password.',
            ])->errorBag('passwordUpdate');
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->forceFill([
                'password' => Hash::make($validated['new_password']),
                'remember_token' => null,
            ])->save();
        }, 3);

        if (Schema::hasTable('sessions') && $request->hasSession()) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $request->session()->regenerate();
        app(ActiveLoginSession::class)->claim($request, $user);

        return redirect()
            ->to(route('accounting.profile').'#change-password')
            ->with('password_status', 'Password changed successfully.');
    }
}
