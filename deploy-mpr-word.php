<?php

// Temporary one-shot deployment helper for installing the MPR Word engine on
// hosts where deploy.php is still running from an old OPcache entry.

$secret = 'muy-deploy-2024';

if (! isset($_GET['key']) || ! hash_equals($secret, (string) $_GET['key'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$projectPath = rtrim(getenv('MUY_DEPLOY_PATH') ?: __DIR__, '/');
$run = static function (string $command, array &$output, int &$exitCode) use ($projectPath): void {
    $output = [];
    $exitCode = 0;
    exec('cd '.escapeshellarg($projectPath).' && '.$command.' 2>&1', $output, $exitCode);
};

header('Content-Type: text/plain; charset=UTF-8');
echo "MPR Word engine deployment\n";

$composer = getenv('MUY_COMPOSER') ?: '';
if ($composer === '') {
    foreach (['/opt/cpanel/composer/bin/composer', '/usr/local/bin/composer', '/usr/bin/composer'] as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            $composer = $candidate;
            break;
        }
    }
}

if ($composer === '') {
    echo "ERROR: Composer executable was not found.\n";
    http_response_code(500);
    exit;
}

echo "Composer: {$composer}\n";
$composerHome = $projectPath.'/storage/app/composer';
if (! is_dir($composerHome)) {
    mkdir($composerHome, 0775, true);
}
$run('COMPOSER_HOME='.escapeshellarg($composerHome).' '.escapeshellarg($composer).' install --no-dev --no-interaction --optimize-autoloader', $output, $composerExit);
echo implode("\n", $output)."\n";

if ($composerExit !== 0) {
    echo "ERROR: Composer install failed ({$composerExit}).\n";
    http_response_code(500);
    exit;
}

$run(
    "php -r \"require 'vendor/autoload.php'; exit(class_exists('PhpOffice\\\\PhpWord\\\\PhpWord') ? 0 : 1);\"",
    $output,
    $phpWordExit,
);

if ($phpWordExit !== 0) {
    echo "ERROR: PHPWord is still unavailable.\n\n";
    echo "Diagnostics:\n";
    echo 'PHP: '.PHP_VERSION."\n";
    foreach (['gd', 'zip', 'dom', 'xml', 'json'] as $extension) {
        echo 'ext-'.$extension.': '.(extension_loaded($extension) ? 'loaded' : 'MISSING')."\n";
    }
    echo 'vendor/phpoffice/phpword: '.(is_dir($projectPath.'/vendor/phpoffice/phpword') ? 'present' : 'MISSING')."\n";
    echo "\nIf ext-gd is MISSING, enable it in cPanel → MultiPHP INI Editor, then retry.\n";
    echo "MPR download still works via compatible .doc fallback without PHPWord.\n";
    http_response_code(500);
    exit;
}

$run('php artisan optimize:clear', $output, $clearExit);
echo implode("\n", $output)."\n";

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset.\n";
}

echo "SUCCESS: PHPWord is installed and Laravel caches were cleared.\n";
