<?php
$secret = 'muy-deploy-2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    exit('Unauthorized');
}
$projectPath = '/home/zwkk8e2xgz4h/muy-app';
echo "<pre style='background:#111;color:#0f0;padding:20px;font-size:14px;'>";
echo "=== GIT FORCE RESET ===\n\n";
echo "--- git reset --hard origin/master ---\n";
$output = shell_exec("cd $projectPath && /usr/bin/git fetch origin 2>&1 && /usr/bin/git reset --hard origin/master 2>&1");
echo $output . "\n";
echo "=== DONE ===\n";
echo "</pre>";
