<?php

declare(strict_types=1);

if (! hash_equals('muy-deploy-2024', (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

$root = dirname(__DIR__);
$log = $root.'/storage/logs/laravel.log';
$lastError = null;

if (is_file($log) && is_readable($log)) {
    $size = filesize($log) ?: 0;
    $handle = fopen($log, 'rb');
    if ($handle !== false) {
        fseek($handle, max(0, $size - 524288));
        $tail = stream_get_contents($handle) ?: '';
        fclose($handle);

        preg_match_all('/^\[[^\]]+\] production\.ERROR:.*$/m', $tail, $matches);
        if (($matches[0] ?? []) !== []) {
            $lastError = (string) end($matches[0]);
            $lastError = mb_substr($lastError, 0, 3000);
        }
    }
}

echo json_encode([
    'checked_at' => date(DATE_ATOM),
    'memory_limit' => ini_get('memory_limit'),
    'file_cache_writable' => is_writable($root.'/storage/framework/cache/data'),
    'log_readable' => is_readable($log),
    'last_error' => $lastError,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
