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
        abort_unless($request->user()?->role === 'state_admin', 403);

        $composer = collect([
            getenv('MUY_COMPOSER') ?: null,
            '/opt/cpanel/composer/bin/composer',
            '/usr/local/bin/composer',
            '/usr/bin/composer',
        ])->filter()->first(fn (string $candidate): bool => is_file($candidate) && is_executable($candidate));

        abort_if(! $composer, 500, 'Composer executable was not found on the server.');

        $output = [];
        $exitCode = 0;
        exec(
            'cd '.escapeshellarg(base_path()).' && '.escapeshellarg($composer).' install --no-dev --no-interaction --optimize-autoloader 2>&1',
            $output,
            $exitCode,
        );

        clearstatcache();
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        abort_if($exitCode !== 0, 500, "Composer install failed.\n".implode("\n", $output));

        require_once base_path('vendor/autoload.php');
        abort_unless(class_exists(\PhpOffice\PhpWord\PhpWord::class), 500, 'PHPWord remains unavailable after Composer install.');

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
