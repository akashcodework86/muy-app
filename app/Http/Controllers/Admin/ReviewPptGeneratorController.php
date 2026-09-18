<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\ReviewPpt\ReviewPptDataService;
use App\Services\ReviewPpt\ReviewPptSelection;
use App\Services\ReviewPpt\ReviewPptTemplateExport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReviewPptGeneratorController extends Controller
{
    public function __construct(
        private readonly ReviewPptDataService $dataService,
        private readonly ReviewPptTemplateExport $templateExport,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $allowedSlugs = $this->allowedDistrictSlugs($user);
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
        $monthIndex = (int) $firstMonth->diffInMonths($selectedMonth) + 1;
        $defaultQuarter = intdiv($monthIndex - 1, 3) + 1;
        $defaultFrom = $firstMonth->copy()->addMonths(($defaultQuarter - 1) * 3)->startOfDay();
        if ($defaultFrom->lt($fiscalYear->starts_on)) {
            $defaultFrom = $fiscalYear->starts_on->copy()->startOfDay();
        }
        $districtNames = District::query()->whereIn('slug', $allowedSlugs)->pluck('name', 'slug');
        $routePrefix = $this->routePrefix($request);

        return view('admin.review-ppt.index', [
            'months' => $months,
            'defaultMonth' => $defaultMonth,
            'defaultDate' => $defaultDate->toDateString(),
            'defaultFrom' => $defaultFrom->toDateString(),
            'defaultQuarter' => $defaultQuarter,
            'districtNames' => $districtNames,
            'allowedSlugs' => $allowedSlugs,
            'fiscalYear' => $fiscalYear,
            'routePrefix' => $routePrefix,
            'pageUrl' => route($routePrefix.'.index'),
            'roleLabel' => match ($user?->role) {
                'hub_admin' => 'Hub Admin',
                'state_admin' => 'State Admin',
                default => 'Admin',
            },
            'scopeAllLabel' => count($allowedSlugs) === 13
                ? 'All 13 districts · statewide'
                : 'All hub districts ('.count($allowedSlugs).')',
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $fiscalYear = $this->fiscalYear();
        $selection = ReviewPptSelection::fromRequest($request, $fiscalYear, $this->allowedDistrictSlugs($request->user()));
        set_time_limit(240);
        $data = $this->reportData($fiscalYear, $selection);
        $outputPath = tempnam(sys_get_temp_dir(), 'muy-review-');
        if ($outputPath === false) {
            abort(500, 'Could not create the review PowerPoint.');
        }
        try {
            $this->templateExport->write($data, $selection, $outputPath);
        } catch (\Throwable $e) {
            @unlink($outputPath);
            throw $e;
        }

        return response()->download($outputPath, 'MUY-Review-'.$selection->fileTag().'.pptx')
            ->deleteFileAfterSend(true);
    }

    public function preview(Request $request): JsonResponse
    {
        $fiscalYear = $this->fiscalYear();
        $selection = ReviewPptSelection::fromRequest($request, $fiscalYear, $this->allowedDistrictSlugs($request->user()));
        set_time_limit(240);
        $data = $this->reportData($fiscalYear, $selection);
        $totals = [];
        foreach (['1.1' => 'Applications', '2.1' => 'Onboarded'] as $serial => $name) {
            $target = 0;
            $achievement = 0;
            foreach ($selection->districtSlugs as $slug) {
                $target += (int) ($data['targets'][$slug][$serial] ?? 0);
                $achievement += (int) ($data['achievements'][$slug][$serial] ?? 0);
            }
            $totals[] = ['name' => $name, 'target' => $target, 'achievement' => $achievement];
        }

        return response()->json([
            'scope' => $selection->districtScope,
            'achievement_from' => $selection->achievementFrom->toDateString(),
            'through' => $selection->periodTo->toDateString(),
            'target_months' => $selection->targetFromMonth.'–'.$selection->targetToMonth,
            'slides' => count($selection->districtSlugs) === 13 ? 5 : 3,
            'totals' => $totals,
        ]);
    }

    private function reportData(FiscalYear $fiscalYear, ReviewPptSelection $selection): array
    {
        $key = 'review_ppt_v2_'.hash('sha256', json_encode([
            (int) $fiscalYear->id, $selection->achievementFrom->toDateString(),
            $selection->periodTo->toDateString(), $selection->targetFromMonth,
            $selection->targetToMonth, $selection->districtSlugs,
        ], JSON_THROW_ON_ERROR));

        return Cache::remember($key, 300, fn () => $this->dataService->build($fiscalYear, $selection));
    }

    private function fiscalYear(): FiscalYear
    {
        return FiscalYear::query()->where('code', '2026-27')->firstOrFail();
    }

    private function routePrefix(Request $request): string
    {
        return $request->routeIs('hub.*') ? 'hub.review-ppt' : 'admin.review-ppt';
    }

    /** @return list<string> */
    private function allowedDistrictSlugs(?User $user): array
    {
        $all = array_values(array_merge(config('review_ppt.kumaon', []), config('review_ppt.garhwal', [])));
        if ($user?->role === 'state_admin') {
            return $all;
        }

        $hubSlugs = $user?->hub?->districts()->orderBy('sort_order')->pluck('slug')->all() ?? [];
        $slugs = array_values(array_intersect($all, $hubSlugs));
        abort_if($slugs === [], 403, 'No review districts are assigned to this hub.');

        return $slugs;
    }
}
