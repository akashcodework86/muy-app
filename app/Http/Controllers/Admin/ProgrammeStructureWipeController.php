<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * One-shot HTTP trigger for {@see \App\Console\Commands\WipeProgrammeStructureCommand}.
 * DELETE this controller and the programme-wipe-run route from web.php after you have run the wipe on production.
 */
class ProgrammeStructureWipeController extends Controller
{
    public function execute(Request $request): Response
    {
        $secret = (string) config('muy.programme_wipe_secret', '');
        $key = (string) $request->query('key', '');

        if (strlen($secret) < 8 || ! hash_equals($secret, $key)) {
            abort(403, 'Unauthorized');
        }

        $wipeAppSettings = $request->query('app_settings') === '1'
            || filter_var($request->query('app_settings', false), FILTER_VALIDATE_BOOLEAN);

        $exitCode = Artisan::call('programme:wipe-structure', [
            '--force' => true,
            '--wipe-app-settings' => $wipeAppSettings,
        ]);

        $body = "=== programme:wipe-structure ===\nTime: ".date('Y-m-d H:i:s')."\nExit code: {$exitCode}\n\n".Artisan::output();
        $status = $exitCode === 0 ? 200 : 500;

        return response(
            '<pre style="background:#111;color:#0f0;padding:20px;font-size:14px;white-space:pre-wrap;">'.e($body).'</pre>',
            $status,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
