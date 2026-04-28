<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

/**
 * TEMPORARY MAINTENANCE ENDPOINT
 *
 * Usage:
 *   /maintenance-cache-clear.php?key=REPLACE_WITH_STRONG_KEY
 *
 * Optional:
 *   &delete=1   (self-delete file after successful run)
 */

$expectedKey = 'akash';
$providedKey = isset($_GET['key']) ? (string) $_GET['key'] : '';

if ($providedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden\n";
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $start = __DIR__;
    $autoloadPath = null;
    $bootstrapPath = null;
    $cursor = $start;

    for ($i = 0; $i < 8; $i++) {
        $candidateAutoload = $cursor.'/vendor/autoload.php';
        $candidateBootstrap = $cursor.'/bootstrap/app.php';
        if ($autoloadPath === null && is_file($candidateAutoload)) {
            $autoloadPath = $candidateAutoload;
        }
        if ($bootstrapPath === null && is_file($candidateBootstrap)) {
            $bootstrapPath = $candidateBootstrap;
        }
        if ($autoloadPath !== null && $bootstrapPath !== null) {
            break;
        }
        $parent = dirname($cursor);
        if ($parent === $cursor) {
            break;
        }
        $cursor = $parent;
    }

    if ($autoloadPath === null || $bootstrapPath === null) {
        // Common cPanel layout fallback:
        // /home/<user>/public_html/phase3 (this file) and /home/<user>/muy-app (Laravel app)
        $homeDir = dirname(dirname($start));
        $fallbackRoots = [
            $homeDir.'/muy-app',
            $homeDir.'/www/muy-app',
            $homeDir.'/public_html/muy-app',
        ];

        foreach ($fallbackRoots as $root) {
            $candidateAutoload = $root.'/vendor/autoload.php';
            $candidateBootstrap = $root.'/bootstrap/app.php';
            if (is_file($candidateAutoload) && is_file($candidateBootstrap)) {
                $autoloadPath = $candidateAutoload;
                $bootstrapPath = $candidateBootstrap;
                break;
            }
        }
    }

    if ($autoloadPath === null || $bootstrapPath === null) {
        throw new RuntimeException('Could not locate Laravel root. Checked from: '.$start);
    }

    require $autoloadPath;
    $app = require $bootstrapPath;

    /** @var Kernel $kernel */
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    echo "Running optimize:clear...\n";
    Artisan::call('optimize:clear');
    echo Artisan::output()."\n";

    echo "Running view:clear...\n";
    Artisan::call('view:clear');
    echo Artisan::output()."\n";

    echo "Done.\n";

    if (isset($_GET['delete']) && (string) $_GET['delete'] === '1') {
        $self = __FILE__;
        if (@unlink($self)) {
            echo "Self-delete: OK\n";
        } else {
            echo "Self-delete: FAILED (delete file manually)\n";
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: ".$e->getMessage()."\n";
}

