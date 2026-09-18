<?php

namespace App\Services\ReviewPpt;

use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewPptSelection
{
    /** @param list<string> $districtSlugs */
    public function __construct(
        public readonly Carbon $periodFrom,
        public readonly Carbon $periodTo,
        public readonly Carbon $achievementFrom,
        public readonly int $targetFromMonth,
        public readonly int $targetToMonth,
        public readonly array $districtSlugs,
        public readonly string $districtScope,
        public readonly string $countMode,
        public readonly string $periodKind,
        public readonly string $periodLabel,
    ) {}

    /** @param list<string>|null $allowedSlugs */
    public static function fromRequest(Request $request, FiscalYear $fiscalYear, ?array $allowedSlugs = null): self
    {
        $request->validate([
            'period_kind' => ['nullable', 'in:quarter,month,custom'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'report_month' => ['nullable', 'date_format:Y-m'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'count_mode' => ['nullable', 'in:period,cumulative'],
            'district_scope' => ['nullable', 'string', 'max:80'],
        ]);

        $fyStart = $fiscalYear->starts_on->copy()->startOfDay();
        $firstMonth = $fyStart->copy()->startOfMonth();
        $lastDay = $firstMonth->copy()->addMonths(11)->endOfMonth()->startOfDay();
        $today = now()->startOfDay();
        if ($lastDay->gt($today)) {
            $lastDay = $today;
        }
        $kind = (string) $request->query('period_kind', $request->filled('report_month') ? 'month' : 'quarter');
        $mode = (string) $request->query('count_mode', 'period');

        if ($kind === 'quarter') {
            $quarter = (int) $request->query('quarter', min(4, intdiv((int) $firstMonth->diffInMonths($today), 3) + 1));
            $from = $firstMonth->copy()->addMonths(($quarter - 1) * 3)->startOfDay();
            $to = $from->copy()->addMonths(3)->subDay()->startOfDay();
            $label = 'Q'.$quarter.' '.($mode === 'period' ? 'period' : 'FY cumulative');
        } elseif ($kind === 'month') {
            if (! $request->filled('report_month')) {
                throw ValidationException::withMessages(['report_month' => 'Choose a reporting month.']);
            }
            $from = Carbon::createFromFormat('!Y-m', (string) $request->query('report_month'))->startOfDay();
            $to = $from->copy()->endOfMonth()->startOfDay();
            $label = $from->format('M Y').' '.($mode === 'period' ? 'period' : 'FY cumulative');
            if ($request->filled('as_of')) {
                $to = Carbon::parse((string) $request->query('as_of'))->startOfDay();
                if (! $to->isSameMonth($from)) {
                    throw ValidationException::withMessages(['as_of' => 'Cut-off must be within the reporting month.']);
                }
            }
        } else {
            if (! $request->filled('date_from') || ! $request->filled('date_to')) {
                throw ValidationException::withMessages(['date_from' => 'Choose both From and To dates.']);
            }
            $from = Carbon::parse((string) $request->query('date_from'))->startOfDay();
            $to = Carbon::parse((string) $request->query('date_to'))->startOfDay();
            $label = $mode === 'period' ? 'Custom period' : 'FY cumulative';
        }

        if ($from->lt($fyStart)) {
            $from = $fyStart->copy();
        }
        if ($to->gt($lastDay)) {
            $to = $lastDay->copy();
        }
        if ($from->gt($to) || $from->lt($fyStart) || $from->gt($lastDay)) {
            throw ValidationException::withMessages(['date_from' => 'Choose dates within FY 2026-27, up to today.']);
        }
        if ($kind === 'custom' && ($request->query('date_from') < $fyStart->toDateString()
            || $request->query('date_to') > $lastDay->toDateString())) {
            throw ValidationException::withMessages(['date_to' => 'Custom dates must be within FY 2026-27, up to today.']);
        }

        $scope = (string) $request->query('district_scope', 'all');
        $kumaon = config('review_ppt.kumaon', []);
        $garhwal = config('review_ppt.garhwal', []);
        $pool = $allowedSlugs ?? array_merge($kumaon, $garhwal);
        $districtSlugs = match ($scope) {
            'all' => $pool,
            'kumaon' => array_values(array_intersect($kumaon, $pool)),
            'garhwal' => array_values(array_intersect($garhwal, $pool)),
            default => in_array($scope, $pool, true) ? [$scope] : [],
        };
        if ($districtSlugs === []) {
            throw ValidationException::withMessages(['district_scope' => 'Choose a valid Uttarakhand district or region.']);
        }

        $achievementFrom = $mode === 'cumulative' ? $fyStart->copy() : $from->copy();
        $targetFromMonth = $mode === 'cumulative' ? 1 : (int) $firstMonth->diffInMonths($from->copy()->startOfMonth()) + 1;
        $targetToMonth = (int) $firstMonth->diffInMonths($to->copy()->startOfMonth()) + 1;

        return new self($from, $to, $achievementFrom, $targetFromMonth, $targetToMonth,
            $districtSlugs, $scope, $mode, $kind, $label);
    }

    public function slideLabel(): string
    {
        return $this->periodLabel.' ('.$this->achievementFrom->format('d M').'–'.$this->periodTo->format('d M Y').')';
    }

    public function fileTag(): string
    {
        return ucfirst($this->periodKind).'-'.$this->districtScope.'-'.$this->countMode.'-'.$this->periodTo->format('Ymd');
    }
}
