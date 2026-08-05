<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Invalid email or password.',
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been disabled. Contact the administrator.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if (in_array($user->role, ['state_admin', 'hub_admin', 'district_staff'], true)) {
            $request->session()->flash('show_case_study_shortlist_announcement', true);

            $roleLabel = str_replace('_', ' ', (string) $user->role);
            $activity->log(
                type: 'user.login',
                title: ucfirst($roleLabel).' '.$user->name.' signed in',
                actor: $user,
            );
        }

        if ($user->role === 'district_staff' && empty($user->avatar_path)) {
            $request->session()->flash(
                'profile_photo_reminder',
                'Please upload your profile picture first. You can add it from Settings.'
            );
        }

        if ($user->role === 'incubatee') {
            return redirect()->route('incubatee.dashboard');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
