<?php

namespace App\Services;

use App\Models\District;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MisAssistantService
{
    private const MONTHS = [
        'january' => 1, 'jan' => 1, 'जनवरी' => 1,
        'february' => 2, 'feb' => 2, 'फरवरी' => 2,
        'march' => 3, 'mar' => 3, 'मार्च' => 3,
        'april' => 4, 'apr' => 4, 'अप्रैल' => 4,
        'may' => 5, 'मई' => 5,
        'june' => 6, 'jun' => 6, 'जून' => 6,
        'july' => 7, 'jul' => 7, 'जुलाई' => 7,
        'august' => 8, 'aug' => 8, 'अगस्त' => 8,
        'september' => 9, 'sep' => 9, 'सितंबर' => 9,
        'october' => 10, 'oct' => 10, 'अक्टूबर' => 10,
        'november' => 11, 'nov' => 11, 'नवंबर' => 11,
        'december' => 12, 'dec' => 12, 'दिसंबर' => 12,
    ];

    /** @return array<string, mixed> */
    public function answer(string $question): array
    {
        $normalized = $this->normalize($question);
        $context = $this->context($normalized);
        $intent = $this->intent($normalized);

        return match ($intent) {
            'cfa' => $this->cfaAnswer($normalized, $context),
            'onboarding' => $this->onboardingAnswer($normalized, $context),
            'service' => $this->serviceAnswer($normalized, $context),
            'market_linkage' => $this->marketLinkageAnswer($normalized, $context),
            'staff' => $this->staffAnswer($normalized, $context),
            default => $this->helpAnswer($context),
        };
    }

    private function normalize(string $question): string
    {
        return Str::of($question)
            ->lower()
            ->replace(['–', '—', '_'], ['-', '-', ' '])
            ->squish()
            ->toString();
    }

    private function intent(string $question): string
    {
        $scores = [
            'market_linkage' => $this->hits($question, ['market linkage', 'linkage', 'online market', 'offline market', 'linked incubatee']),
            'service' => $this->hits($question, ['service', 'approval', 'approved', 'pending approval', 'sent back', 'rejected', 'udbt', 'utdb', 'fssai', 'udyam', 'gst', 'artisan', 'mentorship']),
            'onboarding' => $this->hits($question, ['onboard', 'onboarding', 'incubatee', 'locked batch']),
            'staff' => $this->hits($question, ['staff', 'bpde', 'coordinator', 'spoc', 'submitted by']),
            'cfa' => $this->hits($question, ['cfa', 'form', 'forms', 'application', 'applications', 'registration', 'received', 'submit']),
        ];

        arsort($scores);
        $intent = (string) array_key_first($scores);

        return ($scores[$intent] ?? 0) > 0 ? $intent : 'unknown';
    }

    /** @param list<string> $needles */
    private function hits(string $question, array $needles): int
    {
        return collect($needles)->sum(fn (string $needle): int => Str::contains($question, $needle) ? 1 : 0);
    }

    /** @return array<string, mixed> */
    private function context(string $question): array
    {
        $district = District::query()->get(['id', 'name'])->sortByDesc(fn (District $item): int => mb_strlen($item->name))
            ->first(fn (District $item): bool => Str::contains($question, Str::lower($item->name)));

        $fyCode = null;
        if (preg_match('/\b(20\d{2})\s*[-\/]\s*(\d{2,4})\b/', $question, $match)) {
            $fyCode = $match[1].'-'.substr($match[2], -2);
        }

        $fy = $fyCode
            ? FiscalYear::query()->where('code', $fyCode)->first()
            : FiscalYear::phase3Default();

        if (Str::contains($question, ['all fy', 'all years', 'all time', 'till date total', 'total till date'])) {
            $fy = null;
        }

        [$from, $to] = $this->dateRange($question, $fy);

        return [
            'district_id' => $district?->id,
            'district_name' => $district?->name,
            'fiscal_year_id' => $fy?->id,
            'fiscal_year_code' => $fy?->code,
            'from' => $from,
            'to' => $to,
        ];
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function dateRange(string $question, ?FiscalYear $fy): array
    {
        if (Str::contains($question, ['today', 'aaj', 'आज'])) {
            return [now()->startOfDay(), now()->endOfDay()];
        }
        if (Str::contains($question, ['yesterday', 'kal', 'कल'])) {
            return [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()];
        }
        if (Str::contains($question, ['this month', 'current month', 'is month'])) {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }

        if ($fy && preg_match('/\bq(?:uarter)?\s*([1-4])\b/', $question, $quarterMatch)) {
            $period = $fy->fiscalQuarterPeriod((int) $quarterMatch[1]);
            if ($period) {
                return $period;
            }
        }

        $monthNumber = null;
        foreach (self::MONTHS as $name => $number) {
            if (preg_match('/(?<![a-z])'.preg_quote($name, '/').'(?![a-z])/', $question)) {
                $monthNumber = $number;
                break;
            }
        }

        $explicitYear = null;
        if (preg_match('/\b(20\d{2})\b/', $question, $yearMatch)) {
            $explicitYear = (int) $yearMatch[1];
        }

        if ($monthNumber !== null) {
            $year = $explicitYear ?: $this->yearForFiscalMonth($monthNumber, $fy);
            $month = Carbon::create($year, $monthNumber, 1);

            if (preg_match('/\b([0-3]?\d)\s*(?:st|nd|rd|th)?\s*(?:'.implode('|', array_map(fn ($m) => preg_quote($m, '/'), array_keys(self::MONTHS))).')\b/u', $question, $dayMatch)) {
                $day = min((int) $dayMatch[1], $month->daysInMonth);
                $from = $month->copy()->day($day)->startOfDay();
                $to = Str::contains($question, ['till date', 'to date', 'tak', 'से आज', 'से अब']) ? now()->endOfDay() : $from->copy()->endOfDay();

                return [$from, $to];
            }

            return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        }

        if ($fy?->starts_on && $fy?->ends_on) {
            return [Carbon::parse($fy->starts_on)->startOfDay(), Carbon::parse($fy->ends_on)->endOfDay()];
        }

        return [null, null];
    }

    private function yearForFiscalMonth(int $month, ?FiscalYear $fy): int
    {
        if (! $fy?->starts_on) {
            return now()->year;
        }

        $start = Carbon::parse($fy->starts_on);

        return $month >= $start->month ? $start->year : $start->year + 1;
    }

    /** @param array<string, mixed> $context */
    private function cfaAnswer(string $question, array $context): array
    {
        $query = DB::table('cfa_submissions as cs')->leftJoin('districts as d', 'd.id', '=', 'cs.district_id');
        $this->scope($query, $context, 'cs.created_at', 'cs.district_id', 'cs.fiscal_year_id');
        $total = (int) (clone $query)->count('cs.id');
        $group = $this->requestedGroup($question, ['district', 'date', 'staff', 'stage']);

        $rows = match ($group) {
            'district' => $this->groupRows($query, 'COALESCE(d.name, "Not specified")', 'district'),
            'date' => $this->groupRows($query, 'DATE(cs.created_at)', 'date'),
            'staff' => $this->cfaByStaff($query),
            'stage' => $this->groupRows($query, 'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, "$.form_stage")), "Not specified")', 'stage'),
            default => [],
        };

        return $this->response('cfa', 'CFA applications', $total, $rows, $context, route('admin.cfa.index', $this->cfaUrlFilters($context)), $group);
    }

    /** @param array<string, mixed> $context */
    private function onboardingAnswer(string $question, array $context): array
    {
        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'ob.district_id')
            ->where('ob.status', 'locked');
        $this->scope($query, $context, 'ob.locked_at', 'ob.district_id', 'cs.fiscal_year_id');
        $total = (int) (clone $query)->distinct()->count('obc.cfa_submission_id');
        $group = $this->requestedGroup($question, ['district', 'date', 'stage']);
        $rows = match ($group) {
            'district' => $this->groupRows($query, 'COALESCE(d.name, "Not specified")', 'district', 'obc.cfa_submission_id'),
            'date' => $this->groupRows($query, 'DATE(COALESCE(ob.onboarding_date, ob.locked_at))', 'date', 'obc.cfa_submission_id'),
            'stage' => $this->groupRows($query, 'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, "$.form_stage")), "Not specified")', 'stage', 'obc.cfa_submission_id'),
            default => [],
        };

        return $this->response('onboarding', 'Onboarded incubatees', $total, $rows, $context, route('admin.onboarded.index'), $group);
    }

    /** @param array<string, mixed> $context */
    private function serviceAnswer(string $question, array $context): array
    {
        $query = DB::table('service_cases as sc')
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id');
        $this->scope($query, $context, 'COALESCE(sc.submitted_at, sc.created_at)', 'cs.district_id', 'cs.fiscal_year_id');

        $status = $this->status($question);
        if ($status) {
            $query->where('sc.status', $status);
        }
        $service = DB::table('services')->get(['id', 'name', 'code'])->first(function ($row) use ($question): bool {
            $name = Str::lower((string) $row->name);
            $code = Str::lower((string) $row->code);
            if (($name !== '' && Str::contains($question, $name)) || ($code !== '' && Str::contains($question, $code))) {
                return true;
            }

            foreach (['utdb' => 'utdb', 'udbt' => 'utdb', 'udyam' => 'udyam', 'fssai' => 'fssai', 'gst' => 'gst', 'artisan' => 'artisan', 'trademark' => 'trademark', 'gi seller' => 'gi seller', 'mentorship' => 'mentorship'] as $asked => $catalogueKeyword) {
                if (Str::contains($question, $asked) && (Str::contains($name, $catalogueKeyword) || Str::contains($code, $catalogueKeyword))) {
                    return true;
                }
            }

            return false;
        });
        $serviceName = $service?->name;
        if ($service) {
            $query->where('sc.service_id', (int) $service->id);
        }

        $total = (int) (clone $query)->count('sc.id');
        $group = $this->requestedGroup($question, ['district', 'date', 'service', 'status']);
        $rows = match ($group) {
            'district' => $this->groupRows($query, 'COALESCE(d.name, "Not specified")', 'district'),
            'date' => $this->groupRows($query, 'DATE(COALESCE(sc.submitted_at, sc.created_at))', 'date'),
            'service' => $this->groupRows($query, 's.name', 'service'),
            'status' => $this->groupRows($query, 'sc.status', 'status'),
            default => [],
        };

        $filters = array_filter([
            'district_id' => $context['district_id'], 'status' => $status,
            'date_from' => $context['from']?->toDateString(), 'date_to' => $context['to']?->toDateString(),
        ]);

        return $this->response('service', $serviceName ? $serviceName.' service cases' : 'Service cases', $total, $rows, $context, route('admin.phase3-services.index', $filters), $group, $status);
    }

    /** @param array<string, mixed> $context */
    private function marketLinkageAnswer(string $question, array $context): array
    {
        $query = DB::table('market_linkage_submissions as ml')
            ->leftJoin('market_linkage_partners as mp', 'mp.market_linkage_submission_id', '=', 'ml.id')
            ->leftJoin('districts as d', 'd.id', '=', 'ml.district_id');
        $this->scope($query, $context, 'COALESCE(mp.linkage_date, ml.created_at)', 'ml.district_id', null);

        $status = $this->status($question);
        $query->where('ml.status', $status ?: 'approved');
        $mode = Str::contains($question, 'online') ? 'online' : (Str::contains($question, 'offline') ? 'offline' : null);
        if ($mode) {
            $query->where('mp.linkage_mode', $mode);
        }
        $total = (int) (clone $query)->distinct()->count('ml.id');
        $group = $this->requestedGroup($question, ['district', 'date', 'mode']);
        $rows = match ($group) {
            'district' => $this->groupRows($query, 'COALESCE(d.name, "Not specified")', 'district', 'ml.id'),
            'date' => $this->groupRows($query, 'DATE(COALESCE(mp.linkage_date, ml.created_at))', 'date', 'ml.id'),
            'mode' => $this->groupRows($query, 'COALESCE(mp.linkage_mode, "Not specified")', 'mode', 'ml.id'),
            default => [],
        };

        $filters = array_filter([
            'district_id' => $context['district_id'],
            'fiscal_year' => $context['fiscal_year_code'],
            'coverage' => $status === 'pending_approval' ? 'pending' : 'linked',
        ]);

        return $this->response('market_linkage', ucfirst($mode ?: 'Market').' linkages', $total, $rows, $context, route('admin.market-linkages.coverage.index', $filters), $group, $status ?: 'approved');
    }

    /** @param array<string, mixed> $context */
    private function staffAnswer(string $question, array $context): array
    {
        $query = DB::table('users as u')->leftJoin('districts as d', 'd.id', '=', 'u.district_id')->whereIn('u.role', ['district_staff', 'state_staff']);
        if ($context['district_id']) {
            $query->where('u.district_id', $context['district_id']);
        }
        if (Str::contains($question, ['active', 'working'])) {
            $query->where('u.is_active', true);
        }
        $total = (int) (clone $query)->count('u.id');
        $rows = $this->requestedGroup($question, ['district']) === 'district'
            ? $this->groupRows($query, 'COALESCE(d.name, "State / unassigned")', 'district') : [];

        return $this->response('staff', 'Staff members', $total, $rows, $context, route('admin.staff.index'), $rows ? 'district' : null);
    }

    private function requestedGroup(string $question, array $allowed): ?string
    {
        $cues = [
            'district' => ['district wise', 'district-wise', 'jile', 'district breakup', 'by district'],
            'date' => ['date wise', 'date-wise', 'daily', 'day wise', 'by date'],
            'staff' => ['staff wise', 'staff-wise', 'submitted by', 'by staff'],
            'stage' => ['stage wise', 'stage-wise', 'by stage'],
            'service' => ['service wise', 'service-wise', 'by service'],
            'status' => ['status wise', 'status-wise', 'by status'],
            'mode' => ['mode wise', 'mode-wise', 'online offline', 'by mode'],
        ];
        foreach ($allowed as $group) {
            if (Str::contains($question, $cues[$group] ?? [])) {
                return $group;
            }
        }

        return null;
    }

    private function status(string $question): ?string
    {
        return match (true) {
            Str::contains($question, ['pending approval', 'pending']) => 'pending_approval',
            Str::contains($question, ['sent back', 'send back']) => 'sent_back',
            Str::contains($question, ['rejected', 'reject']) => 'rejected',
            Str::contains($question, ['approved', 'approve']) => 'approved',
            Str::contains($question, ['draft']) => 'draft',
            default => null,
        };
    }

    /** @param array<string, mixed> $context */
    private function scope(Builder $query, array $context, string $dateColumn, string $districtColumn, ?string $fyColumn): void
    {
        if ($context['district_id']) {
            $query->where($districtColumn, $context['district_id']);
        }
        if ($fyColumn && $context['fiscal_year_id']) {
            $query->where($fyColumn, $context['fiscal_year_id']);
        }
        if ($context['from']) {
            $query->whereRaw($dateColumn.' >= ?', [$context['from']]);
        }
        if ($context['to']) {
            $query->whereRaw($dateColumn.' <= ?', [$context['to']]);
        }
    }

    /** @return list<array{label: string, count: int}> */
    private function groupRows(Builder $query, string $expression, string $alias, string $countColumn = '*'): array
    {
        $countSql = $countColumn === '*' ? 'COUNT(*)' : 'COUNT(DISTINCT '.$countColumn.')';

        return (clone $query)->selectRaw($expression.' as group_label, '.$countSql.' as total')
            ->groupBy(DB::raw($expression))->orderByDesc('total')->limit(50)->get()
            ->map(fn ($row) => ['label' => $this->pretty((string) ($row->group_label ?: 'Not specified'), $alias), 'count' => (int) $row->total])->all();
    }

    /** @return list<array{label: string, count: int}> */
    private function cfaByStaff(Builder $query): array
    {
        return (clone $query)->leftJoin('users as u', 'u.id', '=', 'cs.referral_user_id')
            ->selectRaw('COALESCE(u.name, "Not linked") as group_label, COUNT(cs.id) as total')
            ->groupBy(DB::raw('COALESCE(u.name, "Not linked")'))->orderByDesc('total')->limit(50)->get()
            ->map(fn ($row) => ['label' => (string) $row->group_label, 'count' => (int) $row->total])->all();
    }

    private function pretty(string $value, string $type): string
    {
        if ($type === 'date') {
            try {
                return Carbon::parse($value)->format('d M Y');
            } catch (\Throwable) {
                return $value;
            }
        }

        return Str::of($value)->replace('_', ' ')->title()->toString();
    }

    /** @param array<string, mixed> $context @param list<array{label: string, count: int}> $rows */
    private function response(string $intent, string $metric, int $total, array $rows, array $context, string $url, ?string $group, ?string $qualifier = null): array
    {
        $filters = $this->filterLabels($context);
        if ($qualifier) {
            $filters[] = Str::of($qualifier)->replace('_', ' ')->title()->toString();
        }
        $scope = $filters === [] ? 'selected MIS scope' : implode(' · ', $filters);

        return [
            'ok' => true,
            'intent' => $intent,
            'answer' => 'Found '.number_format($total).' '.$metric.' for '.$scope.'.',
            'metric' => $metric,
            'total' => $total,
            'rows' => $rows,
            'group_label' => $group ? 'Breakdown by '.Str::of($group)->replace('_', ' ')->title()->toString() : null,
            'filters' => $filters,
            'source_url' => $url,
            'source_label' => 'View matching MIS records',
            'note' => 'Live read-only MIS data. No record was changed.',
        ];
    }

    /** @param array<string, mixed> $context @return list<string> */
    private function filterLabels(array $context): array
    {
        return array_values(array_filter([
            $context['fiscal_year_code'] ? 'FY '.$context['fiscal_year_code'] : null,
            $context['district_name'],
            $context['from'] && $context['to'] ? $context['from']->format('d M Y').' – '.$context['to']->format('d M Y') : null,
        ]));
    }

    /** @param array<string, mixed> $context */
    private function cfaUrlFilters(array $context): array
    {
        return array_filter([
            'district_id' => $context['district_id'],
            'from' => $context['from']?->toDateString(),
            'to' => $context['to']?->toDateString(),
        ]);
    }

    /** @param array<string, mixed> $context */
    private function helpAnswer(array $context): array
    {
        return [
            'ok' => false,
            'intent' => 'unknown',
            'answer' => 'I could not identify a verified MIS source for this question. Please mention a CFA, onboarding, service, market-linkage or staff metric, along with the required period or district.',
            'metric' => null, 'total' => null, 'rows' => [], 'group_label' => null,
            'filters' => $this->filterLabels($context), 'source_url' => null, 'source_label' => null,
            'note' => 'I will not guess a value. Example: “Show the district-wise CFA count from 21 August to date.”',
        ];
    }
}
