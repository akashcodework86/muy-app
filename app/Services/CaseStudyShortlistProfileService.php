<?php

namespace App\Services;

use App\Models\AccelerationServiceSession;
use App\Models\CaseStudyShortlist;
use App\Models\CaseStudyEntry;
use App\Models\CfaSubmission;
use App\Models\MarketLinkageSubmission;
use App\Models\PitchDeckPreparation;
use App\Models\ServiceCase;
use App\Models\TechnicalTraining;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CaseStudyShortlistProfileService
{
    public function __construct(
        private readonly LegacyPhase2IncubateeProfileService $phase2Profiles,
        private readonly LegacyPhase1ApplicationDetailService $phase1Details,
    ) {}

    /** @return array<string, mixed> */
    public function build(CaseStudyShortlist $shortlist): array
    {
        $sourceProfile = match ($shortlist->source) {
            'phase3' => $this->phase3Profile($shortlist),
            'phase2' => $this->phase2Profile($shortlist),
            'phase1' => $this->phase1Profile($shortlist),
            default => $this->fallbackProfile($shortlist),
        };
        $services = collect($sourceProfile['legacy_services'] ?? [])
            ->concat($this->currentServiceCases($shortlist))
            ->concat($this->pitchDeckRows($shortlist))
            ->concat($this->accelerationRows($shortlist))
            ->concat($this->technicalTrainingRows($shortlist))
            ->concat($this->caseStudyRows($shortlist))
            ->concat($this->marketLinkageRows($shortlist))
            ->unique(fn (array $row): string => mb_strtolower(implode('|', [
                $row['name'] ?? '', $row['date'] ?? '', $row['source'] ?? '', $row['detail'] ?? '',
            ])))
            ->sortByDesc(fn (array $row): string => (string) ($row['sort_date'] ?? $row['date'] ?? ''))
            ->values();

        $received = collect(array_keys((array) config('case_study_shortlists.nomination_services', [])))
            ->mapWithKeys(fn (string $code): array => [
                $code => $services->contains(fn (array $row): bool => ($row['service_code'] ?? '') === $code),
            ])->all();

        return $sourceProfile + ['services' => $services, 'received' => $received];
    }

    /** @return array<string, mixed> */
    private function phase3Profile(CaseStudyShortlist $shortlist): array
    {
        $cfa = CfaSubmission::query()
            ->with(['district:id,name', 'fiscalYear:id,code', 'onboardingBatchMembership.batch:id,name,onboarding_date,locked_at'])
            ->find($shortlist->source_application_id);
        if (! $cfa) {
            return $this->fallbackProfile($shortlist);
        }
        $p = is_array($cfa->payload) ? $cfa->payload : [];

        return [
            'identity' => $this->rows([
                'Applicant name' => $cfa->applicant_name, 'Application number' => $cfa->application_no,
                'Phone' => $cfa->phone, 'Gender' => $p['gender'] ?? null, 'Applicant type' => $p['category'] ?? null,
                'District' => $cfa->district?->name, 'Block' => $p['block'] ?? null,
                'Village' => $p['village'] ?? null, 'Caste/category' => $p['caste'] ?? null,
            ]),
            'enterprise' => $this->rows([
                'Enterprise / product' => $p['enterprise_name'] ?? $p['product'] ?? null,
                'Business category' => $p['business_category'] ?? null, 'Business stage' => $p['form_stage'] ?? null,
                'Business age' => $p['business_age'] ?? $p['years_in_business'] ?? null,
                'Location type' => $p['location_type'] ?? null, 'Registered' => $p['registered'] ?? null,
                'Registration type' => $p['registration_type'] ?? null, 'Annual turnover' => $p['turnover'] ?? $p['turnover_last_year'] ?? null,
                'Current employment' => $p['current_employment'] ?? null, 'People employed' => $p['employed_count'] ?? null,
                'Loan taken' => $p['loan_taken'] ?? null, 'Loan / support amount' => $p['bank_loan'] ?? $p['financial_support_amount'] ?? null,
            ]),
            'potential' => $this->rows([
                'Training received' => $p['training_received'] ?? $p['training'] ?? null,
                'Technology usage' => $p['techuse'] ?? null, 'Women/community impact' => $p['empwomen'] ?? null,
                'Sustainability practices' => $p['sustainability'] ?? null, 'Lakhpati status' => $p['lakhpati'] ?? null,
                'Regular buyers' => $p['regular_buyer'] ?? null, 'Challenges' => $p['challenges'] ?? null,
                'Expectations' => $p['expectations'] ?? null, 'Business vision' => $p['business_vision'] ?? null,
            ]),
            'journey' => $this->rows([
                'Programme year' => $cfa->fiscalYear?->code ?? $shortlist->program_year,
                'Source' => 'Phase 3', 'Onboarding batch' => $cfa->onboardingBatchMembership?->batch?->name,
                'Onboarding date' => $cfa->onboardingBatchMembership?->batch?->onboarding_date?->format('d M Y'),
                'Shortlisted in' => $shortlist->shortlist_month?->format('F Y'),
            ]),
            'legacy_services' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function phase2Profile(CaseStudyShortlist $shortlist): array
    {
        $profile = $this->phase2Profiles->loadProfile((int) $shortlist->source_application_id);
        if (! $profile) {
            return $this->fallbackProfile($shortlist);
        }
        $a = $profile['application']; $p = $profile['applicant']; $e = $profile['enterprise'];

        return [
            'identity' => $this->rows([
                'Applicant name' => $p['applicant_name'] ?? null, 'Application number' => $a['application_no'] ?? null,
                'Phone' => $p['phone'] ?? null, 'Gender' => $p['gender'] ?? null, 'Applicant type' => $a['category'] ?? null,
                'District' => $p['district'] ?? null, 'Block' => $p['block'] ?? null, 'Village' => $p['village'] ?? null,
                'Education' => $p['education'] ?? null, 'Caste/category' => $p['caste'] ?? null,
            ]),
            'enterprise' => $this->rows([
                'Enterprise name' => $e['enterprise_name'] ?? null, 'Product' => $a['product'] ?? null,
                'Business category' => $e['sector'] ?? $shortlist->business_category,
                'Business stage' => $a['form_stage'] ?? null, 'Years in business' => $e['years_in_business'] ?? null,
                'Location type' => $e['location_type'] ?? null, 'Registered' => $e['is_registered'] ?? null,
                'Registration type' => $e['registration_type'] ?? null, 'Annual turnover' => $e['turnover_last_year'] ?? null,
                'Team size' => $e['team_size'] ?? null,
            ]),
            'potential' => $this->rows([
                'Training received' => $e['training_received'] ?? null, 'Technology usage' => $p['techuse'] ?? null,
                'Women/community impact' => $p['empwomen'] ?? null, 'Lakhpati status' => $p['lakhpati'] ?? null,
                'Challenges' => $p['challenges'] ?? null, 'Expectations' => $p['expectations'] ?? null,
                'Support needed' => $e['support_needed'] ?? null, 'Migrated for employment' => $p['migrated_for_employment'] ?? null,
            ]),
            'journey' => $this->rows([
                'Programme year' => $shortlist->program_year, 'Source' => 'Phase 2 legacy',
                'Application date' => $a['submission_date'] ?? null, 'Shortlisted in' => $shortlist->shortlist_month?->format('F Y'),
            ]),
            'legacy_services' => collect($profile['services'] ?? [])->map(fn (array $row): array => [
                'name' => $row['service_name'] ?: $row['category'], 'date' => $row['assigned_on'] ?: 'Not available',
                'status' => 'Delivered', 'source' => 'Phase 2',
                'detail' => $row['partner_name'] ?: $row['category'], 'provider' => $row['served_by_name'] ?: null,
                'sort_date' => $row['assigned_on'] ?? '', 'service_code' => $this->serviceCode($row['service_name'] ?? ''),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function phase1Profile(CaseStudyShortlist $shortlist): array
    {
        try {
            $row = Schema::connection('legacy_phase1')->hasTable('tblapplication')
                ? DB::connection('legacy_phase1')->table('tblapplication')->where('ID', $shortlist->source_application_id)->first()
                : null;
        } catch (\Throwable) {
            $row = null;
        }
        if (! $row) {
            return $this->fallbackProfile($shortlist);
        }
        $r = (array) $row;
        $district = LegacyPhase1DistrictResolver::canonicalNameForLegacyFatherName((string) ($r['FatherName'] ?? ''));
        $legacyServices = $this->phase1Details->servicesForLegacyId((int) $shortlist->source_application_id);

        return [
            'identity' => $this->rows([
                'Applicant name' => $r['FullName'] ?? null, 'Application number' => $r['ApplicationNumber'] ?? null,
                'Phone' => $r['MobileNumber'] ?? null, 'Gender' => $r['gender'] ?? null,
                'District' => $district, 'Block / city' => $r['City'] ?? null, 'Education' => $r['education'] ?? null,
                'Caste/category' => $r['cast'] ?? null,
            ]),
            'enterprise' => $this->rows([
                'Enterprise name' => $r['enterprise_name'] ?? null, 'Business idea' => $r['idea'] ?? $r['other_idea'] ?? null,
                'Business description' => $r['business_desp'] ?? null, 'Business stage' => $r['current_status'] ?? null,
                'Registered' => $r['registered'] ?? null, 'Registration type' => $r['registration_type'] ?? null,
                'Average turnover' => $r['avg_turnover'] ?? null, 'Previous turnover' => $r['pre_turnover'] ?? null,
                'Current employment' => $r['current_emp'] ?? null, 'Jobs offered' => $r['job_count'] ?? null,
                'Loan' => $r['loan'] ?? null, 'Loan amount' => $r['loan_amount'] ?? null,
            ]),
            'potential' => $this->rows([
                'Training received' => $r['training'] ?? null, 'Technology usage' => $r['tech'] ?? null,
                'Challenges' => $r['chal'] ?? null, 'Support required' => $r['seekingHelp'] ?? $r['need_help'] ?? null,
                'Five-year vision' => $r['in5years'] ?? null, 'Mentorship attained' => $r['mentorships_attained'] ?? null,
            ]),
            'journey' => $this->rows([
                'Programme year' => $shortlist->program_year, 'Source' => 'Phase 1 legacy',
                'Application date' => $r['ApplicationDate'] ?? null, 'Onboarding status' => LegacyPhase1DistrictResolver::onboardLabel($r['onboard'] ?? null),
                'Shortlisted in' => $shortlist->shortlist_month?->format('F Y'),
            ]),
            'legacy_services' => collect($legacyServices)->map(fn (array $service): array => [
                'name' => $service['label'], 'date' => 'Phase 1 record', 'status' => 'Recorded', 'source' => 'Phase 1',
                'detail' => $service['detail'], 'provider' => null, 'sort_date' => '',
                'service_code' => $this->serviceCode($service['label']),
            ])->all(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function currentServiceCases(CaseStudyShortlist $shortlist): Collection
    {
        if (! Schema::hasTable('service_cases')) return collect();
        $query = ServiceCase::query()->with(['service:id,name', 'creator:id,name', 'approver:id,name']);
        if ($shortlist->source === 'phase3') {
            $query->where('cfa_submission_id', $shortlist->source_application_id);
        } elseif ($shortlist->source === 'phase2' && ServiceCase::supportsLegacyApplicationLink()) {
            $query->where('legacy_application_id', $shortlist->source_application_id);
        } elseif ($shortlist->source === 'phase1') {
            $ids = CfaSubmission::query()->whereIn('source', ['legacy_phase1', 'rbiphase1'])
                ->get(['id', 'payload'])->filter(fn (CfaSubmission $cfa) => in_array((int) (($cfa->payload['legacy_phase1_id'] ?? $cfa->payload['legacy_id'] ?? 0)), [$shortlist->source_application_id], true))->pluck('id');
            if ($ids->isEmpty()) return collect();
            $query->whereIn('cfa_submission_id', $ids);
        } else return collect();

        return $query->latest()->get()->map(fn (ServiceCase $case): array => [
            'name' => $case->service?->name ?? 'Service', 'date' => ($case->delivered_on ?? $case->approved_at ?? $case->created_at)?->format('d M Y'),
            'status' => str_replace('_', ' ', ucfirst($case->status)), 'source' => 'Phase 3 service case',
            'detail' => $case->reference_number, 'provider' => $case->approver?->name ?? $case->creator?->name,
            'sort_date' => (string) ($case->delivered_on ?? $case->approved_at ?? $case->created_at),
            'service_code' => in_array((string) $case->status, [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED], true)
                ? $this->serviceCode($case->service?->name ?? '') : null,
        ]);
    }

    private function pitchDeckRows(CaseStudyShortlist $shortlist): Collection
    {
        if (! Schema::hasTable('pitch_deck_preparations')) return collect();
        $q = PitchDeckPreparation::query()->with('enteredBy:id,name');
        if ($shortlist->source === 'phase3') $q->where('cfa_submission_id', $shortlist->source_application_id);
        elseif ($shortlist->source === 'phase2') $q->where('legacy_application_id', $shortlist->source_application_id);
        else return collect();
        return $q->get()->map(fn ($row): array => [
            'name' => 'Pitch Deck Preparation', 'date' => $row->prepared_on?->format('d M Y'), 'status' => 'Completed',
            'source' => 'Pitch Deck module', 'detail' => $row->formattedSupportMode(), 'provider' => $row->enteredBy?->name ?? $row->entered_by_name,
            'sort_date' => (string) $row->prepared_on, 'service_code' => 'pitch_deck',
        ]);
    }

    private function accelerationRows(CaseStudyShortlist $shortlist): Collection
    {
        if ($shortlist->source !== 'phase1' || ! Schema::hasTable('acceleration_service_sessions')) return collect();
        return AccelerationServiceSession::query()->with(['items:id,session_id,item_label'])
            ->where('legacy_phase1_application_id', $shortlist->source_application_id)
            ->where(fn ($q) => $q->whereNull('is_draft')->orWhere('is_draft', false))
            ->get()->map(fn ($row): array => [
                'name' => 'Acceleration Services', 'date' => $row->service_date?->format('d M Y'), 'status' => $row->statusLabel(),
                'source' => 'Acceleration module', 'detail' => $row->items->pluck('item_label')->join(', '), 'provider' => $row->submitted_by_name,
                'sort_date' => (string) $row->service_date,
                'service_code' => (string) $row->status === 'approved' ? 'acceleration' : null,
            ]);
    }

    private function marketLinkageRows(CaseStudyShortlist $shortlist): Collection
    {
        if (! Schema::hasTable('market_linkage_submissions')) return collect();
        $q = MarketLinkageSubmission::query()->with(['partners', 'submitter:id,name']);
        if ($shortlist->source === 'phase3') $q->where('cfa_submission_id', $shortlist->source_application_id);
        elseif ($shortlist->source === 'phase2') $q->where('legacy_application_id', $shortlist->source_application_id);
        else return collect();
        return $q->get()->map(fn ($row): array => [
            'name' => 'Market Linkage', 'date' => ($row->approved_at ?? $row->submitted_at ?? $row->created_at)?->format('d M Y'),
            'status' => str_replace('_', ' ', ucfirst((string) $row->status)), 'source' => 'Market Linkage module',
            'detail' => $row->partners->pluck('partner_name')->join(', '), 'provider' => $row->submitter?->name ?? $row->submitted_by_name,
            'sort_date' => (string) ($row->approved_at ?? $row->submitted_at ?? $row->created_at), 'service_code' => null,
        ]);
    }

    private function technicalTrainingRows(CaseStudyShortlist $shortlist): Collection
    {
        if ($shortlist->source !== 'phase3' || ! Schema::hasTable('technical_trainings')) return collect();

        return TechnicalTraining::query()->with('submitter:id,name')->approvedForMis()
            ->where('district_id', $shortlist->district_id)
            ->whereJsonContains('selected_incubatee_ids', (int) $shortlist->source_application_id)
            ->get()->map(fn (TechnicalTraining $row): array => [
                'name' => 'Technical Training', 'date' => $row->event_date?->format('d M Y'),
                'status' => 'Completed', 'source' => 'Technical Training module',
                'detail' => collect([$row->training_batch_name, $row->session_name])->filter()->join(' - '),
                'provider' => $row->submitter?->name ?? $row->submitted_by_name,
                'sort_date' => (string) $row->event_date, 'service_code' => 'technical_training',
            ]);
    }

    private function caseStudyRows(CaseStudyShortlist $shortlist): Collection
    {
        if (! Schema::hasTable('case_study_entries')) return collect();

        $query = CaseStudyEntry::query()->with('submitter:id,name');
        if ($shortlist->source === 'phase3') {
            $query->where('cfa_submission_id', $shortlist->source_application_id);
        } elseif ($shortlist->source === 'phase2') {
            $query->where('legacy_application_id', $shortlist->source_application_id);
        } else {
            return collect();
        }

        return $query->get()->map(fn (CaseStudyEntry $row): array => [
            'name' => 'Case Study', 'date' => $row->story_date?->format('d M Y'),
            'status' => 'Completed', 'source' => 'Case Study module',
            'detail' => collect([$row->story_title, $row->story_type])->filter()->join(' - '),
            'provider' => $row->submitter?->name ?? $row->submitted_by_name,
            'sort_date' => (string) $row->story_date, 'service_code' => 'case_study',
        ]);
    }

    /** @return array<string, mixed> */
    private function fallbackProfile(CaseStudyShortlist $shortlist): array
    {
        return [
            'identity' => $this->rows(['Applicant name' => $shortlist->applicant_name, 'Application number' => $shortlist->application_no, 'District' => $shortlist->district?->name, 'Block' => $shortlist->block_name]),
            'enterprise' => $this->rows(['Business category' => $shortlist->business_category, 'Business stage' => $shortlist->business_stage]),
            'potential' => [], 'journey' => $this->rows(['Programme year' => $shortlist->program_year, 'Source' => ucfirst($shortlist->source)]), 'legacy_services' => [],
        ];
    }

    /** @param array<string, mixed> $values @return list<array{label:string,value:string,available:bool}> */
    private function rows(array $values): array
    {
        return collect($values)->map(function ($value, string $label): array {
            $available = $value !== null && trim((string) $value) !== '' && ! in_array(trim((string) $value), ['N/A', '-'], true);
            return ['label' => $label, 'value' => $available ? trim((string) $value) : 'Not available in this programme year', 'available' => $available];
        })->values()->all();
    }

    private function serviceCode(string $label): ?string
    {
        $value = mb_strtolower($label);
        if (str_contains($value, 'pitch') && str_contains($value, 'deck')) return 'pitch_deck';
        if (str_contains($value, 'acceleration')) return 'acceleration';
        if (str_contains($value, 'technical') && str_contains($value, 'training')) return 'technical_training';
        if (str_contains($value, 'case') && str_contains($value, 'study')) return 'case_study';
        return null;
    }
}
