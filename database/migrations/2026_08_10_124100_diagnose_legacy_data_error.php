<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $log = storage_path('logs/laravel.log');
        $message = 'Laravel log is unavailable.';
        if (is_file($log) && is_readable($log)) {
            $size = filesize($log) ?: 0;
            $handle = fopen($log, 'rb');
            if ($handle !== false) {
                fseek($handle, max(0, $size - 524288));
                $tail = stream_get_contents($handle) ?: '';
                fclose($handle);
                preg_match_all('/^\[[^\]]+\] production\.ERROR:.*$/m', $tail, $matches);
                if (($matches[0] ?? []) !== []) {
                    $message = mb_substr((string) end($matches[0]), 0, 3000);
                }
            }
        }

        throw new RuntimeException('LEGACY_DATA_DIAGNOSTIC: '.$message);
    }

    public function down(): void {}
};
