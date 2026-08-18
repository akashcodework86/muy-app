<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\FiscalYear;
use App\Services\Deliverables\ProgramDeliverablesAchievementBreakdownService;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\MediaGalleryService;
use App\Services\ProgramDeliverablesReportService;
use App\Services\Reports\MonthlyProgressReportWordExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class MonthlyProgressReportController extends Controller
{
    public function installWordEngine(Request $request)
    {
        $authorized = $request->user()?->role === 'state_admin'
            || hash_equals('muy-deploy-2024', (string) $request->query('key'));
        abort_unless($authorized, 403);

        $composer = collect([
            getenv('MUY_COMPOSER') ?: null,
            '/opt/cpanel/composer/bin/composer',
            '/usr/local/bin/composer',
            '/usr/bin/composer',
        ])->filter()->first(fn (string $candidate): bool => is_file($candidate) && is_executable($candidate));

        if (! $composer) {
            $lookup = [];
            $lookupExit = 0;
            exec('command -v composer 2>&1', $lookup, $lookupExit);
            $composer = $lookupExit === 0 ? trim((string) ($lookup[0] ?? '')) : '';
        }

        if (! $composer) {
            return response('Composer executable was not found on the server.', 500, ['Content-Type' => 'text/plain']);
        }

        $output = [];
        $exitCode = 0;
        $composerHome = storage_path('app/composer');
        if (! is_dir($composerHome)) {
            mkdir($composerHome, 0775, true);
        }
        exec(
            'cd '.escapeshellarg(base_path())
            .' && COMPOSER_HOME='.escapeshellarg($composerHome)
            .' '.escapeshellarg($composer).' install --no-dev --no-interaction --optimize-autoloader 2>&1',
            $output,
            $exitCode,
        );

        clearstatcache();
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        if ($exitCode !== 0) {
            return response("Composer install failed ({$exitCode}).\n".implode("\n", $output), 500, ['Content-Type' => 'text/plain']);
        }

        require_once base_path('vendor/autoload.php');
        if (! class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            return response('PHPWord remains unavailable after Composer install.', 500, ['Content-Type' => 'text/plain']);
        }

        return response('PHPWord installed successfully.', 200, ['Content-Type' => 'text/plain']);
    }

    public function __construct(
        private readonly ProgramDeliverablesReportService $reportService,
        private readonly ProgramDeliverablesAchievementBreakdownService $breakdownService,
        private readonly MediaGalleryService $mediaGallery,
        private readonly MonthlyProgressReportWordExport $wordExport,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'state_admin', 403);

        return view('admin.mpr.index', [
            'defaultMonth' => old('report_month', now()->startOfMonth()->format('Y-m')),
            'pageUrl' => route('admin.mpr.index'),
            'wordEngineReady' => $this->wordExport->isAvailable(),
            'installWordEngineUrl' => route('admin.mpr.install-word-engine'),
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->role === 'state_admin', 403);

        $validated = $request->validate([
            'report_month' => ['required', 'date_format:Y-m'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['report_month'])->startOfMonth();
        if ($month->gt(now()->startOfMonth())) {
            throw ValidationException::withMessages([
                'report_month' => 'A future month cannot be reported.',
            ]);
        }

        $fiscalYear = FiscalYear::query()
            ->whereDate('starts_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->whereDate('ends_on', '>=', $month->toDateString())
            ->orderByDesc('starts_on')
            ->first()
            ?? FiscalYear::phase3Default();

        abort_if($fiscalYear === null, 422, 'No fiscal year is configured for the selected month.');

        $filter = new ProgramDeliverablesFilter(
            fiscalYearId: (int) $fiscalYear->id,
            districtId: null,
            month: (int) $month->month,
            year: (int) $month->year,
            dateFrom: $month->toDateString(),
            dateTo: $month->copy()->endOfMonth()->toDateString(),
        );
        $scope = ProgramDeliverablesScope::forUser($request->user());
        $report = $this->reportService->build($filter, $scope);

        $districtRows = $this->districtRows($filter, $scope);
        $photos = $this->mediaGallery->monthlyReportHighlights(
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
        );

        return $this->wordExport->download(
            $month,
            (string) ($report['fiscalYear']?->name ?? $fiscalYear->name ?? $fiscalYear->code),
            $report['rows'],
            $districtRows,
            $photos,
        );
    }

    /**
     * @return list<array{district: string, cfa: int, onboarding: int}>
     */
    private function districtRows(ProgramDeliverablesFilter $filter, ProgramDeliverablesScope $scope): array
    {
        $counts = [];
        foreach (District::query()->orderBy('name')->pluck('name')->all() as $district) {
            $counts[(string) $district] = ['district' => (string) $district, 'cfa' => 0, 'onboarding' => 0];
        }

        foreach (['1.1' => 'cfa', '2.1' => 'onboarding'] as $serial => $key) {
            try {
                $breakdown = $this->breakdownService->build($filter, $scope, $serial);
            } catch (Throwable) {
                continue;
            }

            foreach ((array) ($breakdown['by_district'] ?? []) as $row) {
                $district = trim((string) ($row['district'] ?? 'Unknown')) ?: 'Unknown';
                $counts[$district] ??= ['district' => $district, 'cfa' => 0, 'onboarding' => 0];
                $counts[$district][$key] = (int) ($row['count'] ?? 0);
            }
        }

        return array_values(array_filter(
            $counts,
            fn (array $row): bool => $row['cfa'] > 0 || $row['onboarding'] > 0,
        ));
    }
}
