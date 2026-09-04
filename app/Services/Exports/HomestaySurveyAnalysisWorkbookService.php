<?php

namespace App\Services\Exports;

use App\Models\HomestaySurveyResponse;
use App\Support\SimpleXlsxWriter;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Multi-sheet Homestay Survey analysis workbook (SimpleXlsxWriter).
 */
final class HomestaySurveyAnalysisWorkbookService
{
    public const NOT_ANSWERED = 'Not answered';

    /** @var list<string> */
    public const SHEET_TITLES = [
        'Summary',
        'By district',
        'By phase',
        'Demographics',
        'Business',
        'Finance',
        'Employment & impact',
        'Training & digital',
        'Ratings & future',
        'Acceleration list',
        'Raw responses',
    ];

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  array{q?: string, phase?: string, district?: string, acceleration?: string}  $filters
     */
    public function download(Collection $responses, array $filters = []): BinaryFileResponse
    {
        $stamp = now()->format('Ymd_His');
        $fileName = 'homestay-survey-analysis-'.$stamp.'.xlsx';
        $path = storage_path('app/tmp/'.$fileName);
        $this->writeToPath($responses, $filters, $path);

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  array{q?: string, phase?: string, district?: string, acceleration?: string}  $filters
     */
    public function writeToPath(Collection $responses, array $filters, string $absolutePath): void
    {
        $writer = new SimpleXlsxWriter;
        foreach ($this->sheets($responses, $filters) as $title => $rows) {
            $writer->addSheet($title, $rows);
        }
        $writer->save($absolutePath);
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  array{q?: string, phase?: string, district?: string, acceleration?: string}  $filters
     * @return array<string, list<list<string|int|float|null>>>
     */
    public function sheets(Collection $responses, array $filters = []): array
    {
        $options = config('homestay_survey');
        if (! is_array($options)) {
            $options = [];
        }

        return [
            'Summary' => $this->summarySheet($responses, $filters, $options),
            'By district' => $this->kpiGroupSheet($responses, 'District', fn ($row, $a) => $this->districtOf($row, $a)),
            'By phase' => $this->phaseSheet($responses),
            'Demographics' => $this->demographicsSheet($responses, $options),
            'Business' => $this->businessSheet($responses, $options),
            'Finance' => $this->financeSheet($responses, $options),
            'Employment & impact' => $this->employmentSheet($responses, $options),
            'Training & digital' => $this->trainingSheet($responses, $options),
            'Ratings & future' => $this->ratingsSheet($responses, $options),
            'Acceleration list' => $this->accelerationListSheet($responses),
            'Raw responses' => $this->rawSheet($responses),
        ];
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  array{q?: string, phase?: string, district?: string, acceleration?: string}  $filters
     * @param  array<string, mixed>  $options
     * @return list<list<string|int|float|null>>
     */
    private function summarySheet(Collection $responses, array $filters, array $options): array
    {
        $total = $responses->count();
        $dates = $responses
            ->map(fn (HomestaySurveyResponse $r) => $r->submitted_at)
            ->filter()
            ->sort()
            ->values();
        $from = $dates->first();
        $to = $dates->last();
        $districts = $responses
            ->map(fn (HomestaySurveyResponse $r) => $this->districtOf($r, $this->answers($r)))
            ->filter(fn ($d) => $d !== self::NOT_ANSWERED)
            ->unique()
            ->count();

        $rows = [
            ['MUY Homestay Progress Survey — analysis'],
            ['Generated at', now()->timezone(config('app.timezone'))->format('d M Y, g:i A')],
            ['Filters', $this->filterLabel($filters)],
            ['Total responses', $total],
            ['Date range', $total === 0
                ? '—'
                : (optional($from)->format('Y-m-d H:i').' to '.optional($to)->format('Y-m-d H:i'))],
            ['Districts with responses', $districts],
            [],
        ];

        $rows = array_merge($rows, $this->titledFreq($responses, 'Phase', 'phase', ['Phase 1', 'Phase 2', 'Phase 3'], false, true));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Gender', 'gender', $options['genders'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Age group', 'age_group', $options['age_groups'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Category (caste)', 'caste', $options['castes'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Location type', 'location_type', $options['location_types'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Enrolment year', 'enrolment_year', $options['enrolment_years'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Venture type', 'venture_type', $options['venture_types'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Stage at enrolment', 'stage_at_enrolment', $options['stages'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'UTDB registered', 'utdb_registered', $options['yes_no_process'] ?? ['Yes', 'No', 'In process'], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'MUY financial assistance', 'muy_financial_assistance', ['Yes', 'No'], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Bank loan through MUY', 'bank_loan_muy', ['Yes', 'No'], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Acceleration support (Q53) — key KPI', 'acceleration', ['Yes', 'No'], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Occupancy band', 'occupancy_band', $options['occupancy_bands'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Listed on OTA', 'listed_ota', ['Yes', 'No'], false));

        $rows[] = ['Ratings (1–5)'];
        $rows[] = ['Metric', 'n scored', 'Mean', 'Mode'];
        foreach ([
            ['Progress since joining MUY', 'progress_rating'],
            ['Income & confidence', 'income_confidence'],
            ['Recommend MUY', 'recommend_muy'],
        ] as [$label, $key]) {
            $scores = $this->scores($responses, $key);
            $rows[] = [$label, count($scores), $this->mean($scores) ?? '—', $this->mode($scores) ?? '—'];
        }
        $rows[] = [];

        $rows[] = ['Acceleration Yes by district'];
        $rows[] = ['District', 'Yes n', 'Yes %', 'Total in district'];
        foreach ($this->accelerationCross($responses, fn ($row, $a) => $this->districtOf($row, $a)) as $line) {
            $rows[] = $line;
        }
        $rows[] = [];

        $rows[] = ['Acceleration Yes by phase'];
        $rows[] = ['Phase', 'Yes n', 'Yes %', 'Total in phase'];
        foreach ($this->accelerationCross($responses, fn ($row, $a) => $this->phaseOf($row)) as $line) {
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @return list<list<string|int|float|null>>
     */
    private function phaseSheet(Collection $responses): array
    {
        $order = ['Phase 1', 'Phase 2', 'Phase 3'];
        $kpis = $this->groupedKpis($responses, fn ($row, $a) => $this->phaseOf($row));
        $rows = [$this->kpiHeader('Phase')];
        foreach ($order as $phase) {
            $rows[] = $this->kpiRow($phase, $kpis[$phase] ?? $this->emptyKpi());
        }
        foreach ($kpis as $label => $kpi) {
            if (! in_array($label, $order, true)) {
                $rows[] = $this->kpiRow($label, $kpi);
            }
        }
        $rows[] = $this->kpiRow('All', $this->accumulateAll($responses));

        return $rows;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  callable(HomestaySurveyResponse, array<string, mixed>): string  $groupKey
     * @return list<list<string|int|float|null>>
     */
    private function kpiGroupSheet(Collection $responses, string $labelHeader, callable $groupKey): array
    {
        $kpis = $this->groupedKpis($responses, $groupKey);
        $rows = [$this->kpiHeader($labelHeader)];
        foreach ($kpis as $label => $kpi) {
            $rows[] = $this->kpiRow($label, $kpi);
        }
        $rows[] = $this->kpiRow('All', $this->accumulateAll($responses));

        return $rows;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  array<string, mixed>  $options
     * @return list<list<string|int|float|null>>
     */
    private function demographicsSheet(Collection $responses, array $options): array
    {
        $rows = [];
        $rows = array_merge($rows, $this->titledFreq($responses, 'Gender', 'gender', $options['genders'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Age group', 'age_group', $options['age_groups'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Category (caste)', 'caste', $options['castes'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Role', 'role', $options['roles'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Location type', 'location_type', $options['location_types'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Block (top 20)', 'block', [], false, false, 20));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Incubation center (top 30)', 'incubation_center', [], false, false, 30));

        return $rows;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  array<string, mixed>  $options
     * @return list<list<string|int|float|null>>
     */
    private function businessSheet(Collection $responses, array $options): array
    {
        $rows = [];
        $rows = array_merge($rows, $this->titledFreq($responses, 'Number of rooms', 'room_count', $options['room_counts'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Homestay type', 'homestay_type', $options['homestay_types'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Facilities (multi — respondents who selected)', 'facilities', $options['facilities'] ?? [], true));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Peak season', 'peak_season', $options['peak_seasons'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Tariff per night', 'tariff', $options['tariffs'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Booking sources (multi)', 'booking_sources', $options['booking_sources'] ?? [], true));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Listed on OTA', 'listed_ota', ['Yes', 'No'], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'OTA platforms (multi)', 'ota_platforms', $options['ota_platforms'] ?? [], true));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Tourism linkage', 'tourism_linkage', ['Yes', 'No'], false));

        return $rows;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  array<string, mixed>  $options
     * @return list<list<string|int|float|null>>
     */
    private function financeSheet(Collection $responses, array $options): array
    {
        $rows = [];
        $rows = array_merge($rows, $this->titledFreq($responses, 'Funding sources (multi)', 'funding_sources', $options['funding_sources'] ?? [], true));
        $rows = array_merge($rows, $this->titledFreq($responses, 'MUY financial assistance', 'muy_financial_assistance', ['Yes', 'No'], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Bank loan through MUY', 'bank_loan_muy', ['Yes', 'No'], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Interest subvention', 'interest_subvention', ['Yes', 'No'], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Revenue vs first year', 'revenue_status', $options['revenue_status'] ?? [], false));

        $rows[] = ['Numeric fields (parsed from free text)'];
        $rows[] = ['Field', 'n with value', 'Mean', 'Median', 'Min', 'Max'];
        foreach ([
            ['Initial investment (₹)', 'initial_investment'],
            ['MUY amount (₹)', 'muy_financial_amount'],
            ['Bank loan amount (₹)', 'bank_loan_amount'],
            ['Revenue during MUY (₹/month)', 'revenue_during'],
            ['Revenue current (₹/month)', 'revenue_current'],
            ['Guests / year during MUY', 'guests_during'],
            ['Guests / year current', 'guests_current'],
        ] as [$label, $key]) {
            $nums = $this->numbers($responses, $key);
            $rows[] = $this->numericRow($label, $nums);
        }
        $rows[] = [];

        $rows[] = ['Other income sources (Q31.5) — ₹ / month'];
        $rows[] = ['Source', 'n with value', 'Mean', 'Median', 'Min', 'Max'];
        $bySource = $this->otherIncomeBySource($responses, $options['other_income_sources'] ?? []);
        $any = false;
        foreach ($bySource as $source => $nums) {
            if ($nums === [] && $source === '(unlabelled amounts)') {
                continue;
            }
            $any = true;
            $rows[] = $this->numericRow($source, $nums);
        }
        if (! $any) {
            $rows[] = ['No parseable other-income amounts', 0, '—', '—', '—', '—'];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  array<string, mixed>  $options
     * @return list<list<string|int|float|null>>
     */
    private function employmentSheet(Collection $responses, array $options): array
    {
        $bands = $options['employment_bands'] ?? ['1', '2–3', '4–6', '7–10', '>10'];
        $rows = [];
        $rows = array_merge($rows, $this->titledFreq($responses, 'People employed during MUY', 'employed_during', $bands, false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'People employed currently', 'employed_current', $bands, false));

        $rows[] = ['Women / youth / local (numeric where parseable)'];
        $rows[] = ['Field', 'n with value', 'Mean', 'Median', 'Min', 'Max'];
        foreach ([
            ['Women during incubation', 'women_during'],
            ['Youth during incubation', 'youth_during'],
            ['Local villagers during incubation', 'local_during'],
            ['Women current', 'women_current'],
            ['Youth current', 'youth_current'],
            ['Local villagers current', 'local_current'],
        ] as [$label, $key]) {
            $rows[] = $this->numericRow($label, $this->numbers($responses, $key));
        }
        $rows[] = [];

        $rows = array_merge($rows, $this->titledFreq($responses, 'Local sourcing (multi)', 'local_sourcing', $options['local_sourcing'] ?? [], true));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Encouraged others in the village', 'encouraged_others', $options['yes_no_unsure'] ?? ['Yes', 'No', 'Not sure'], false));

        return $rows;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  array<string, mixed>  $options
     * @return list<list<string|int|float|null>>
     */
    private function trainingSheet(Collection $responses, array $options): array
    {
        $rows = [];
        $rows = array_merge($rows, $this->titledFreq($responses, 'Support services received (multi)', 'support_services', $options['support_services'] ?? [], true));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Training usefulness', 'training_usefulness', $options['usefulness'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Follow-up frequency', 'followup_frequency', $options['followup_frequency'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Certification / quality grading help', 'certification', ['Yes', 'No'], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Digital support from MUY (multi)', 'digital_support', $options['digital_support'] ?? [], true));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Digital comfort', 'digital_comfort', $options['digital_comfort'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'COVID / disaster impact', 'covid_impact', ['Yes', 'No'], false));

        return $rows;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  array<string, mixed>  $options
     * @return list<list<string|int|float|null>>
     */
    private function ratingsSheet(Collection $responses, array $options): array
    {
        $rows = [];
        $rows = array_merge($rows, $this->titledFreq($responses, 'Progress rating', 'progress_rating', $options['progress_rating'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Income & entrepreneurial confidence', 'income_confidence', $options['agree_rating'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Recommend MUY (1–5)', 'recommend_muy', $options['recommend_rating'] ?? [], false));
        $rows = array_merge($rows, $this->titledFreq($responses, 'Expansion plans (multi)', 'expansion_plans', $options['expansion_plans'] ?? [], true));

        $rows[] = ['Top challenges (Q44) — rank 1 / 2 / 3'];
        $rows[] = ['Challenge', 'Ranked 1', 'Ranked 2', 'Ranked 3', 'Any top-3', 'Any top-3 %'];
        $challenge = $this->rankCounts($responses, 'challenge_ranks', $options['challenge_rank_options'] ?? []);
        $total = $responses->count();
        foreach ($challenge['counts'] as $label => $c) {
            $rows[] = [$label, $c[1], $c[2], $c[3], $c['any'], $this->pct($c['any'], $total)];
        }
        if ($challenge['unlabelled'][1] + $challenge['unlabelled'][2] + $challenge['unlabelled'][3] > 0) {
            $u = $challenge['unlabelled'];
            $rows[] = ['(rank stored without challenge label)', $u[1], $u[2], $u[3], $u[1] + $u[2] + $u[3], '—'];
        }
        $rows[] = ['Respondents who entered any challenge rank', $challenge['respondents'], $this->pct($challenge['respondents'], $total)];
        $rows[] = [];

        $rows[] = ['Further support needed (Q52) — rank 1 / 2 / 3'];
        $rows[] = ['Support', 'Ranked 1', 'Ranked 2', 'Ranked 3', 'Any top-3', 'Any top-3 %'];
        $future = $this->rankCounts($responses, 'future_support', $options['future_support'] ?? []);
        foreach ($future['counts'] as $label => $c) {
            $rows[] = [$label, $c[1], $c[2], $c[3], $c['any'], $this->pct($c['any'], $total)];
        }
        if ($future['unlabelled'][1] + $future['unlabelled'][2] + $future['unlabelled'][3] > 0) {
            $u = $future['unlabelled'];
            $rows[] = ['(rank stored without support label)', $u[1], $u[2], $u[3], $u[1] + $u[2] + $u[3], '—'];
        }
        $rows[] = ['Respondents who entered any future-support rank', $future['respondents'], $this->pct($future['respondents'], $total)];
        $rows[] = [];

        $rows = array_merge($rows, $this->titledFreq($responses, 'Willing to take MUY acceleration support (Q53)', 'acceleration', ['Yes', 'No'], false));

        return $rows;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @return list<list<string|int|float|null>>
     */
    private function accelerationListSheet(Collection $responses): array
    {
        $rows = [[
            'Name', 'Phone', 'District', 'Phase', 'App no', 'Enterprise', 'Block',
            'UTDB', 'Rooms', 'Occupancy', 'OTA listed', 'Progress rating', 'Submitted at',
        ]];
        foreach ($responses as $row) {
            $a = $this->answers($row);
            if ($this->accelerationOf($a) !== 'Yes') {
                continue;
            }
            $rows[] = [
                $row->applicant_name ?: $this->scalar($a, 'respondent_name'),
                $row->phone,
                $this->districtOf($row, $a),
                $this->phaseOf($row),
                (string) ($row->application_no ?? ''),
                $this->scalar($a, 'enterprise_name'),
                $this->scalar($a, 'block'),
                $this->scalar($a, 'utdb_registered'),
                trim($this->scalar($a, 'room_count').' '.$this->scalar($a, 'room_count_other')),
                $this->scalar($a, 'occupancy_band'),
                $this->scalar($a, 'listed_ota'),
                $this->scalar($a, 'progress_rating'),
                optional($row->submitted_at)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            ];
        }
        if (count($rows) === 1) {
            $rows[] = ['No respondents selected Yes for MUY acceleration support'];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @return list<list<string|int|float|null>>
     */
    private function rawSheet(Collection $responses): array
    {
        $rows = [$this->rawHeader()];
        foreach ($responses as $row) {
            $rows[] = $this->rawRow($row);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function rawHeader(): array
    {
        return [
            'ID', 'Submitted at', 'Phone', 'Phase', 'Application no', 'Applicant name', 'District',
            'Respondent name', 'Gender', 'Age group', 'Caste', 'Enterprise name',
            'Block', 'Village', 'Pin', 'Location type', 'Email', 'Website', 'Role',
            'Enrolment year', 'Info source', 'Incubation center', 'Venture type', 'Stage',
            'UTDB registered', 'UTDB number', 'Rooms', 'Homestay type', 'Facilities',
            'Peak season', 'Tariff', 'Investment', 'Funding sources',
            'MUY financial assistance', 'MUY amount', 'MUY year', 'Bank loan MUY', 'Loan amount', 'Interest subvention',
            'Revenue status',
            'Revenue during', 'Revenue current', 'Occupancy during', 'Occupancy current',
            'Guests during', 'Guests current', 'Employed Q31 during', 'Employed Q31 current', 'Other income',
            'Occupancy %', 'Booking sources', 'Listed OTA', 'OTA platforms',
            'Tourism linkage', 'Employed during', 'Employed current', 'Women/Youth/Local during', 'Women/Youth/Local current',
            'Local sourcing', 'Encouraged others', 'Support services', 'Training usefulness', 'Follow-up',
            'Certification', 'Top challenges', 'COVID/disaster impact', 'Digital support', 'Digital comfort',
            'Progress rating', 'Income confidence', 'Recommend MUY', 'Expansion plans', 'Future support', 'MUY acceleration support services',
            'Consent',
        ];
    }

    /**
     * @return list<string|int|float|null>
     */
    public function rawRow(HomestaySurveyResponse $row): array
    {
        $a = $this->answers($row);

        return [
            $row->id,
            optional($row->submitted_at)->format('Y-m-d H:i'),
            $row->phone,
            $row->phase,
            $row->application_no,
            $row->applicant_name,
            $row->district,
            $a['respondent_name'] ?? '',
            $a['gender'] ?? '',
            $a['age_group'] ?? '',
            $a['caste'] ?? '',
            $a['enterprise_name'] ?? '',
            $a['block'] ?? '',
            $a['village'] ?? '',
            $a['pincode'] ?? '',
            $a['location_type'] ?? '',
            $a['email'] ?? '',
            $a['website'] ?? '',
            trim(($a['role'] ?? '').' '.($a['role_other'] ?? '')),
            $a['enrolment_year'] ?? '',
            $this->join($a['info_source'] ?? null),
            $a['incubation_center'] ?? '',
            $a['venture_type'] ?? '',
            $a['stage_at_enrolment'] ?? '',
            $a['utdb_registered'] ?? '',
            $a['utdb_reg_number'] ?? '',
            trim(($a['room_count'] ?? '').' '.($a['room_count_other'] ?? '')),
            $a['homestay_type'] ?? '',
            $this->join($a['facilities'] ?? null),
            $a['peak_season'] ?? '',
            $a['tariff'] ?? '',
            $a['initial_investment'] ?? '',
            $this->join($a['funding_sources'] ?? null),
            $a['muy_financial_assistance'] ?? '',
            $a['muy_financial_amount'] ?? '',
            $a['muy_financial_year'] ?? '',
            $a['bank_loan_muy'] ?? '',
            $a['bank_loan_amount'] ?? '',
            $a['interest_subvention'] ?? '',
            $a['revenue_status'] ?? '',
            $a['revenue_during'] ?? '',
            $a['revenue_current'] ?? '',
            $a['occupancy_during'] ?? '',
            $a['occupancy_current'] ?? '',
            $a['guests_during'] ?? '',
            $a['guests_current'] ?? '',
            $a['employed_count_during_q31'] ?? '',
            $a['employed_count_current_q31'] ?? '',
            $this->join($a['other_income'] ?? null),
            $a['occupancy_band'] ?? '',
            $this->join($a['booking_sources'] ?? null),
            $a['listed_ota'] ?? '',
            $this->join($a['ota_platforms'] ?? null),
            $a['tourism_linkage'] ?? '',
            $a['employed_during'] ?? '',
            $a['employed_current'] ?? '',
            trim(($a['women_during'] ?? '').' / '.($a['youth_during'] ?? '').' / '.($a['local_during'] ?? '')),
            trim(($a['women_current'] ?? '').' / '.($a['youth_current'] ?? '').' / '.($a['local_current'] ?? '')),
            $this->join($a['local_sourcing'] ?? null),
            $a['encouraged_others'] ?? '',
            $this->join($a['support_services'] ?? null),
            $a['training_usefulness'] ?? '',
            $a['followup_frequency'] ?? '',
            trim(($a['certification'] ?? '').' '.($a['certification_detail'] ?? '')),
            $this->join($a['challenge_ranks'] ?? null),
            trim(($a['covid_impact'] ?? '').' '.($a['covid_recovery'] ?? '')),
            $this->join($a['digital_support'] ?? null),
            $a['digital_comfort'] ?? '',
            $a['progress_rating'] ?? '',
            $a['income_confidence'] ?? '',
            $a['recommend_muy'] ?? '',
            $this->join($a['expansion_plans'] ?? null),
            $this->join($a['future_support'] ?? null),
            $this->accelerationOf($a),
            ! empty($a['consent']) ? 'Yes' : 'No',
        ];
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  list<string>  $known
     * @return list<list<string|int|float|null>>
     */
    private function titledFreq(
        Collection $responses,
        string $title,
        string $key,
        array $known,
        bool $multi,
        bool $fromColumn = false,
        ?int $topN = null,
    ): array {
        $total = $responses->count();
        $counts = [];
        $blank = 0;
        foreach ($responses as $row) {
            $a = $this->answers($row);
            if ($multi) {
                $vals = $this->listOf($a, $key);
                if ($vals === []) {
                    $blank++;
                    continue;
                }
                foreach (array_unique($vals) as $v) {
                    $counts[$v] = ($counts[$v] ?? 0) + 1;
                }
            } else {
                $v = $fromColumn ? $this->columnValue($row, $key) : $this->fieldValue($a, $key);
                if ($v === '') {
                    $blank++;
                    continue;
                }
                $counts[$v] = ($counts[$v] ?? 0) + 1;
            }
        }

        $rows = [
            [$title.($multi ? '  (n = respondents who selected; % of all responses)' : '')],
            ['Option', 'n', '%'],
        ];

        $emitted = [];
        foreach ($known as $opt) {
            $n = $counts[$opt] ?? 0;
            $rows[] = [$opt, $n, $this->pct($n, $total)];
            $emitted[$opt] = true;
        }

        $extra = [];
        foreach ($counts as $opt => $n) {
            if (! isset($emitted[$opt])) {
                $extra[$opt] = $n;
            }
        }
        arsort($extra);
        if ($topN !== null && $known === []) {
            $i = 0;
            $other = 0;
            foreach ($extra as $opt => $n) {
                $i++;
                if ($i <= $topN) {
                    $rows[] = [$opt, $n, $this->pct($n, $total)];
                } else {
                    $other += $n;
                }
            }
            if ($other > 0) {
                $rows[] = ['Other', $other, $this->pct($other, $total)];
            }
        } else {
            foreach ($extra as $opt => $n) {
                $rows[] = [$opt, $n, $this->pct($n, $total)];
            }
        }

        $blankLabel = $multi ? 'None selected' : self::NOT_ANSWERED;
        $rows[] = [$blankLabel, $blank, $this->pct($blank, $total)];
        $rows[] = ['Total responses', $total, $this->pct($total, $total)];
        $rows[] = [];

        return $rows;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  callable(HomestaySurveyResponse, array<string, mixed>): string  $groupKey
     * @return list<list<string|int|float|null>>
     */
    private function accelerationCross(Collection $responses, callable $groupKey): array
    {
        $buckets = [];
        foreach ($responses as $row) {
            $a = $this->answers($row);
            $g = $groupKey($row, $a);
            if (! isset($buckets[$g])) {
                $buckets[$g] = ['yes' => 0, 'total' => 0];
            }
            $buckets[$g]['total']++;
            if ($this->accelerationOf($a) === 'Yes') {
                $buckets[$g]['yes']++;
            }
        }
        ksort($buckets, SORT_NATURAL | SORT_FLAG_CASE);
        $lines = [];
        foreach ($buckets as $label => $b) {
            $lines[] = [$label, $b['yes'], $this->pct($b['yes'], $b['total']), $b['total']];
        }

        return $lines;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  callable(HomestaySurveyResponse, array<string, mixed>): string  $groupKey
     * @return array<string, array<string, int|float>>
     */
    private function groupedKpis(Collection $responses, callable $groupKey): array
    {
        $groups = [];
        foreach ($responses as $row) {
            $a = $this->answers($row);
            $key = $groupKey($row, $a);
            if (! isset($groups[$key])) {
                $groups[$key] = $this->emptyKpi();
            }
            $this->addKpi($groups[$key], $row, $a);
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        return $groups;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @return array<string, int|float>
     */
    private function accumulateAll(Collection $responses): array
    {
        $kpi = $this->emptyKpi();
        foreach ($responses as $row) {
            $this->addKpi($kpi, $row, $this->answers($row));
        }

        return $kpi;
    }

    /**
     * @return array<string, int|float>
     */
    private function emptyKpi(): array
    {
        return [
            'total' => 0,
            'p1' => 0,
            'p2' => 0,
            'p3' => 0,
            'male' => 0,
            'female' => 0,
            'utdb_yes' => 0,
            'muy_yes' => 0,
            'loan_yes' => 0,
            'ota_yes' => 0,
            'occ_high' => 0,
            'accel_yes' => 0,
            'progress_sum' => 0.0,
            'progress_n' => 0,
            'recommend_sum' => 0.0,
            'recommend_n' => 0,
        ];
    }

    /**
     * @param  array<string, int|float>  $kpi
     * @param  array<string, mixed>  $a
     */
    private function addKpi(array &$kpi, HomestaySurveyResponse $row, array $a): void
    {
        $kpi['total']++;
        $phase = $this->phaseOf($row);
        if ($phase === 'Phase 1') {
            $kpi['p1']++;
        } elseif ($phase === 'Phase 2') {
            $kpi['p2']++;
        } elseif ($phase === 'Phase 3') {
            $kpi['p3']++;
        }
        $gender = $this->scalar($a, 'gender');
        if ($gender === 'Male') {
            $kpi['male']++;
        } elseif ($gender === 'Female') {
            $kpi['female']++;
        }
        if ($this->scalar($a, 'utdb_registered') === 'Yes') {
            $kpi['utdb_yes']++;
        }
        if ($this->scalar($a, 'muy_financial_assistance') === 'Yes') {
            $kpi['muy_yes']++;
        }
        if ($this->scalar($a, 'bank_loan_muy') === 'Yes') {
            $kpi['loan_yes']++;
        }
        if ($this->scalar($a, 'listed_ota') === 'Yes') {
            $kpi['ota_yes']++;
        }
        if ($this->occupancyHigh($a)) {
            $kpi['occ_high']++;
        }
        if ($this->accelerationOf($a) === 'Yes') {
            $kpi['accel_yes']++;
        }
        $p = $this->ratingScore($a['progress_rating'] ?? null);
        if ($p !== null) {
            $kpi['progress_sum'] += $p;
            $kpi['progress_n']++;
        }
        $r = $this->ratingScore($a['recommend_muy'] ?? null);
        if ($r !== null) {
            $kpi['recommend_sum'] += $r;
            $kpi['recommend_n']++;
        }
    }

    /**
     * @return list<string>
     */
    private function kpiHeader(string $labelHeader): array
    {
        return [
            $labelHeader, 'Total', 'Phase 1', 'Phase 2', 'Phase 3',
            'Male', 'Male %', 'Female', 'Female %',
            'UTDB Yes', 'UTDB Yes %',
            'MUY finance Yes', 'MUY finance Yes %',
            'Bank loan Yes', 'Bank loan Yes %',
            'OTA listed Yes', 'OTA listed Yes %',
            'Occupancy >60%', 'Occupancy >60% %',
            'Acceleration Yes', 'Acceleration Yes %',
            'Avg progress (1–5)', 'Avg recommend (1–5)',
        ];
    }

    /**
     * @param  array<string, int|float>  $kpi
     * @return list<string|int|float|null>
     */
    private function kpiRow(string $label, array $kpi): array
    {
        $t = (int) $kpi['total'];

        return [
            $label,
            $t,
            (int) $kpi['p1'],
            (int) $kpi['p2'],
            (int) $kpi['p3'],
            (int) $kpi['male'],
            $this->pct((int) $kpi['male'], $t),
            (int) $kpi['female'],
            $this->pct((int) $kpi['female'], $t),
            (int) $kpi['utdb_yes'],
            $this->pct((int) $kpi['utdb_yes'], $t),
            (int) $kpi['muy_yes'],
            $this->pct((int) $kpi['muy_yes'], $t),
            (int) $kpi['loan_yes'],
            $this->pct((int) $kpi['loan_yes'], $t),
            (int) $kpi['ota_yes'],
            $this->pct((int) $kpi['ota_yes'], $t),
            (int) $kpi['occ_high'],
            $this->pct((int) $kpi['occ_high'], $t),
            (int) $kpi['accel_yes'],
            $this->pct((int) $kpi['accel_yes'], $t),
            $kpi['progress_n'] > 0 ? round($kpi['progress_sum'] / $kpi['progress_n'], 2) : '—',
            $kpi['recommend_n'] > 0 ? round($kpi['recommend_sum'] / $kpi['recommend_n'], 2) : '—',
        ];
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @return list<int>
     */
    private function scores(Collection $responses, string $key): array
    {
        $out = [];
        foreach ($responses as $row) {
            $score = $this->ratingScore($this->answers($row)[$key] ?? null);
            if ($score !== null) {
                $out[] = $score;
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @return list<float>
     */
    private function numbers(Collection $responses, string $key): array
    {
        $out = [];
        foreach ($responses as $row) {
            $n = $this->parseNumber($this->answers($row)[$key] ?? null);
            if ($n !== null) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * @param  list<float>  $nums
     * @return list<string|int|float>
     */
    private function numericRow(string $label, array $nums): array
    {
        if ($nums === []) {
            return [$label, 0, '—', '—', '—', '—'];
        }

        return [$label, count($nums), $this->mean($nums) ?? '—', $this->median($nums) ?? '—', min($nums), max($nums)];
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  list<string>  $sources
     * @return array<string, list<float>>
     */
    private function otherIncomeBySource(Collection $responses, array $sources): array
    {
        $by = [];
        foreach ($sources as $src) {
            $by[$src] = [];
        }
        $by['(unlabelled amounts)'] = [];
        foreach ($responses as $row) {
            $v = $this->answers($row)['other_income'] ?? null;
            if (! is_array($v)) {
                continue;
            }
            foreach ($v as $k => $item) {
                $n = $this->parseNumber($item);
                if ($n === null) {
                    continue;
                }
                if (is_string($k) && $k !== '' && ! ctype_digit($k)) {
                    if (! isset($by[$k])) {
                        $by[$k] = [];
                    }
                    $by[$k][] = $n;
                } else {
                    $by['(unlabelled amounts)'][] = $n;
                }
            }
        }

        return $by;
    }

    /**
     * @param  Collection<int, HomestaySurveyResponse>  $responses
     * @param  list<string>  $options
     * @return array{counts: array<string, array{1: int, 2: int, 3: int, any: int}>, unlabelled: array{1: int, 2: int, 3: int}, respondents: int}
     */
    private function rankCounts(Collection $responses, string $key, array $options): array
    {
        $counts = [];
        foreach ($options as $opt) {
            $counts[$opt] = [1 => 0, 2 => 0, 3 => 0, 'any' => 0];
        }
        $unlabelled = [1 => 0, 2 => 0, 3 => 0];
        $respondents = 0;
        foreach ($responses as $row) {
            $map = $this->rankMap($this->answers($row), $key);
            if ($map === []) {
                continue;
            }
            $respondents++;
            $seen = [];
            foreach ($map as $label => $rank) {
                if (isset($counts[$label])) {
                    $counts[$label][$rank]++;
                    if (! isset($seen[$label])) {
                        $counts[$label]['any']++;
                        $seen[$label] = true;
                    }
                } else {
                    $unlabelled[$rank]++;
                }
            }
        }

        return ['counts' => $counts, 'unlabelled' => $unlabelled, 'respondents' => $respondents];
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array<string, int>
     */
    private function rankMap(array $a, string $key): array
    {
        $v = $a[$key] ?? null;
        if (! is_array($v)) {
            return [];
        }
        $out = [];
        $list = array_is_list($v);
        foreach ($v as $k => $item) {
            if ($item === null || $item === '') {
                continue;
            }
            $rank = (int) $item;
            if ($rank < 1 || $rank > 3) {
                continue;
            }
            if ($list || is_int($k)) {
                $out['(unlabelled '.($k + 1).')'] = $rank;
            } else {
                $out[(string) $k] = $rank;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function occupancyHigh(array $a): bool
    {
        $b = $this->scalar($a, 'occupancy_band');

        return str_contains($b, '61%')
            || str_contains($b, 'more than 80%')
            || str_contains($b, 'Frequently occupied')
            || str_contains($b, 'Almost always occupied');
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function fieldValue(array $a, string $key): string
    {
        if ($key === 'acceleration') {
            return $this->accelerationOf($a);
        }

        return $this->scalar($a, $key);
    }

    private function columnValue(HomestaySurveyResponse $row, string $key): string
    {
        if ($key === 'phase') {
            return trim((string) ($row->phase ?? ''));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function districtOf(HomestaySurveyResponse $row, array $a): string
    {
        $d = trim((string) ($row->district ?? ''));
        if ($d === '') {
            $d = $this->scalar($a, 'district');
        }

        return $d !== '' ? $d : self::NOT_ANSWERED;
    }

    private function phaseOf(HomestaySurveyResponse $row): string
    {
        $p = trim((string) ($row->phase ?? ''));

        return $p !== '' ? $p : self::NOT_ANSWERED;
    }

    /**
     * @return array<string, mixed>
     */
    private function answers(HomestaySurveyResponse $row): array
    {
        return is_array($row->answers) ? $row->answers : [];
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function accelerationOf(array $a): string
    {
        $v = $a['acceleration_support'] ?? ($a['other_support'] ?? '');
        if (is_array($v)) {
            $v = implode('', array_map(static fn ($x) => is_scalar($x) ? (string) $x : '', $v));
        }

        return trim((string) $v);
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function scalar(array $a, string $key): string
    {
        $v = $a[$key] ?? '';
        if (is_array($v)) {
            $v = implode('; ', array_map(static fn ($x) => is_scalar($x) ? (string) $x : '', $v));
        }

        return trim((string) $v);
    }

    /**
     * @param  array<string, mixed>  $a
     * @return list<string>
     */
    private function listOf(array $a, string $key): array
    {
        $v = $a[$key] ?? [];
        if (! is_array($v)) {
            $v = ($v === null || $v === '') ? [] : [(string) $v];
        }
        $out = [];
        foreach ($v as $item) {
            if (is_scalar($item)) {
                $s = trim((string) $item);
                if ($s !== '') {
                    $out[] = $s;
                }
            }
        }

        return $out;
    }

    private function join(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $k => $item) {
                if (is_int($k)) {
                    if ($item !== null && $item !== '') {
                        $parts[] = (string) $item;
                    }
                } elseif ($item !== null && $item !== '') {
                    $parts[] = $k.': '.$item;
                }
            }

            return implode('; ', $parts);
        }
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function parseNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $s = trim($value);
        if ($s === '') {
            return null;
        }
        $s = str_replace([',', '₹', 'Rs.', 'Rs', ' '], '', $s);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    private function ratingScore(mixed $value): ?int
    {
        if (is_int($value) && $value >= 1 && $value <= 5) {
            return $value;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^([1-5])\b/u', $s, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @param  list<int|float>  $nums
     */
    private function mean(array $nums): ?float
    {
        if ($nums === []) {
            return null;
        }

        return round(array_sum($nums) / count($nums), 2);
    }

    /**
     * @param  list<int|float>  $nums
     */
    private function median(array $nums): ?float
    {
        if ($nums === []) {
            return null;
        }
        sort($nums, SORT_NUMERIC);
        $c = count($nums);
        $mid = intdiv($c, 2);
        if ($c % 2 === 1) {
            return round((float) $nums[$mid], 2);
        }

        return round(((float) $nums[$mid - 1] + (float) $nums[$mid]) / 2, 2);
    }

    /**
     * @param  list<int>  $nums
     */
    private function mode(array $nums): ?int
    {
        if ($nums === []) {
            return null;
        }
        $freq = array_count_values($nums);
        arsort($freq);
        $top = null;
        $topN = -1;
        foreach ($freq as $val => $n) {
            if ($n > $topN || ($n === $topN && $top !== null && (int) $val < $top)) {
                $topN = $n;
                $top = (int) $val;
            }
        }

        return $top;
    }

    private function pct(int $n, int $total): string
    {
        if ($total <= 0) {
            return '0%';
        }

        return round($n * 100 / $total, 1).'%';
    }

    /**
     * @param  array{q?: string, phase?: string, district?: string, acceleration?: string}  $filters
     */
    private function filterLabel(array $filters): string
    {
        $parts = [];
        if (($filters['q'] ?? '') !== '') {
            $parts[] = 'Search: '.$filters['q'];
        }
        if (($filters['phase'] ?? '') !== '') {
            $parts[] = 'Phase: '.$filters['phase'];
        }
        if (($filters['district'] ?? '') !== '') {
            $parts[] = 'District: '.$filters['district'];
        }
        if (($filters['acceleration'] ?? '') !== '') {
            $parts[] = 'Acceleration: '.$filters['acceleration'];
        }

        return $parts === [] ? 'None (all responses)' : implode(' | ', $parts);
    }
}
