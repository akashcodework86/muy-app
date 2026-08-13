<?php

namespace App\Services;

use App\Models\HomestaySurveyResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve a Homestay incubatee by phone across Phase 1 / 2 / 3 and build survey prefill.
 */
final class HomestaySurveyLookupService
{
    /**
     * @return array{
     *     status: 'ok'|'already_submitted'|'not_found'|'invalid',
     *     message?: string,
     *     profile?: array<string, mixed>,
     *     prefill?: array<string, mixed>
     * }
     */
    public function resolve(string $phone): array
    {
        $phone = trim($phone);
        if (! preg_match('/^[6-9]\d{9}$/', $phone)) {
            return [
                'status' => 'invalid',
                'message' => 'Enter a valid 10-digit Indian mobile number.',
            ];
        }

        if (HomestaySurveyResponse::query()->where('phone', $phone)->exists()) {
            return [
                'status' => 'already_submitted',
                'message' => 'A survey response for this mobile number has already been submitted.',
            ];
        }

        $profile = $this->findHomestayByPhone($phone);
        if ($profile === null) {
            return [
                'status' => 'not_found',
                'message' => 'This mobile number was not found as a Homestay incubatee in MUY (Phase 1–3). Please check the number and try again.',
            ];
        }

        return [
            'status' => 'ok',
            'profile' => array_merge($profile, [
                'summary' => $this->buildProfileSummary($profile),
            ]),
            'prefill' => $this->buildPrefill($profile),
        ];
    }

    /**
     * Simple match-card fields for the public form.
     *
     * @param  array<string, mixed>  $profile
     * @return array{
     *     name: string,
     *     application_no: string,
     *     district: string,
     *     block: string,
     *     sector: string,
     *     product: string
     * }
     */
    public function buildProfileSummary(array $profile): array
    {
        $raw = is_array($profile['raw'] ?? null) ? $profile['raw'] : [];

        $sector = $this->str($raw['sector'] ?? ($raw['business_category'] ?? null));
        if ($sector === '') {
            $sector = 'Homestay';
        }

        $product = $this->str($raw['product'] ?? null);
        if ($product === '') {
            $product = $this->str($raw['enterprise_name'] ?? null);
        }

        return [
            'name' => $this->str($raw['applicant_name'] ?? null),
            'application_no' => $this->str($profile['application_no'] ?? null),
            'district' => $this->str($raw['district'] ?? null),
            'block' => $this->str($raw['block'] ?? null),
            'sector' => $sector,
            'product' => $product,
        ];
    }

    /**
     * @return array{0: string, 1: string} [formatted date, year]
     */
    private function formatCfaFilledDate(mixed $raw): array
    {
        $s = $this->str($raw);
        if ($s === '') {
            return ['', ''];
        }
        try {
            $dt = Carbon::parse($s);

            return [$dt->format('d M Y'), (string) $dt->year];
        } catch (\Throwable) {
            return ['', ''];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findHomestayByPhone(string $phone): ?array
    {
        return $this->findPhase3($phone)
            ?? $this->findPhase2($phone)
            ?? $this->findPhase1($phone);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public function buildPrefill(array $profile): array
    {
        $raw = $profile['raw'] ?? [];

        $prefill = [
            'respondent_name' => $this->str($raw['applicant_name'] ?? null),
            'gender' => $this->mapGender($raw['gender'] ?? null),
            'age_group' => $this->ageGroupFromDob($raw['dob'] ?? null),
            'caste' => $this->mapCaste($raw['caste'] ?? null),
            'enterprise_name' => $this->str($raw['enterprise_name'] ?? null),
            'district' => $this->str($raw['district'] ?? null),
            'block' => $this->str($raw['block'] ?? null),
            'village' => $this->str($raw['village'] ?? null),
            'pincode' => $this->str($raw['pincode'] ?? null),
            'location_type' => $this->mapLocationType($raw['location_type'] ?? null),
            'phone' => $this->str($profile['phone'] ?? ($raw['phone'] ?? null)),
            'email' => $this->str($raw['email'] ?? null),
            'enrolment_year' => $this->enrolmentYear($raw),
            'info_source' => $this->mapInfoSource($raw['info_source'] ?? null),
            'incubation_center' => $this->str($raw['hub'] ?? ($raw['incubation_center'] ?? null)),
            'venture_type' => $this->mapVentureType($raw['business_age'] ?? null),
            'stage_at_enrolment' => $this->mapStage($raw['form_stage'] ?? ($raw['business_stage'] ?? null)),
            'utdb_registered' => $this->mapUtdbRegistered($raw),
            'utdb_reg_number' => $this->str($raw['utdb_reg_number'] ?? ($raw['registration_number'] ?? null)),
            'muy_financial_assistance' => $this->yesNoFromAmount($raw['financial_amount'] ?? null),
            'muy_financial_amount' => $this->str($raw['financial_amount'] ?? null),
            'bank_loan_muy' => $this->mapYesNo($raw['bank_loan'] ?? ($raw['loan_taken'] ?? null)),
            'employed_count_during' => $this->str($raw['employed_count'] ?? ($raw['current_employment'] ?? null)),
            'empwomen_during' => $this->str($raw['empwomen'] ?? null),
            'support_services' => $raw['support_services'] ?? [],
            'challenges_prefill' => $raw['challenges'] ?? [],
            'website' => '',
            'role' => '',
        ];

        // Drop empty enterprise name so the applicant fills it
        if ($prefill['enterprise_name'] === '') {
            unset($prefill['enterprise_name']);
        }

        return $prefill;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPhase3(string $phone): ?array
    {
        if (! Schema::hasTable('cfa_submissions')) {
            return null;
        }

        $rows = DB::table('cfa_submissions as cs')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->where('cs.phone', $phone)
            ->orderByDesc('cs.id')
            ->get([
                'cs.id',
                'cs.application_no',
                'cs.applicant_name',
                'cs.phone',
                'cs.payload',
                'cs.created_at',
                'd.name as district_name',
                'h.name as hub_name',
            ]);

        foreach ($rows as $row) {
            $payload = json_decode((string) ($row->payload ?? ''), true);
            if (! is_array($payload)) {
                $payload = [];
            }
            $bc = mb_strtolower(trim((string) ($payload['business_category'] ?? '')));
            $abc = mb_strtolower(trim((string) ($payload['app_business_category'] ?? '')));
            if ($bc !== 'homestay' && $abc !== 'homestay') {
                continue;
            }

            $hub = (string) ($row->hub_name ?? '');
            $onboarding = $this->phase3OnboardingMeta((int) $row->id);
            if ($onboarding['hub'] !== '') {
                $hub = $onboarding['hub'];
            }

            $enterprise = trim((string) ($payload['enterprise_name'] ?? ''));
            $product = trim((string) ($payload['other_product'] ?? ''));
            if ($product === '') {
                $product = trim((string) ($payload['product'] ?? ($payload['app_product'] ?? '')));
            }
            if ($enterprise === '') {
                $enterprise = $product;
                if (mb_strtolower($enterprise) === 'homestay') {
                    $enterprise = '';
                }
            }
            $sector = trim((string) ($payload['business_category'] ?? ($payload['app_business_category'] ?? 'Homestay')));
            if ($sector === '') {
                $sector = 'Homestay';
            }

            $regTypes = $payload['registration_type'] ?? ($payload['registration_types'] ?? []);
            if (is_string($regTypes)) {
                $regTypes = [$regTypes];
            }
            if (! is_array($regTypes)) {
                $regTypes = [];
            }
            $hasUtdb = false;
            foreach ($regTypes as $t) {
                if (stripos((string) $t, 'utdb') !== false) {
                    $hasUtdb = true;
                    break;
                }
            }
            $isRegistered = $this->mapYesNo($payload['is_registered'] ?? null);
            $utdb = $hasUtdb ? 'Yes' : ($isRegistered === 'Yes' ? '' : ($isRegistered === 'No' ? 'No' : ''));

            $services = $this->phase3SupportServices((int) $row->id);

            return [
                'phase' => 'Phase 3',
                'source_id' => (int) $row->id,
                'application_no' => (string) ($row->application_no ?? ''),
                'phone' => (string) ($row->phone ?? $phone),
                'raw' => [
                    'applicant_name' => $row->applicant_name ?: ($payload['applicant_name'] ?? ''),
                    'gender' => $payload['gender'] ?? '',
                    'dob' => $payload['dob'] ?? '',
                    'caste' => $payload['caste'] ?? '',
                    'enterprise_name' => $enterprise,
                    'sector' => $sector,
                    'business_category' => $sector,
                    'product' => $product,
                    'district' => $row->district_name ?: ($payload['district'] ?? ''),
                    'block' => $payload['block'] ?? ($payload['block_name'] ?? ''),
                    'village' => $payload['village'] ?? '',
                    'pincode' => $payload['pincode'] ?? '',
                    'location_type' => $payload['location_type'] ?? '',
                    'phone' => $row->phone ?: ($payload['phone'] ?? $phone),
                    'email' => $payload['email'] ?? '',
                    'hub' => $hub,
                    'info_source' => $payload['info_source'] ?? '',
                    'business_age' => $payload['business_age'] ?? '',
                    'form_stage' => $payload['form_stage'] ?? ($payload['business_stage'] ?? ''),
                    'utdb_registered' => $utdb,
                    'utdb_reg_number' => $payload['registration_number'] ?? ($payload['registration_no'] ?? ''),
                    'financial_amount' => $payload['financial_amount'] ?? '',
                    'bank_loan' => $payload['bank_loan'] ?? '',
                    'loan_taken' => $payload['loan_taken'] ?? '',
                    'employed_count' => $payload['employed_count'] ?? '',
                    'current_employment' => $payload['current_employment'] ?? '',
                    'empwomen' => $payload['empwomen'] ?? '',
                    'challenges' => $payload['challenges'] ?? [],
                    'support_services' => $services,
                    'onboarding_date' => $onboarding['onboarding_date'],
                    'submission_date' => $payload['submitted_at'] ?? ($payload['submission_date'] ?? $row->created_at),
                ],
            ];
        }

        return null;
    }

    /**
     * @return array{hub: string, onboarding_date: mixed}
     */
    private function phase3OnboardingMeta(int $cfaId): array
    {
        $out = ['hub' => '', 'onboarding_date' => null];
        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return $out;
        }

        $row = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'ob.hub_id')
            ->where('obc.cfa_submission_id', $cfaId)
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->orderByDesc('ob.locked_at')
            ->first(['h.name as hub_name', 'ob.onboarding_date', 'ob.locked_at']);

        if (! $row) {
            return $out;
        }

        return [
            'hub' => trim((string) ($row->hub_name ?? '')),
            'onboarding_date' => $row->onboarding_date ?: $row->locked_at,
        ];
    }

    /**
     * @return list<string>
     */
    private function phase3SupportServices(int $cfaId): array
    {
        if (! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return [];
        }

        $names = DB::table('service_cases as sc')
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->where('sc.cfa_submission_id', $cfaId)
            ->whereNotNull('s.name')
            ->pluck('s.name')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $mapped = [];
        $rules = [
            'Business/entrepreneurship training' => ['eap', 'edp', 'entrepreneur', 'business training', 'bmc'],
            'Hospitality/guest management' => ['hospitality', 'guest', 'homestay training'],
            'Digital marketing' => ['digital marketing', 'social media', 'online marketing'],
            'Financial literacy/ assistance' => ['financ', 'loan', 'credit', 'seed'],
            'Registration/licensing help' => ['registration', 'udyam', 'gst', 'fssai', 'utdb', 'license'],
            'Branding' => ['brand'],
            'Mentoring/handholding' => ['mentor', 'handhold'],
            'Market linkage' => ['market linkage', 'market link', 'ota', 'buyer'],
        ];

        foreach ($rules as $label => $needles) {
            foreach ($names as $name) {
                foreach ($needles as $needle) {
                    if (str_contains($name, $needle)) {
                        $mapped[] = $label;
                        continue 3;
                    }
                }
            }
        }

        return array_values(array_unique($mapped));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPhase2(string $phone): ?array
    {
        if (trim((string) config('database.connections.legacy.database', '')) === '') {
            return null;
        }

        try {
            $query = DB::connection('legacy')
                ->table('rbi_applications as a')
                ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
                ->where('d.phone', $phone)
                ->whereRaw("LOWER(TRIM(COALESCE(a.business_category, ''))) = 'homestay'")
                ->orderByDesc('a.id');

            $hasEnterprise = false;
            try {
                $hasEnterprise = Schema::connection('legacy')->hasTable('rbi_enterprise_details');
            } catch (\Throwable) {
                $hasEnterprise = false;
            }

            if ($hasEnterprise) {
                $query->leftJoin(DB::raw('(
                    SELECT e1.*
                    FROM rbi_enterprise_details e1
                    INNER JOIN (
                        SELECT application_id, MAX(id) AS max_id
                        FROM rbi_enterprise_details
                        GROUP BY application_id
                    ) t ON t.application_id = e1.application_id AND t.max_id = e1.id
                ) as ed'), 'ed.application_id', '=', 'a.id');
            }

            $select = [
                'a.id as application_id',
                'a.application_no',
                'a.form_stage',
                'a.submission_date',
                'a.business_category',
                'a.product',
                'a.other_product',
                'd.applicant_name',
                'd.gender',
                'd.dob',
                'd.phone',
                'd.email',
                'd.caste',
                'd.district',
                'd.block',
                'd.village',
                'd.pincode',
                'd.info_source',
                'd.loan_taken',
                'd.bank_loan',
                'd.current_employment',
                'd.employed_count',
                'd.empwomen',
                'd.challenges',
                'd.techuse',
            ];
            if ($hasEnterprise) {
                $select[] = 'ed.enterprise_name';
                $select[] = 'ed.location_type';
                $select[] = 'ed.is_registered';
                $select[] = 'ed.financial_amount';
                $select[] = 'ed.years_in_business';
            }

            $row = $query->first($select);
            if (! $row) {
                return null;
            }

            $challenges = $row->challenges ?? [];
            if (is_string($challenges)) {
                $decoded = json_decode($challenges, true);
                $challenges = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $challenges)));
            }

            $hub = '';
            try {
                if (Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')) {
                    $oa = DB::connection('legacy')
                        ->table('rbi_onboarded_applicants as oa')
                        ->leftJoin('rbi_onboarding_batches as b', 'b.id', '=', 'oa.onboarding_batch_id')
                        ->where('oa.application_id', $row->application_id)
                        ->orderByDesc('oa.id')
                        ->first(['b.batch_name', 'oa.onboarded_at']);
                    if ($oa) {
                        $hub = trim((string) ($oa->batch_name ?? ''));
                    }
                }
            } catch (\Throwable) {
                // ignore
            }

            $enterprise = $hasEnterprise ? trim((string) ($row->enterprise_name ?? '')) : '';
            $product = trim((string) ($row->other_product ?? ''));
            if ($product === '') {
                $product = trim((string) ($row->product ?? ''));
            }
            $sector = trim((string) ($row->business_category ?? 'Homestay'));
            if ($sector === '') {
                $sector = 'Homestay';
            }
            $utdb = '';
            if ($hasEnterprise) {
                $reg = $this->mapYesNo($row->is_registered ?? null);
                $utdb = $reg === 'Yes' ? 'Yes' : ($reg === 'No' ? 'No' : '');
            }

            return [
                'phase' => 'Phase 2',
                'source_id' => (int) $row->application_id,
                'application_no' => (string) ($row->application_no ?? ''),
                'phone' => (string) ($row->phone ?? $phone),
                'raw' => [
                    'applicant_name' => $row->applicant_name ?? '',
                    'gender' => $row->gender ?? '',
                    'dob' => $row->dob ?? '',
                    'caste' => $row->caste ?? '',
                    'enterprise_name' => $enterprise,
                    'sector' => $sector,
                    'business_category' => $sector,
                    'product' => $product,
                    'district' => $row->district ?? '',
                    'block' => $row->block ?? '',
                    'village' => $row->village ?? '',
                    'pincode' => $row->pincode ?? '',
                    'location_type' => $hasEnterprise ? ($row->location_type ?? '') : '',
                    'phone' => $row->phone ?? $phone,
                    'email' => $row->email ?? '',
                    'hub' => $hub,
                    'info_source' => $row->info_source ?? '',
                    'business_age' => $hasEnterprise ? ($row->years_in_business ?? '') : '',
                    'form_stage' => $row->form_stage ?? '',
                    'utdb_registered' => $utdb,
                    'financial_amount' => $hasEnterprise ? ($row->financial_amount ?? '') : '',
                    'bank_loan' => $row->bank_loan ?? '',
                    'loan_taken' => $row->loan_taken ?? '',
                    'employed_count' => $row->employed_count ?? '',
                    'current_employment' => $row->current_employment ?? '',
                    'empwomen' => $row->empwomen ?? '',
                    'challenges' => $challenges,
                    'support_services' => [],
                    'submission_date' => $row->submission_date ?? null,
                    'onboarding_date' => null,
                ],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPhase1(string $phone): ?array
    {
        if (trim((string) config('database.connections.legacy_phase1.database', '')) === '') {
            return null;
        }

        try {
            $row = DB::connection('legacy_phase1')
                ->table('tblapplication')
                ->where('MobileNumber', $phone)
                ->orderByDesc('ID')
                ->first([
                    'ID', 'ApplicationNumber', 'FullName', 'gender', 'dob', 'cast', 'Email', 'MobileNumber',
                    'FatherName', 'City', 'Pincode', 'Address', 'hub', 'business_desp',
                    'enterprise_name', 'registered', 'loan', 'loan_amount', 'current_emp', 'job_count',
                    'ApplicationDate', 'onboarding_date', 'onboard_date', 'chal', 'tech',
                ]);

            if (! $row) {
                return null;
            }

            $desp = mb_strtolower(trim((string) ($row->business_desp ?? '')));
            $desp = preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', $desp)) ?? $desp;
            $desp = rtrim($desp, " \t\n\r\0\x0B.");
            if (! in_array($desp, ['homestay', 'home stay'], true)) {
                return null;
            }

            $district = trim((string) ($row->FatherName ?? ''));

            return [
                'phase' => 'Phase 1',
                'source_id' => (int) $row->ID,
                'application_no' => (string) ($row->ApplicationNumber ?? ''),
                'phone' => (string) ($row->MobileNumber ?? $phone),
                'raw' => [
                    'applicant_name' => $row->FullName ?? '',
                    'gender' => $row->gender ?? '',
                    'dob' => $row->dob ?? '',
                    'caste' => $row->cast ?? '',
                    'enterprise_name' => $row->enterprise_name ?? '',
                    'sector' => 'Homestay',
                    'business_category' => 'Homestay',
                    'product' => trim((string) ($row->business_desp ?? 'Homestay')),
                    'district' => $district,
                    'block' => $row->City ?? '',
                    'village' => $row->Address ?? '',
                    'pincode' => $row->Pincode ?? '',
                    'location_type' => '',
                    'phone' => $row->MobileNumber ?? $phone,
                    'email' => $row->Email ?? '',
                    'hub' => $row->hub ?? '',
                    'info_source' => '',
                    'business_age' => '',
                    'form_stage' => '',
                    'utdb_registered' => $this->mapYesNo($row->registered ?? null),
                    'financial_amount' => $row->loan_amount ?? '',
                    'bank_loan' => $row->loan ?? '',
                    'loan_taken' => $row->loan ?? '',
                    'employed_count' => $row->job_count ?? '',
                    'current_employment' => $row->current_emp ?? '',
                    'empwomen' => '',
                    'challenges' => $row->chal ?? [],
                    'support_services' => [],
                    'submission_date' => $row->ApplicationDate ?? null,
                    'onboarding_date' => $row->onboarding_date ?: ($row->onboard_date ?? null),
                ],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function str(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        $s = trim((string) $v);
        if ($s === '' || $s === '—' || strtolower($s) === 'null' || strtolower($s) === 'na') {
            return '';
        }

        return $s;
    }

    private function mapGender(mixed $raw): string
    {
        $g = mb_strtolower($this->str($raw));
        if ($g === '') {
            return '';
        }
        if (str_starts_with($g, 'f')) {
            return 'Female';
        }
        if (str_starts_with($g, 'm')) {
            return 'Male';
        }
        if (str_contains($g, 'other') || $g === 'o') {
            return 'Other';
        }

        return '';
    }

    private function mapCaste(mixed $raw): string
    {
        $c = mb_strtoupper($this->str($raw));
        if ($c === '') {
            return '';
        }
        $map = [
            'GEN' => 'General',
            'GENERAL' => 'General',
            'OBC' => 'OBC',
            'SC' => 'SC',
            'ST' => 'ST',
            'MINORITY' => 'Minority',
        ];

        return $map[$c] ?? (in_array($c, ['GENERAL', 'OBC', 'SC', 'ST', 'MINORITY'], true) ? ucfirst(strtolower($c)) : '');
    }

    private function ageGroupFromDob(mixed $dob): string
    {
        $s = $this->str($dob);
        if ($s === '') {
            return '';
        }
        try {
            $age = Carbon::parse($s)->age;
        } catch (\Throwable) {
            return '';
        }
        if ($age < 18) {
            return '18–25';
        }
        if ($age <= 25) {
            return '18–25';
        }
        if ($age <= 35) {
            return '26–35';
        }
        if ($age <= 45) {
            return '36–45';
        }
        if ($age <= 60) {
            return '46–60';
        }

        return 'Above 60';
    }

    private function mapLocationType(mixed $raw): string
    {
        $v = mb_strtolower($this->str($raw));
        if ($v === '') {
            return '';
        }
        if (str_contains($v, 'rural')) {
            return 'Rural';
        }
        if (str_contains($v, 'urban') && ! str_contains($v, 'semi')) {
            return 'Urban';
        }
        if (str_contains($v, 'semi')) {
            return 'Semi-urban';
        }
        if (str_contains($v, 'remote') || str_contains($v, 'border')) {
            return 'Remote/border area';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function enrolmentYear(array $raw): string
    {
        foreach (['onboarding_date', 'submission_date'] as $key) {
            $s = $this->str($raw[$key] ?? null);
            if ($s === '') {
                continue;
            }
            try {
                $y = (string) Carbon::parse($s)->year;
                if (in_array($y, config('homestay_survey.enrolment_years', []), true)) {
                    return $y;
                }
            } catch (\Throwable) {
                // continue
            }
        }

        return '';
    }

    private function mapInfoSource(mixed $raw): string
    {
        $v = mb_strtolower($this->str($raw));
        if ($v === '') {
            return '';
        }
        if (str_contains($v, 'social')) {
            return 'Social media/website';
        }
        if (str_contains($v, 'department') || str_contains($v, 'print')) {
            return 'Government official/line dept';
        }
        if (str_contains($v, 'rbi') || str_contains($v, 'muy') || str_contains($v, 'staff')) {
            return 'MUY Incubation center';
        }

        return '';
    }

    private function mapVentureType(mixed $raw): string
    {
        $v = mb_strtolower($this->str($raw));
        if ($v === '' || $v === '0') {
            return 'New venture (started after joining MUY)';
        }
        if (str_contains($v, '1-6') || str_contains($v, '7-12') || str_contains($v, '12-24') || str_contains($v, '>24') || (is_numeric($v) && (float) $v > 0)) {
            return 'Existing (expanded/formalized under MUY)';
        }

        return '';
    }

    private function mapStage(mixed $raw): string
    {
        $v = mb_strtolower($this->str($raw));
        if ($v === '') {
            return '';
        }
        if (str_contains($v, 'seed')) {
            return 'Seed';
        }
        if (str_contains($v, 'early')) {
            return 'Early';
        }
        if (str_contains($v, 'growth')) {
            return 'Growth';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function mapUtdbRegistered(array $raw): string
    {
        $direct = $this->str($raw['utdb_registered'] ?? null);
        if ($direct !== '') {
            return $this->mapYesNo($direct) ?: $direct;
        }

        return '';
    }

    private function mapYesNo(mixed $raw): string
    {
        $v = mb_strtolower($this->str($raw));
        if ($v === '') {
            return '';
        }
        if (in_array($v, ['yes', 'y', '1', 'true'], true) || str_starts_with($v, 'y')) {
            return 'Yes';
        }
        if (in_array($v, ['no', 'n', '0', 'false'], true) || str_starts_with($v, 'n')) {
            return 'No';
        }

        return '';
    }

    private function yesNoFromAmount(mixed $raw): string
    {
        $s = $this->str($raw);
        if ($s === '') {
            return '';
        }
        $num = preg_replace('/[^\d.]/', '', $s);
        if ($num !== null && $num !== '' && (float) $num > 0) {
            return 'Yes';
        }

        return '';
    }
}
