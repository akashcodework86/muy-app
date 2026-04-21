<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgrammeStructureWipeController extends Controller
{
    public function create(Request $request): View
    {
        return view('admin.programme-structure-wipe.create', [
            'wipeConfigured' => $this->wipeSecretIsConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->wipeSecretIsConfigured()) {
            abort(403, 'PROGRAMME_WIPE_SECRET is not configured.');
        }

        $expected = (string) config('muy.programme_wipe_secret');

        $request->validate([
            'wipe_secret' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($expected): void {
                    if (! is_string($value) || $value === '' || ! hash_equals($expected, $value)) {
                        $fail('The wipe secret is incorrect.');
                    }
                },
            ],
            'confirm_phrase' => ['required', 'string', Rule::in(['RESET-PROGRAMME'])],
            'wipe_app_settings' => ['sometimes', 'boolean'],
        ]);

        $exitCode = Artisan::call('programme:wipe-structure', [
            '--force' => true,
            '--wipe-app-settings' => $request->boolean('wipe_app_settings'),
        ]);

        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            return back()
                ->withErrors(['run' => 'Wipe command failed (exit '.$exitCode.').'])
                ->withInput();
        }

        return back()->with([
            'status' => 'Programme structure wiped. Recreate categories, services, and deliverables / targets as needed.',
            'command_output' => $output !== '' ? $output : null,
        ]);
    }

    private function wipeSecretIsConfigured(): bool
    {
        $s = (string) config('muy.programme_wipe_secret', '');

        return strlen($s) >= 8;
    }
}
