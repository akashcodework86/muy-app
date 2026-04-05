<?php

$secret = 'muy-deploy-2024';
if (! isset($_GET['key']) || $_GET['key'] !== $secret) {
    exit('❌ Unauthorized');
}

echo "<pre style='background:#111;color:#0f0;padding:20px;font-size:14px;'>";
echo "=== GIT INIT ON SERVER ===\n\n";

$projectPath = '/home/zwkk8e2xgz4h/muy-app';
$repoUrl = 'https://github.com/akashcodework86/muy-app.git';

// Remove old .git if exists
shell_exec("rm -rf $projectPath/.git 2>&1");
echo "✅ Cleaned old .git\n\n";

// Fresh git init
echo "--- git init ---\n";
echo shell_exec("cd $projectPath && /usr/bin/git init 2>&1")."\n";

// Add remote
echo "--- git remote add ---\n";
echo shell_exec("cd $projectPath && /usr/bin/git remote add origin $repoUrl 2>&1")."\n";

// Fetch
echo "--- git fetch ---\n";
echo shell_exec("cd $projectPath && /usr/bin/git fetch origin 2>&1")."\n";

// Reset hard to match GitHub (works even with existing files)
echo "--- git reset --hard origin/master ---\n";
echo shell_exec("cd $projectPath && /usr/bin/git reset --hard origin/master 2>&1")."\n";

// Set tracking branch
echo "--- set upstream ---\n";
echo shell_exec("cd $projectPath && /usr/bin/git branch --set-upstream-to=origin/master master 2>&1")."\n";

echo "\n✅ Git setup complete! Server is now linked to GitHub.\n";
echo "=== DONE ===\n";
echo '</pre>';
