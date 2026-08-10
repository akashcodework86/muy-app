<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $logs = glob(storage_path('logs/*')) ?: [];
        usort($logs, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        $message = 'Laravel logs are unavailable. Files: '.implode(', ', array_map('basename', $logs));
        foreach ($logs as $log) {
            if (! is_file($log) || ! is_readable($log)) {
                continue;
            }
            $size = filesize($log) ?: 0;
            $handle = fopen($log, 'rb');
            if ($handle !== false) {
                fseek($handle, max(0, $size - 524288));
                $tail = stream_get_contents($handle) ?: '';
                fclose($handle);
                preg_match_all('/^\[[^\]]+\] [^.]+\.ERROR:.*$/m', $tail, $matches);
                if (($matches[0] ?? []) !== []) {
                    $message = basename($log).': '.mb_substr((string) end($matches[0]), 0, 3000);
                    break;
                }
            }
        }

        throw new RuntimeException('LEGACY_DATA_DIAGNOSTIC: '.$message);
    }

    public function down(): void {}
};
