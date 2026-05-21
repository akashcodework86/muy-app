<?php

namespace App\Services;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
                'Social media posts (database)',
                ['posted_platforms', 'platform', 'thumbnail_url', 'preview_title'],
            ),
            ...$this->deployHealthChecks(),
        ];
    }

    /**
     * @return array{exit_code: int, output: string, results: list<array{command: string, exit_code: int, output: string}>}
     */
    public function clearApplicationCaches(): array
    {
        $results = [];
        foreach (['optimize:clear', 'route:clear', 'view:clear', 'config:clear'] as $command) {
            try {
                $exitCode = Artisan::call($command);
                $results[] = [
                    'command' => $command,
                    'exit_code' => $exitCode,
                    'output' => trim((string) Artisan::output()),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'command' => $command,
                    'exit_code' => 1,
                    'output' => $e->getMessage(),
                ];
            }
        }

        $failed = collect($results)->contains(fn (array $row): bool => ($row['exit_code'] ?? 1) !== 0);

        return [
            'exit_code' => $failed ? 1 : 0,
            'output' => collect($results)
                ->map(fn (array $row): string => $row['command'].' (exit '.$row['exit_code'].')')
                ->implode("\n"),
            'results' => $results,
        ];
    }

    /**
     * @return list<array{key: string, label: string, ok: bool, detail: string}>
     */
    public function deployHealthChecks(): array
    {
        $controllerClass = \App\Http\Controllers\SocialMediaPostController::class;
        $controllerOk = class_exists($controllerClass);
        $indexOk = $controllerOk && method_exists($controllerClass, 'index');

        $checks = [
            $this->deployCheck(
                'social_media_controller',
                'PHP: SocialMediaPostController',
                $indexOk,
                $indexOk ? 'index() method present' : ($controllerOk ? 'Missing index() — re-upload controller file' : 'Class missing — upload app/Http/Controllers/SocialMediaPostController.php'),
            ),
        ];

        foreach ([
            'spoc.social-media-posts.index' => 'Route: spoc/social-media-posts',
            'spoc.social-media-posts.create' => 'Route: spoc/social-media-posts/create',
            'spoc.social-media-posts.preview' => 'Route: spoc/social-media-posts/preview',
        ] as $routeName => $label) {
            $checks[] = $this->deployCheck(
                $routeName,
                $label,
                Route::has($routeName),
                Route::has($routeName) ? 'Registered' : 'Missing — upload routes/web.php, then clear cache',
            );
        }

        foreach ([
            \App\Services\SocialMediaPostPreviewService::class => 'app/Services/SocialMediaPostPreviewService.php',
            \App\Support\SocialMediaPostPlatforms::class => 'app/Support/SocialMediaPostPlatforms.php',
            \App\Support\SocialMediaPostAccess::class => 'app/Support/SocialMediaPostAccess.php',
        ] as $class => $label) {
            $checks[] = $this->deployCheck(
                $class,
                'PHP: '.basename(str_replace('\\', '/', $class)),
                class_exists($class),
                class_exists($class) ? 'Loaded' : 'Missing — upload '.$label,
            );
        }

        $checks[] = $this->deployCheck(
            'view_smp_form',
            'View: social-media-posts/form.blade.php',
            is_file(resource_path('views/social-media-posts/form.blade.php')),
            is_file(resource_path('views/social-media-posts/form.blade.php')) ? 'Present' : 'Missing — upload resources/views/social-media-posts/',
        );

        $checks[] = $this->deployCheck(
            'config_smp',
            'Config: config/social_media_posts.php',
            is_file(config_path('social_media_posts.php')),
            is_file(config_path('social_media_posts.php')) ? 'Present' : 'Missing — upload config/social_media_posts.php',
        );

        return $checks;
    }

    /**
     * @return array{key: string, label: string, ok: bool, detail: string}
     */
    private function deployCheck(string $key, string $label, bool $ok, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'ok' => $ok,
            'detail' => $detail,
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
