<?php

// Deploy: git pull → composer install → migrate → clear caches → rebuild config/routes.
// Optional env: MUY_DEPLOY_PATH, MUY_COMPOSER (see comments in repo README or below).

$secret = 'muy-deploy-2024'; // Security key

if (! isset($_GET['key']) || $_GET['key'] !== $secret) {
    exit('❌ Unauthorized - key missing or wrong');
}

$projectPath = getenv('MUY_DEPLOY_PATH') ?: __DIR__;
$projectPath = rtrim($projectPath, '/');

$out = static function (?string $s): void {
    echo ($s !== null && $s !== '' ? $s : '(no output)')."\n";
};

$run = static function (string $projectPath, string $bashCommand, array &$lines, int &$exitCode): void {
    $lines = [];
    $exitCode = 0;
    $cmd = 'cd '.escapeshellarg($projectPath).' && '.$bashCommand.' 2>&1';
    exec($cmd, $lines, $exitCode);
};

echo "<pre style='background:#111;color:#0f0;padding:20px;font-size:14px;'>";
echo "=== MUY APP DEPLOY ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n";
echo 'Project: '.htmlspecialchars($projectPath, ENT_QUOTES, 'UTF-8')."\n\n";

echo "--- git pull ---\n";
$lines = [];
$run($projectPath, '/usr/bin/git pull origin master', $lines, $gitExit);
$out(implode("\n", $lines));
if ($gitExit !== 0) {
    echo "\n<span style='color:#f87171;font-weight:bold'>❌ git pull failed (exit {$gitExit}). Fix and redeploy.</span>\n";
    echo '</pre>';
    exit;
}

echo "--- composer install ---\n";
$composerBin = getenv('MUY_COMPOSER') ?: 'composer';
$run($projectPath, $composerBin.' install --no-dev --no-interaction --optimize-autoloader', $lines, $composerExit);
$out(implode("\n", $lines));
if ($composerExit !== 0) {
    echo "\n<span style='color:#fbbf24;font-weight:bold'>⚠️ composer install failed (exit {$composerExit}). Check MUY_COMPOSER / PATH. Continuing to migrations…</span>\n\n";
}

echo "--- migrations (php artisan migrate --force) ---\n";
$run($projectPath, 'php artisan migrate --force --no-interaction', $lines, $migrateExit);
$out(implode("\n", $lines));
if ($migrateExit !== 0) {
    echo "\n<span style='color:#f87171;font-weight:bold'>❌ MIGRATIONS FAILED (exit {$migrateExit}). Database was NOT fully updated. Fix the error above, push, and run deploy again.</span>\n";
    echo "\n<span style='color:#94a3b8'>Tip: if you see duplicate column on `services`, pull latest code — migration 2026_04_28_100000 is idempotent.</span>\n";
    echo '</pre>';
    exit;
}

echo "--- optimize:clear ---\n";
$run($projectPath, 'php artisan optimize:clear', $lines, $clearExit);
$out(implode("\n", $lines));

echo "--- config:cache ---\n";
$run($projectPath, 'php artisan config:cache', $lines, $cfgExit);
$out(implode("\n", $lines));

echo "--- route:cache ---\n";
$run($projectPath, 'php artisan route:cache', $lines, $routeExit);
$out(implode("\n", $lines));

echo "\n--- social media deploy check ---\n";
$smpFiles = [
    'app/Http/Controllers/SocialMediaPostController.php',
    'app/Http/Controllers/SocialMediaPostLandingController.php',
    'app/Services/SocialMediaPostPreviewService.php',
    'app/Support/SocialMediaPostAccess.php',
    'app/Support/SocialMediaPostPlatforms.php',
    'config/social_media_posts.php',
    'resources/views/social-media-posts/form.blade.php',
    'public/social-media-health.php',
];
foreach ($smpFiles as $relative) {
    $full = $projectPath.'/'.$relative;
    echo $relative.': '.(is_file($full) ? 'OK' : 'MISSING')."\n";
}

if (is_file($projectPath.'/public/social-media-health.php')) {
    echo "Health URL: /social-media-health.php?key=akash\n";
}

$run($projectPath, 'php artisan route:list --name=social-media-posts.index --columns=method,uri,name,action', $lines, $routeListExit);
if ($lines !== []) {
    echo implode("\n", $lines)."\n";
}

echo "\n=== DEPLOY COMPLETE ===\n";
echo '</pre>';
