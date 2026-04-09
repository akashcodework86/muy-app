<?php
/**
 * One-time cache clear helper — DELETE THIS FILE after use!
 */

// The public folder (phase3) is separate from the Laravel root (muy-app).
// Walk up from HOME to find muy-app/bootstrap/cache.
$home    = rtrim(getenv('HOME') ?: getenv('DOCUMENT_ROOT') ?: '', '/');
$appRoot = '';
foreach ([
    $home . '/muy-app',
    dirname($_SERVER['DOCUMENT_ROOT'] ?? '') . '/muy-app',
    dirname(dirname($_SERVER['DOCUMENT_ROOT'] ?? '')) . '/muy-app',
] as $candidate) {
    if (is_dir($candidate . '/bootstrap/cache')) {
        $appRoot = $candidate;
        break;
    }
}
if (!$appRoot) {
    die('<p style="color:red;font-family:sans-serif">❌ Could not locate muy-app directory. Tried: ' . htmlspecialchars($home . '/muy-app') . '</p>');
}

$deleted = [];
$failed  = [];

$targets = [
    $appRoot . '/bootstrap/cache/config.php',
    $appRoot . '/bootstrap/cache/routes-v7.php',
    $appRoot . '/bootstrap/cache/services.php',
    $appRoot . '/bootstrap/cache/packages.php',
    $appRoot . '/storage/framework/views',   // directory — handled below
];

// Delete cached config/route/service files
foreach (array_slice($targets, 0, 4) as $file) {
    if (file_exists($file)) {
        unlink($file) ? $deleted[] = basename($file) : $failed[] = basename($file);
    }
}

// Delete compiled Blade views
$viewsDir = $appRoot . '/storage/framework/views';
if (is_dir($viewsDir)) {
    foreach (glob($viewsDir . '/*.php') as $view) {
        unlink($view) ? $deleted[] = 'view:' . basename($view) : $failed[] = basename($view);
    }
}

?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cache Clear</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 3rem auto; padding: 1rem; }
        .ok  { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; color: #166534; }
        .err { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 1rem; color: #991b1b; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
        li   { margin: 4px 0; }
    </style>
</head>
<body>
    <h2>Cache Clear</h2>

    <?php if (!empty($deleted)): ?>
    <div class="ok">
        <strong>✅ Cleared (<?= count($deleted) ?> files):</strong>
        <ul>
            <?php foreach ($deleted as $f): ?>
                <li><code><?= htmlspecialchars($f) ?></code></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php else: ?>
    <div class="ok"><strong>✅ Nothing to clear</strong> — cache files were already absent.</div>
    <?php endif; ?>

    <?php if (!empty($failed)): ?>
    <div class="err">
        <strong>⚠️ Could not delete:</strong>
        <ul>
            <?php foreach ($failed as $f): ?>
                <li><code><?= htmlspecialchars($f) ?></code></li>
            <?php endforeach; ?>
        </ul>
        <p style="margin:0.5rem 0 0">Check file permissions (should be writable by the web server).</p>
    </div>
    <?php endif; ?>

    <p style="margin-top:2rem;color:#64748b;font-size:0.85rem;">
        ⚠️ <strong>Delete this file from your server now</strong> — it should not stay publicly accessible.
    </p>
</body>
</html>
