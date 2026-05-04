<?php

// Same pipeline as ../deploy.php; project root is parent of public/.
// Optional env: MUY_DEPLOY_PATH, MUY_COMPOSER (see ../deploy.php)

$secret = 'muy-deploy-2024'; // Security key

if (! isset($_GET['key']) || $_GET['key'] !== $secret) {
    exit('❌ Unauthorized - key missing or wrong');
}

$projectPath = getenv('MUY_DEPLOY_PATH') ?: dirname(__DIR__);
$projectPath = rtrim($projectPath, '/');

$out = static function (?string $s): void {
    echo ($s !== null && $s !== '' ? $s : '(no output)')."\n";
};

echo "<pre style='background:#111;color:#0f0;padding:20px;font-size:14px;'>";
echo "=== MUY APP DEPLOY ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n";
echo 'Project: '.htmlspecialchars($projectPath, ENT_QUOTES, 'UTF-8')."\n\n";

echo "--- git pull ---\n";
$out(shell_exec('cd '.escapeshellarg($projectPath).' && /usr/bin/git reset --hard origin/master 2>&1'));
$out(shell_exec('cd '.escapeshellarg($projectPath).' && /usr/bin/git pull origin master 2>&1'));

echo "--- composer install ---\n";
$composerBin = getenv('MUY_COMPOSER') ?: 'composer';
$out(shell_exec('cd '.escapeshellarg($projectPath).' && '.$composerBin.' install --no-dev --no-interaction --optimize-autoloader 2>&1'));

echo "--- migrations (php artisan migrate --force) ---\n";
$out(shell_exec('cd '.escapeshellarg($projectPath).' && php artisan migrate --force --no-interaction 2>&1'));

echo "--- optimize:clear ---\n";
$out(shell_exec('cd '.escapeshellarg($projectPath).' && php artisan optimize:clear 2>&1'));

echo "--- config:cache ---\n";
$out(shell_exec('cd '.escapeshellarg($projectPath).' && php artisan config:cache 2>&1'));

echo "--- route:cache ---\n";
$out(shell_exec('cd '.escapeshellarg($projectPath).' && php artisan route:cache 2>&1'));

echo "=== DEPLOY COMPLETE ===\n";
echo '</pre>';
