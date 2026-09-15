<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Services\ReviewPpt\ReviewPptDataService;
use App\Services\ReviewPpt\ReviewPptTemplateExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReviewPptGeneratorController extends Controller
{
    public function __construct(
        private readonly ReviewPptDataService $dataService,
        private readonly ReviewPptTemplateExport $templateExport,
    ) {}

    public function index(): View
    {
        $fiscalYear = $this->fiscalYear();
        $months = [];
        $firstMonth = $fiscalYear->starts_on->copy()->startOfMonth();
        for ($index = 0; $index < 12; $index++) {
            $month = $firstMonth->copy()->addMonths($index);
            $months[$month->format('Y-m')] = $month->format('F Y');
        }
        $currentMonth = now()->startOfMonth();
        $defaultMonth = $currentMonth->betweenIncluded($firstMonth, $firstMonth->copy()->addMonths(11))
            ? $currentMonth->format('Y-m')
            : ($currentMonth->lt($firstMonth) ? $firstMonth->format('Y-m') : $firstMonth->copy()->addMonths(11)->format('Y-m'));

        $selectedMonth = Carbon::createFromFormat('!Y-m', $defaultMonth);
        $defaultDate = $selectedMonth->copy()->endOfMonth()->startOfDay();
        if ($defaultDate->gt(now()->startOfDay())) {
            $defaultDate = now()->startOfDay();
        }

        return view('admin.review-ppt.index', [
            'months' => $months,
            'defaultMonth' => $defaultMonth,
            'defaultDate' => $defaultDate->toDateString(),
            'fiscalYear' => $fiscalYear,
            'pageUrl' => route('admin.review-ppt.index'),
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $request->validate([
            'report_month' => ['required', 'date_format:Y-m'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $fiscalYear = $this->fiscalYear();
        $month = Carbon::createFromFormat('!Y-m', (string) $request->query('report_month'))->startOfMonth();
        $firstMonth = $fiscalYear->starts_on->copy()->startOfMonth();
        $monthIndex = (int) $firstMonth->diffInMonths($month) + 1;
        if ($month->lt($firstMonth) || $monthIndex < 1 || $monthIndex > 12) {
            throw ValidationException::withMessages(['report_month' => 'Choose a month in FY 2026–27.']);
        }
        $defaultAsOf = $month->copy()->endOfMonth()->startOfDay();
        if ($defaultAsOf->gt(now()->startOfDay())) {
            $defaultAsOf = now()->startOfDay();
        }
        $asOf = $request->filled('as_of')
            ? Carbon::parse((string) $request->query('as_of'))->startOfDay()
            : $defaultAsOf;
        if (! $asOf->isSameMonth($month) || $asOf->lt($fiscalYear->starts_on) || $asOf->gt(now()->startOfDay())) {
            throw ValidationException::withMessages(['as_of' => 'Choose a date in the selected month, up to today.']);
        }

        $quarter = intdiv($monthIndex - 1, 3) + 1;
        $targetThroughMonth = $quarter * 3;
        set_time_limit(240);
        $data = $this->dataService->build($fiscalYear, $asOf, $targetThroughMonth);
        $outputPath = tempnam(sys_get_temp_dir(), 'muy-review-');
        if ($outputPath === false) {
            abort(500, 'Could not create the review PowerPoint.');
        }
        try {
            $this->templateExport->write($data, $asOf, $quarter, $outputPath);
        } catch (\Throwable $e) {
            @unlink($outputPath);
            throw $e;
        }

        return response()->download($outputPath, 'MUY-Review-Q'.$quarter.'-'.$asOf->format('d-m-Y').'.pptx')
            ->deleteFileAfterSend(true);
    }

    private function fiscalYear(): FiscalYear
    {
        return FiscalYear::query()->where('code', '2026-27')->firstOrFail();
    }
}
