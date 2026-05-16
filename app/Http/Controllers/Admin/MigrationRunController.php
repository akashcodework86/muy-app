<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * One-shot HTTP trigger for pending `php artisan migrate --force`.
 * Remove route + this controller after production setup, or keep secret unset.
 */
class MigrationRunController extends Controller
{
    public function execute(Request $request): Response
    {
        $secret = (string) config('muy.migration_run_secret', '');
        $key = (string) $request->query('key', '');

        if (strlen($secret) < 8 || ! hash_equals($secret, $key)) {
            abort(403, 'Unauthorized');
        }

        $exitCode = Artisan::call('migrate', ['--force' => true]);

        $body = "=== php artisan migrate --force ===\n"
            .'Time: '.date('Y-m-d H:i:s')."\n"
            ."Exit code: {$exitCode}\n\n"
            .Artisan::output();

        $status = $exitCode === 0 ? 200 : 500;

        return response(
            '<pre style="background:#111;color:#0f0;padding:20px;font-size:14px;white-space:pre-wrap;">'.e($body).'</pre>',
            $status,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
