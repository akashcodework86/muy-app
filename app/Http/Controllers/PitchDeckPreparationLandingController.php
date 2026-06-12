<?php

namespace App\Http\Controllers;

use App\Support\PitchDeckPreparationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PitchDeckPreparationLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(PitchDeckPreparationAccess::canViewDashboard($request->user()), 403);

        if ($request->user()?->role === 'state_staff' && PitchDeckPreparationAccess::canSubmit($request->user())) {
            return redirect()->route('spoc.pitch-deck-preparations.create');
        }

        if ($request->user()?->role === 'state_admin') {
            return redirect()->route('admin.pitch-deck-preparations.dashboard');
        }

        return redirect()->route('spoc.pitch-deck-preparations.dashboard');
    }
}
