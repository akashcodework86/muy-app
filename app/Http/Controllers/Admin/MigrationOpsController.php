<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MigrationOpsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MigrationOpsController extends Controller
{
    public function __construct(
        private readonly MigrationOpsService $migrationOps,
    ) {}

    public function index(): View
    {
        return view('admin.ops.migrations', [
            'pending' => $this->migrationOps->pendingMigrationNames(),
            'ranCount' => count($this->migrationOps->ranMigrationNames()),
            'moduleChecks' => $this->migrationOps->moduleHealthChecks(),
            'sqlBundles' => $this->migrationOps->downloadableSqlBundles(),
            'hasMigrationsTable' => \Illuminate\Support\Facades\Schema::hasTable('migrations'),
        ]);
    }

    public function run(Request $request): View
    {
        $request->validate([
            'confirm' => ['required', 'in:1'],
        ]);

        $result = $this->migrationOps->runPendingMigrations();

        return view('admin.ops.migrations', [
            'pending' => $this->migrationOps->pendingMigrationNames(),
            'ranCount' => count($this->migrationOps->ranMigrationNames()),
            'moduleChecks' => $this->migrationOps->moduleHealthChecks(),
            'sqlBundles' => $this->migrationOps->downloadableSqlBundles(),
            'hasMigrationsTable' => \Illuminate\Support\Facades\Schema::hasTable('migrations'),
            'runResult' => $result,
            'ranAt' => now(),
        ]);
    }

    public function downloadSql(string $bundle): BinaryFileResponse
    {
        $path = $this->migrationOps->sqlBundlePath($bundle);
        abort_unless($path !== null, 404, 'SQL bundle not found.');

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ]);
    }

    public function clearCache(): View
    {
        $result = $this->migrationOps->clearApplicationCaches();

        return view('admin.ops.migrations', [
            'pending' => $this->migrationOps->pendingMigrationNames(),
            'ranCount' => count($this->migrationOps->ranMigrationNames()),
            'moduleChecks' => $this->migrationOps->moduleHealthChecks(),
            'sqlBundles' => $this->migrationOps->downloadableSqlBundles(),
            'hasMigrationsTable' => \Illuminate\Support\Facades\Schema::hasTable('migrations'),
            'cacheResult' => $result,
            'cacheRanAt' => now(),
        ]);
    }
}
