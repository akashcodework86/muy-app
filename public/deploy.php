<?php

// Simple deploy script - pull latest code from GitHub
$secret = 'muy-deploy-2024'; // Security key

if (! isset($_GET['key']) || $_GET['key'] !== $secret) {
    exit('❌ Unauthorized - key missing or wrong');
}

echo "<pre style='background:#111;color:#0f0;padding:20px;font-size:14px;'>";
echo "=== MUY APP DEPLOY ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n\n";

$projectPath = '/home/zwkk8e2xgz4h/muy-app';

// Git pull
echo "--- git pull ---\n";
$output = shell_exec("cd $projectPath && /usr/bin/git pull origin master 2>&1");
echo $output."\n";

// Clear caches
echo "--- Clearing caches ---\n";
$output = shell_exec("cd $projectPath && php artisan optimize:clear 2>&1");
echo $output."\n";

// Cache config & routes
echo "--- Caching config & routes ---\n";
$output = shell_exec("cd $projectPath && php artisan config:cache 2>&1");
echo $output."\n";
$output = shell_exec("cd $projectPath && php artisan route:cache 2>&1");
echo $output."\n";

// Run migrations if any
echo "--- Migrations ---\n";
$output = shell_exec("cd $projectPath && php artisan migrate --force 2>&1");
echo $output."\n";

echo "=== DEPLOY COMPLETE ===\n";
echo '</pre>';
