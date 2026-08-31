<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomestaySurveyResponse;
use App\Services\AppSettingsService;
use App\Support\SimpleXlsxWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HomestaySurveyAdminController extends Controller
{
    public function __construct(
        private AppSettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filtersFrom($request);
        $responses = $this->filteredQuery($filters)
            ->orderByDesc('submitted_at')
            ->paginate(40)
            ->withQueryString();

        $districts = HomestaySurveyResponse::query()
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->distinct()
            ->orderBy('district')
            ->pluck('district');

        return view('admin.homestay-survey.index', [
            'responses' => $responses,
            'districts' => $districts,
            'filters' => $filters,
            'total' => HomestaySurveyResponse::query()->count(),
            'prefillLocked' => $this->settings->isEnabled('homestay_survey.prefill_locked'),
            'publicUrl' => url('/homestay-survey'),
        ]);
    }

    public function show(HomestaySurveyResponse $homestaySurvey): View
    {
        return view('admin.homestay-survey.show', [
            'response' => $homestaySurvey,
            'options' => config('homestay_survey'),
        ]);
    }

    public function updatePrefillLock(Request $request): RedirectResponse
    {
        $locked = $request->boolean('prefill_locked');
        $this->settings->setMany([
            'homestay_survey.prefill_locked' => $locked,
        ], auth()->id());

        return back()->with('status', $locked
            ? 'Prefill fields are now locked on the public survey form.'
            : 'Prefill fields are now editable on the public survey form.');
    }

    public function export(Request $request): BinaryFileResponse
    {
        $rows = $this->filteredQuery($this->filtersFrom($request))->orderBy('id')->get();

        $header = [
            'ID', 'Submitted at', 'Phone', 'Phase', 'Application no', 'Applicant name', 'District',
            'Respondent name', 'Gender', 'Age group', 'Caste', 'Enterprise name',
            'Block', 'Village', 'Pin', 'Location type', 'Email', 'Website', 'Role',
            'Enrolment year', 'Info source', 'Incubation center', 'Venture type', 'Stage',
            'UTDB registered', 'UTDB number', 'Rooms', 'Homestay type', 'Facilities',
            'Peak season', 'Tariff', 'Investment', 'Funding sources',
            'MUY financial assistance', 'MUY amount', 'MUY year', 'Bank loan MUY', 'Loan amount', 'Interest subvention',
            'Revenue status', 'Occupancy %', 'Booking sources', 'Listed OTA', 'OTA platforms',
            'Tourism linkage', 'Employed during', 'Employed current', 'Women/Youth/Local during', 'Women/Youth/Local current',
            'Local sourcing', 'Encouraged others', 'Support services', 'Training usefulness', 'Follow-up',
            'Certification', 'Top challenges', 'COVID/disaster impact', 'Digital support', 'Digital comfort',
            'Progress rating', 'Income confidence', 'Recommend MUY', 'Expansion plans', 'Future support', 'MUY acceleration support services',
            'Consent',
        ];

        $data = [$header];
        foreach ($rows as $row) {
            $a = is_array($row->answers) ? $row->answers : [];
            $data[] = [
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
                $a['role'] ?? '',
                $a['enrolment_year'] ?? '',
                $this->join($a['info_source'] ?? null),
                $a['incubation_center'] ?? '',
                $a['venture_type'] ?? '',
                $a['stage_at_enrolment'] ?? '',
                $a['utdb_registered'] ?? '',
                $a['utdb_reg_number'] ?? '',
                $a['room_count'] ?? '',
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
                $a['acceleration_support'] ?? ($a['other_support'] ?? ''),
                ! empty($a['consent']) ? 'Yes' : 'No',
            ];
        }

        $tmp = storage_path('app/tmp/homestay-survey-'.now()->format('Ymd_His').'.xlsx');
        if (! is_dir(dirname($tmp))) {
            mkdir(dirname($tmp), 0755, true);
        }
        (new SimpleXlsxWriter)->addSheet('Responses', $data)->save($tmp);

        return response()->download($tmp, 'homestay-survey-responses-'.now()->format('Ymd_His').'.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * @return array{q: string, phase: string, district: string, acceleration: string}
     */
    private function filtersFrom(Request $request): array
    {
        $phase = trim((string) $request->query('phase', ''));
        if (! in_array($phase, ['Phase 1', 'Phase 2', 'Phase 3'], true)) {
            $phase = '';
        }

        $acceleration = trim((string) $request->query('acceleration', ''));
        if (! in_array($acceleration, ['Yes', 'No'], true)) {
            $acceleration = '';
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'phase' => $phase,
            'district' => trim((string) $request->query('district', '')),
            'acceleration' => $acceleration,
        ];
    }

    /**
     * @param  array{q: string, phase: string, district: string, acceleration: string}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = HomestaySurveyResponse::query();

        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $query->where(function ($w) use ($like): void {
                $w->where('phone', 'like', $like)
                    ->orWhere('applicant_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhere('district', 'like', $like);
            });
        }
        if ($filters['phase'] !== '') {
            $query->where('phase', $filters['phase']);
        }
        if ($filters['district'] !== '') {
            $query->where('district', $filters['district']);
        }
        if ($filters['acceleration'] !== '') {
            $value = $filters['acceleration'];
            $query->where(function ($w) use ($value): void {
                $w->where('answers->acceleration_support', $value)
                    ->orWhere('answers->other_support', $value);
            });
        }

        return $query;
    }

    private function join(mixed $value): string
    {
        if (is_array($value)) {
            return implode('; ', array_map(static fn ($v) => (string) $v, $value));
        }
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
