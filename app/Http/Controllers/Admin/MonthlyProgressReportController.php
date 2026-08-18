<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Services\Reports\MonthlyProgressReportWordExport;
use App\Services\Reports\ProgressReportDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $verifyOutput = [];
        $verifyExit = 0;
        exec(
            'cd '.escapeshellarg(base_path())
            .' && php -r '.escapeshellarg('require "vendor/autoload.php"; exit(class_exists("PhpOffice\\PhpWord\\PhpWord") ? 0 : 1);')
            .' 2>&1',
            $verifyOutput,
            $verifyExit,
        );

        if ($verifyExit !== 0) {
            $diagnostics = $this->wordEngineDiagnostics();

            return response(
                "PHPWord remains unavailable after Composer install.\n\n"
                .$diagnostics."\n\n"
                ."Composer output (last 40 lines):\n"
                .implode("\n", array_slice($output, -40))."\n\n"
                ."If ext-gd is MISSING, enable it in cPanel → MultiPHP INI Editor, then run install again.\n"
                ."MPR download still works via compatible .doc fallback without PHPWord.",
                500,
                ['Content-Type' => 'text/plain'],
            );
        }

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return response(
            "PHPWord installed successfully.\n\n"
            .$this->wordEngineDiagnostics()
            ."\n\nRefresh the MPR page and download again for .docx output.",
            200,
            ['Content-Type' => 'text/plain'],
        );
    }

    private function wordEngineDiagnostics(): string
    {
        $lines = [
            'Project: '.base_path(),
            'PHP (web): '.PHP_VERSION,
        ];

        foreach (['gd', 'zip', 'dom', 'xml', 'json'] as $extension) {
            $lines[] = 'ext-'.$extension.': '.(extension_loaded($extension) ? 'loaded' : 'MISSING');
        }

        $lines[] = 'vendor/autoload.php: '.(is_file(base_path('vendor/autoload.php')) ? 'present' : 'MISSING');
        $lines[] = 'vendor/phpoffice/phpword: '.(is_dir(base_path('vendor/phpoffice/phpword')) ? 'present' : 'MISSING');

        return implode("\n", $lines);
    }

    public function __construct(
        private readonly ProgressReportDataService $reportData,
        private readonly MonthlyProgressReportWordExport $wordExport,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'state_admin', 403);

        $fiscalYears = FiscalYear::forUiDropdown();
        $anchorMonth = Carbon::createFromFormat('Y-m', old('report_month', now()->startOfMonth()->format('Y-m')));
        $fiscalYear = FiscalYear::query()
            ->whereDate('starts_on', '<=', $anchorMonth->copy()->endOfMonth()->toDateString())
            ->whereDate('ends_on', '>=', $anchorMonth->toDateString())
            ->orderByDesc('starts_on')
            ->first()
            ?? FiscalYear::phase3Default();

        $quarters = [];
        if ($fiscalYear !== null) {
            for ($q = 1; $q <= 4; $q++) {
                $quarters[$q] = $fiscalYear->fiscalQuarterLabel($q);
            }
        }

        return view('admin.mpr.index', [
            'defaultMonth' => old('report_month', now()->startOfMonth()->format('Y-m')),
            'defaultReportType' => old('report_type', 'mpr'),
            'defaultQuarter' => (int) old('report_quarter', 1),
            'fiscalYears' => $fiscalYears,
            'defaultFiscalYearId' => (int) ($fiscalYear?->id ?? 0),
            'quarters' => $quarters,
            'pageUrl' => route('admin.mpr.index'),
            'wordEngineReady' => $this->wordExport->isAvailable(),
            'installWordEngineUrl' => route('admin.mpr.install-word-engine'),
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->role === 'state_admin', 403);

        $validated = $request->validate([
            'report_type' => ['required', Rule::in(['mpr', 'qpr'])],
            'report_month' => ['required', 'date_format:Y-m'],
            'report_quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);

        $reportType = (string) $validated['report_type'];
        $month = Carbon::createFromFormat('Y-m', $validated['report_month'])->startOfMonth();

        if ($reportType === 'mpr') {
            if ($month->gt(now()->startOfMonth())) {
                throw ValidationException::withMessages([
                    'report_month' => 'A future month cannot be reported.',
                ]);
            }

            $context = $this->reportData->buildMonthly($request->user(), $month);
        } else {
            $fiscalYear = isset($validated['fiscal_year_id'])
                ? FiscalYear::query()->findOrFail((int) $validated['fiscal_year_id'])
                : FiscalYear::query()
                    ->whereDate('starts_on', '<=', $month->copy()->endOfMonth()->toDateString())
                    ->whereDate('ends_on', '>=', $month->toDateString())
                    ->orderByDesc('starts_on')
                    ->first()
                ?? FiscalYear::phase3Default();

            abort_if($fiscalYear === null, 422, 'No fiscal year is configured for the selected quarter.');

            $quarter = (int) ($validated['report_quarter'] ?? 1);
            $period = $fiscalYear->fiscalQuarterPeriod($quarter);
            abort_if($period === null, 422, 'Could not resolve the selected fiscal quarter.');

            [, $periodTo] = $period;
            if ($periodTo->gt(now()->endOfDay())) {
                throw ValidationException::withMessages([
                    'report_quarter' => 'A future quarter cannot be reported.',
                ]);
            }

            $context = $this->reportData->buildQuarterly($request->user(), $fiscalYear, $quarter);
        }

        return $this->wordExport->download($context);
    }
}
