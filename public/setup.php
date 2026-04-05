<?php
// SECURITY: Delete this file after setup is complete!
// Access: http://ukrbi.in/phase3/setup.php

$home = '/home/zwkk8e2xgz4h';
$laravelRoot = $home . '/muy-app';
$publicHtml = $home . '/public_html/phase3';

echo '<pre style="font-family:monospace;background:#1a1a1a;color:#00ff00;padding:20px;">';
echo "=== MUY App Setup Script ===\n\n";

// Step 1: Check muy-app extraction
echo "STEP 1: Checking muy-app folder...\n";
if (is_dir($laravelRoot)) {
    echo "  ✓ muy-app folder EXISTS\n";
    $dirs = ['app', 'vendor', 'public', 'database', 'routes', 'storage'];
    foreach ($dirs as $d) {
        $exists = is_dir($laravelRoot . '/' . $d) ? '✓' : '✗ MISSING';
        echo "  $exists  $d/\n";
    }
} else {
    echo "  ✗ muy-app folder NOT FOUND\n";
    echo "  Extracting zip...\n";
    $zip = new ZipArchive();
    if ($zip->open($home . '/muy-app-deploy.zip') === TRUE) {
        $zip->extractTo($home . '/muy-app/');
        $zip->close();
        echo "  ✓ Extraction complete!\n";
    } else {
        echo "  ✗ Cannot open zip file!\n";
    }
}

echo "\n";

// Step 2: Create .env
echo "STEP 2: Creating .env file...\n";
$env = 'APP_NAME=MUY
APP_ENV=production
APP_KEY=base64:rKh5ciNo+rtv3+N+fDHI/vcjx/LbM2yiOuuiwyrN7RI=
APP_DEBUG=false
APP_URL=http://ukrbi.in/phase3
APP_TIMEZONE=Asia/Kolkata

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=rbiphase3
DB_USERNAME=zwkk8e2xgz4h
DB_PASSWORD=Px@$RRaC3j3%

SESSION_DRIVER=database
SESSION_LIFETIME=120
QUEUE_CONNECTION=database
CACHE_STORE=database

LOG_CHANNEL=stack
LOG_LEVEL=error
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@ukrbi.in
MAIL_FROM_NAME=MUY
';
if (file_put_contents($laravelRoot . '/.env', $env)) {
    echo "  ✓ .env created successfully\n";
} else {
    echo "  ✗ Failed to create .env\n";
}

echo "\n";

// Step 3: Copy public files
echo "STEP 3: Copying public files to public_html/phase3...\n";
if (!is_dir($publicHtml)) {
    mkdir($publicHtml, 0755, true);
}

function copyDir($src, $dst) {
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    foreach (scandir($src) as $file) {
        if ($file === '.' || $file === '..') continue;
        $s = $src . '/' . $file;
        $d = $dst . '/' . $file;
        if (is_dir($s)) copyDir($s, $d);
        else copy($s, $d);
    }
}
$publicSrc = $laravelRoot . '/public';
copyDir($publicSrc, $publicHtml);
echo "  ✓ Public files copied\n";

echo "\n";

// Step 4: Update index.php
echo "STEP 4: Updating index.php paths...\n";
$indexContent = '<?php
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
define(\'LARAVEL_START\', microtime(true));
if (file_exists($maintenance = __DIR__.\'/../../muy-app/storage/framework/maintenance.php\')) {
    require $maintenance;
}
require __DIR__.\'/../../muy-app/vendor/autoload.php\';
$app = require_once __DIR__.\'/../../muy-app/bootstrap/app.php\';
$app->handleRequest(Request::capture());
';
if (file_put_contents($publicHtml . '/index.php', $indexContent)) {
    echo "  ✓ index.php updated with correct paths\n";
} else {
    echo "  ✗ Failed to update index.php\n";
}

echo "\n";

// Step 5: Set permissions
echo "STEP 5: Setting permissions...\n";
chmod($laravelRoot . '/storage', 0775);
chmod($laravelRoot . '/bootstrap/cache', 0775);

function chmodR($path, $mode) {
    if (is_dir($path)) {
        chmod($path, $mode);
        foreach (scandir($path) as $f) {
            if ($f !== '.' && $f !== '..') chmodR($path.'/'.$f, $mode);
        }
    } else {
        chmod($path, $mode);
    }
}
chmodR($laravelRoot . '/storage', 0775);
chmodR($laravelRoot . '/bootstrap/cache', 0775);
echo "  ✓ Permissions set (775)\n";

echo "\n";

// Step 6: Run Migrations via artisan
echo "STEP 6: Running artisan migrate...\n";
chdir($laravelRoot);
$output = shell_exec('cd ' . $laravelRoot . ' && php artisan migrate --force 2>&1');
if ($output) {
    echo "  " . str_replace("\n", "\n  ", trim($output)) . "\n";
} else {
    echo "  (no output - check if php artisan works)\n";
}

echo "\n";

// Step 7: Run Seeders
echo "STEP 7: Running seeders...\n";
$output2 = shell_exec('cd ' . $laravelRoot . ' && php artisan db:seed --force 2>&1');
if ($output2) {
    echo "  " . str_replace("\n", "\n  ", trim($output2)) . "\n";
} else {
    echo "  (no output)\n";
}

echo "\n";

// Step 8: Cache config/routes
echo "STEP 8: Caching config & routes...\n";
$c1 = shell_exec('cd ' . $laravelRoot . ' && php artisan config:cache 2>&1');
$c2 = shell_exec('cd ' . $laravelRoot . ' && php artisan route:cache 2>&1');
echo "  " . trim($c1) . "\n";
echo "  " . trim($c2) . "\n";

echo "\n=== SETUP COMPLETE ===\n";
echo "Visit: http://ukrbi.in/phase3\n";
echo "\n⚠️  DELETE this file now: public_html/phase3/setup.php\n";
echo '</pre>';
