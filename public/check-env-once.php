<?php
/**
 * One-time diagnostic — DELETE THIS FILE after use!
 * Shows whether LEGACY_DB_* variables exist in .env
 */

$envFile = dirname(__DIR__) . '/.env';
$exists  = file_exists($envFile);
$lines   = $exists ? file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

$keys = ['LEGACY_DB_HOST','LEGACY_DB_PORT','LEGACY_DB_DATABASE','LEGACY_DB_USERNAME','LEGACY_DB_PASSWORD'];
$found = [];
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    foreach ($keys as $k) {
        if (str_starts_with($line, $k . '=')) {
            $val = explode('=', $line, 2)[1] ?? '';
            $found[$k] = strlen($val) > 0 ? '✅ set (' . strlen($val) . ' chars)' : '⚠️ empty';
        }
    }
}
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>ENV Check</title>
<style>
body{font-family:sans-serif;max-width:550px;margin:3rem auto;padding:1rem}
table{width:100%;border-collapse:collapse;font-size:0.9rem}
th,td{text-align:left;padding:8px 12px;border-bottom:1px solid #e2e8f0}
th{background:#f8fafc;font-size:0.75rem;text-transform:uppercase;color:#64748b}
.ok{color:#15803d}.miss{color:#dc2626;font-weight:600}
.warn{color:#b45309;font-size:0.8rem;margin-top:1.5rem}
</style></head>
<body>
<h2>ENV Diagnostic</h2>
<p style="color:#64748b;font-size:0.85rem">.env file: <strong><?= $exists ? '✅ found at ' . htmlspecialchars($envFile) : '❌ NOT FOUND' ?></strong></p>

<table>
<tr><th>Variable</th><th>Status</th></tr>
<?php foreach ($keys as $k): ?>
<tr>
    <td><code><?= $k ?></code></td>
    <td><?php if (isset($found[$k])): ?>
        <span class="ok"><?= htmlspecialchars($found[$k]) ?></span>
    <?php else: ?>
        <span class="miss">❌ NOT FOUND in .env</span>
    <?php endif; ?></td>
</tr>
<?php endforeach; ?>
</table>

<p class="warn">⚠️ <strong>Delete this file immediately after checking</strong> — it must not stay on the server.</p>
</body></html>
