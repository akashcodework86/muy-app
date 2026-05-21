<?php

namespace App\Services;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrationOpsService
{
    public function __construct(
        private readonly Migrator $migrator,
    ) {}

    /**
     * @return array{exit_code: int, output: string}
     */
    public function runPendingMigrations(): array
    {
        $exitCode = Artisan::call('migrate', ['--force' => true]);

        return [
            'exit_code' => $exitCode,
            'output' => trim((string) Artisan::output()),
        ];
    }

    /**
     * @return list<string>
     */
    public function pendingMigrationNames(): array
    {
        if (! Schema::hasTable('migrations')) {
            return array_keys($this->migrator->getMigrationFiles($this->migrationPath()));
        }

        $files = $this->migrator->getMigrationFiles($this->migrationPath());
        $ran = $this->migrator->getRepository()->getRan();

        return array_values(array_diff(array_keys($files), $ran));
    }

    /**
     * @return list<string>
     */
    public function ranMigrationNames(): array
    {
        if (! Schema::hasTable('migrations')) {
            return [];
        }

        return $this->migrator->getRepository()->getRan();
    }

    /**
     * @return list<array{key: string, label: string, ok: bool, detail: string}>
     */
    public function moduleHealthChecks(): array
    {
        return [
            $this->tableCheck(
                'social_media_posts',
                'Social media posts',
                ['posted_platforms', 'platform', 'thumbnail_url', 'preview_title'],
            ),
        ];
    }

    /**
     * @return list<array{slug: string, title: string, filename: string, path: string}>
     */
    public function downloadableSqlBundles(): array
    {
        $dir = database_path('sql');
        if (! is_dir($dir)) {
            return [];
        }

        $bundles = [];
        foreach (glob($dir.'/*.sql') ?: [] as $path) {
            $filename = basename($path);
            $slug = pathinfo($filename, PATHINFO_FILENAME);
            $bundles[] = [
                'slug' => $slug,
                'title' => str_replace(['-', '_'], ' ', $slug),
                'filename' => $filename,
                'path' => $path,
            ];
        }

        return $bundles;
    }

    public function sqlBundlePath(string $slug): ?string
    {
        $path = database_path('sql/'.$slug.'.sql');
        if (! is_file($path)) {
            return null;
        }

        $realBase = realpath(database_path('sql'));
        $realPath = realpath($path);
        if ($realBase === false || $realPath === false || ! str_starts_with($realPath, $realBase)) {
            return null;
        }

        return $realPath;
    }

    /**
     * @param  list<string>  $requiredColumns
     * @return array{key: string, label: string, ok: bool, detail: string}
     */
    private function tableCheck(string $table, string $label, array $requiredColumns): array
    {
        if (! Schema::hasTable($table)) {
            return [
                'key' => $table,
                'label' => $label,
                'ok' => false,
                'detail' => 'Table missing',
            ];
        }

        $missing = [];
        foreach ($requiredColumns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            return [
                'key' => $table,
                'label' => $label,
                'ok' => false,
                'detail' => 'Missing columns: '.implode(', ', $missing),
            ];
        }

        $count = DB::table($table)->count();

        return [
            'key' => $table,
            'label' => $label,
            'ok' => true,
            'detail' => 'Ready ('.number_format($count).' rows)',
        ];
    }

    private function migrationPath(): string
    {
        return database_path('migrations');
    }
}
