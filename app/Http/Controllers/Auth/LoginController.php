<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\IncubateeLoginPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], [
            'login' => 'email or mobile',
        ]);

        $login = trim((string) $validated['login']);
        $password = (string) $validated['password'];
        $phone = IncubateeLoginPhone::fromInput($login);

        $user = null;
        if ($phone !== null) {
            $user = User::query()
                ->where('role', 'incubatee')
                ->where('phone', $phone)
                ->first();
            if ($user === null || ! Hash::check($password, (string) $user->password)) {
                throw ValidationException::withMessages([
                    'login' => 'Invalid mobile number or password.',
                ]);
            }
            Auth::login($user, $request->boolean('remember'));
        } else {
            if (! Auth::attempt(['email' => $login, 'password' => $password], $request->boolean('remember'))) {
                throw ValidationException::withMessages([
                    'login' => 'Invalid email or password.',
                ]);
            }
            $user = Auth::user();
            if ($user && $user->role === 'incubatee') {
                Auth::logout();

                throw ValidationException::withMessages([
                    'login' => 'Incubatees must log in with their 10-digit mobile number.',
                ]);
            }
        }

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'This account has been disabled. Contact the administrator.',
            ]);
        }

        $request->session()->regenerate();

        if (in_array($user->role, ['state_admin', 'hub_admin', 'district_staff'], true)) {
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
