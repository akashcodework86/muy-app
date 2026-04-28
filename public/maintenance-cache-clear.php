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
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';

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

