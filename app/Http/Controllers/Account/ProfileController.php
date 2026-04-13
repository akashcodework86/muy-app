<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    /**
     * Stream the signed-in user's avatar from the public disk (avoids relying on the web server symlink).
     */
    public function showAvatar(Request $request): Response
    {
        $user = $request->user();
        if (! $user->avatar_path || ! Storage::disk('public')->exists($user->avatar_path)) {
            abort(404);
        }

        return Storage::disk('public')->response($user->avatar_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function edit(): View
    {
        return view('account.settings', [
            'user' => auth()->user(),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $emailChanged = $user->email !== $data['email'];

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        return redirect()->route('account.settings.edit')->with('status', 'Profile updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return redirect()->route('account.settings.edit')->with('status', 'Password updated.');
    }

    public function updateAvatar(UpdateAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();
        $file = $request->file('avatar');
        $path = $file->store('avatars', 'public');

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => $path]);

        return redirect()->route('account.settings.edit')->with('status', 'Profile photo updated.');
    }

    public function destroyAvatar(): RedirectResponse
    {
        $user = auth()->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return redirect()->route('account.settings.edit')->with('status', 'Profile photo removed.');
    }
}
