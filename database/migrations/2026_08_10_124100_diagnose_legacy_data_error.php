<?php

use Illuminate\Database\Migrations\Migration;
use App\Services\LegacyData\LegacyDataExplorerService;

return new class extends Migration
{
    public function up(): void
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);
        try {
            $filters = array_fill_keys([
                'fy', 'phase', 'district', 'service', 'service_status', 'category',
                'stage', 'gender', 'education', 'type', 'from', 'to', 'q',
            ], '');
            $filters['group'] = 'district';
            $data = app(LegacyDataExplorerService::class)->build($filters);
            throw new RuntimeException('LEGACY_DATA_BUILD_SUCCEEDED: '.$data['rows']->count().' rows.');
        } catch (Throwable $exception) {
            if (! str_starts_with($exception->getMessage(), 'LEGACY_DATA_BUILD_SUCCEEDED:')) {
                throw new RuntimeException('LEGACY_DATA_BUILD_FAILED: '.get_class($exception).': '.$exception->getMessage().' at '.$exception->getFile().':'.$exception->getLine());
            }
            $buildMessage = $exception->getMessage();
        }

        $logs = glob(storage_path('logs/*')) ?: [];
        usort($logs, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        $message = $buildMessage.'; Laravel logs are unavailable. Files: '.implode(', ', array_map('basename', $logs));
        foreach ($logs as $log) {
            if (! is_file($log) || ! is_readable($log)) {
                continue;
            }
            $size = filesize($log) ?: 0;
            $handle = fopen($log, 'rb');
            if ($handle !== false) {
                fseek($handle, max(0, $size - 10485760));
                $tail = stream_get_contents($handle) ?: '';
                fclose($handle);
                preg_match_all('/^\[[^\]]+\] [^.]+\.ERROR:.*$/m', $tail, $matches);
                if (($matches[0] ?? []) !== []) {
                    foreach (array_reverse($matches[0]) as $candidate) {
                        if (! str_contains($candidate, 'LEGACY_DATA_DIAGNOSTIC')) {
                            $message = basename($log).': '.mb_substr((string) $candidate, 0, 3000);
                            break 2;
                        }
                    }
                }
            }
        }

        throw new RuntimeException('LEGACY_DATA_DIAGNOSTIC: '.$message);
    }

    public function down(): void {}
};
