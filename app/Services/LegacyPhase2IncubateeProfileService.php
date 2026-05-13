<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only legacy rbiphase2 "Incubatee profile" data (mirrors legacy PHP profile page queries).
 */
class LegacyPhase2IncubateeProfileService
{
    public function legacyAvailable(): bool
    {
        if ((string) config('database.connections.legacy.database', '') === '') {
            return false;
        }
        try {
            return Schema::connection('legacy')->hasTable('rbi_applications')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *   legacy_application_id: int,
     *   application: array<string, string>,
     *   applicant: array<string, string>,
     *   enterprise: array<string, string>,
     *   product_image_urls: list<string>,
     *   services: list<array{service_name: string, category: string, assigned_on: ?string, partner_name: string, partner_link: string, served_by_name: string}>,
     *   profile_pic_filename: ?string,
     *   profile_pic_storage_relative: ?string
     * }|null
     */
    public function loadProfile(int $legacyApplicationId): ?array
    {
        if ($legacyApplicationId < 1 || ! $this->legacyAvailable()) {
            return null;
        }

        $hasEnterprise = $this->legacyHasTable('rbi_enterprise_details');

        $base = DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->where('d.application_id', $legacyApplicationId);

        $select = [
            'a.id as legacy_application_id',
            'a.application_no',
            DB::raw('TRIM(a.form_stage) as form_stage'),
            'a.submission_date',
            DB::raw('TRIM(a.category) as category'),
            'a.product',
            'a.other_product',
            'd.applicant_name',
            'd.gender',
            'd.dob',
            'd.education',
            'd.phone',
            'd.empwomen',
            'd.techuse',
            'd.challenges',
            'd.email',
            'd.caste',
            'd.is_shg_member',
            'd.shg_name',
            'd.district',
            'd.block',
            'd.village',
            'd.id_proof_type',
            'd.id_proof_number',
            'd.expectations',
            'd.expectation_other',
            'd.migrated_for_employment',
            'd.submitted_by_name',
            'd.submitted_by_mobile',
        ];

        if ($hasEnterprise) {
            $base->leftJoin(DB::raw('(
                SELECT e1.*
                FROM rbi_enterprise_details e1
                INNER JOIN (
                    SELECT application_id, MAX(id) AS max_id
                    FROM rbi_enterprise_details
                    GROUP BY application_id
                ) t ON t.application_id = e1.application_id AND t.max_id = e1.id
            ) as e'), 'e.application_id', '=', 'd.application_id');
            array_push(
                $select,
                'e.enterprise_name',
                'e.is_registered',
                'e.registration_type',
                'e.registration_number',
                'e.registration_date',
                'e.sector',
                'e.turnover_last_year',
                'e.years_in_business',
                'e.team_size',
                'e.location_type',
                'e.support_needed',
                'e.training_received',
                'e.training_institute',
            );
        }

        try {
            $row = $base->select($select)->first();
        } catch (\Throwable) {
            return null;
        }

        if ($row === null) {
            return null;
        }

        $r = (array) $row;

        $application = [
            'application_no' => (string) ($r['application_no'] ?? 'N/A'),
            'form_stage' => (string) ($r['form_stage'] ?? 'Unknown'),
            'submission_date' => $this->fmtDate($r['submission_date'] ?? null),
            'category' => (string) ($r['category'] ?? 'N/A'),
            'product' => (string) ($r['product'] ?? 'N/A'),
            'other_product' => (string) ($r['other_product'] ?? ''),
        ];

        $shgRaw = $r['is_shg_member'] ?? 0;
        $shgYes = ((int) $shgRaw) !== 0 || in_array(mb_strtolower(trim((string) $shgRaw)), ['yes', 'y', 'true'], true);

        $applicant = [
            'applicant_name' => (string) ($r['applicant_name'] ?? 'N/A'),
            'gender' => (string) ($r['gender'] ?? 'N/A'),
            'dob' => $this->fmtDate($r['dob'] ?? null),
            'education' => (string) ($r['education'] ?? 'N/A'),
            'phone' => (string) ($r['phone'] ?? 'N/A'),
            'empwomen' => (string) ($r['empwomen'] ?? 'N/A'),
            'techuse' => (string) ($r['techuse'] ?? 'N/A'),
            'challenges' => (string) ($r['challenges'] ?? 'N/A'),
            'email' => (string) ($r['email'] ?? 'N/A'),
            'caste' => (string) ($r['caste'] ?? 'N/A'),
            'is_shg_member' => $shgYes ? 'Yes' : 'No',
            'shg_name' => (string) ($r['shg_name'] ?? 'N/A'),
            'district' => (string) ($r['district'] ?? 'N/A'),
            'block' => (string) ($r['block'] ?? 'N/A'),
            'village' => (string) ($r['village'] ?? 'N/A'),
            'id_proof_type' => (string) ($r['id_proof_type'] ?? 'N/A'),
            'id_proof_number' => (string) ($r['id_proof_number'] ?? 'N/A'),
            'expectations' => (string) ($r['expectations'] ?? 'N/A'),
            'expectation_other' => (string) ($r['expectation_other'] ?? 'N/A'),
            'migrated_for_employment' => (string) ($r['migrated_for_employment'] ?? 'N/A'),
            'submitted_by_name' => (string) ($r['submitted_by_name'] ?? 'N/A'),
            'submitted_by_mobile' => (string) ($r['submitted_by_mobile'] ?? 'N/A'),
        ];

        $turnover = $r['turnover_last_year'] ?? null;
        $enterprise = [
            'enterprise_name' => (string) ($r['enterprise_name'] ?? 'N/A'),
            'is_registered' => (string) ($r['is_registered'] ?? 'N/A'),
            'registration_type' => (string) ($r['registration_type'] ?? 'N/A'),
            'registration_number' => (string) ($r['registration_number'] ?? 'N/A'),
            'registration_date' => $this->fmtDate($r['registration_date'] ?? null),
            'sector' => (string) ($r['sector'] ?? 'N/A'),
            'turnover_last_year' => $turnover !== null && $turnover !== ''
                ? number_format((float) $turnover, 0, '.', '')
                : 'N/A',
            'years_in_business' => (string) ($r['years_in_business'] ?? 'N/A'),
            'team_size' => (string) ($r['team_size'] ?? 'N/A'),
            'location_type' => (string) ($r['location_type'] ?? 'N/A'),
            'support_needed' => (string) ($r['support_needed'] ?? 'N/A'),
            'training_received' => (string) ($r['training_received'] ?? 'N/A'),
            'training_institute' => (string) ($r['training_institute'] ?? 'N/A'),
        ];

        $profilePicFilename = null;
        $profilePicStorageRelative = null;
        if ($this->legacyHasTable('rbi_onboarded_applicants')) {
            try {
                $pic = DB::connection('legacy')
                    ->table('rbi_onboarded_applicants')
                    ->where('application_id', $legacyApplicationId)
                    ->value('pic');
                if (is_string($pic) && trim($pic) !== '') {
                    $profilePicFilename = trim($pic);
                    $rel = 'legacy-incubatee-photos/'.$legacyApplicationId.'/'.$profilePicFilename;
                    if (Storage::disk('local')->exists($rel)) {
                        $profilePicStorageRelative = $rel;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $productUrls = [];
        if ($this->legacyHasTable('rbi_product_images')) {
            try {
                $paths = DB::connection('legacy')
                    ->table('rbi_product_images')
                    ->where('application_id', $legacyApplicationId)
                    ->orderBy('id')
                    ->pluck('image_path')
                    ->all();
                $base = (string) config('legacy_phase2.legacy_public_assets_base_url', '');
                foreach ($paths as $p) {
                    $p = trim((string) $p);
                    if ($p === '') {
                        continue;
                    }
                    if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
                        $productUrls[] = $p;
                    } elseif ($base !== '') {
                        $productUrls[] = $base.'/'.ltrim(str_replace('\\', '/', $p), '/');
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $services = $this->loadServices($legacyApplicationId);

        return [
            'legacy_application_id' => $legacyApplicationId,
            'application' => $application,
            'applicant' => $applicant,
            'enterprise' => $enterprise,
            'product_image_urls' => $productUrls,
            'services' => $services,
            'profile_pic_filename' => $profilePicFilename,
            'profile_pic_storage_relative' => $profilePicStorageRelative,
        ];
    }

    /**
     * @return list<array{service_name: string, category: string, assigned_on: ?string, partner_name: string, partner_link: string, served_by_name: string}>
     */
    private function loadServices(int $legacyApplicationId): array
    {
        if (! $this->legacyHasTable('rbi_services_assigned')) {
            return [];
        }

        $hasUsers = $this->legacyHasTable('users');
        try {
            $q = DB::connection('legacy')
                ->table('rbi_services_assigned as sa')
                ->where('sa.application_id', $legacyApplicationId);
            if ($hasUsers) {
                $q->leftJoin('users as u', 'u.id', '=', 'sa.served_by')
                    ->select([
                        'sa.service_name',
                        'sa.category',
                        DB::raw('DATE(COALESCE(sa.assigned_date, sa.doc_date)) AS assigned_on'),
                        'sa.partner_name',
                        'sa.partner_link',
                        DB::raw('COALESCE(u.full_name, u.username) AS served_by_name'),
                    ]);
            } else {
                $q->select([
                    'sa.service_name',
                    'sa.category',
                    DB::raw('DATE(COALESCE(sa.assigned_date, sa.doc_date)) AS assigned_on'),
                    'sa.partner_name',
                    'sa.partner_link',
                    DB::raw("'' AS served_by_name"),
                ]);
            }

            $rows = $q
                ->orderBy(DB::raw('COALESCE(sa.assigned_date, sa.doc_date)'), 'asc')
                ->orderBy('sa.id', 'asc')
                ->get();
        } catch (\Throwable) {
            try {
                $rows = DB::connection('legacy')
                    ->table('rbi_services_assigned as sa')
                    ->where('sa.application_id', $legacyApplicationId)
                    ->select([
                        'sa.service_name',
                        'sa.category',
                        DB::raw('DATE(COALESCE(sa.assigned_date, sa.doc_date)) AS assigned_on'),
                        'sa.partner_name',
                        'sa.partner_link',
                        DB::raw("'' AS served_by_name"),
                    ])
                    ->orderBy(DB::raw('COALESCE(sa.assigned_date, sa.doc_date)'), 'asc')
                    ->orderBy('sa.id', 'asc')
                    ->get();
            } catch (\Throwable) {
                return [];
            }
        }

        $out = [];
        foreach ($rows as $row) {
            $x = (array) $row;
            $out[] = [
                'service_name' => (string) ($x['service_name'] ?? ''),
                'category' => (string) ($x['category'] ?? ''),
                'assigned_on' => isset($x['assigned_on']) ? (string) $x['assigned_on'] : null,
                'partner_name' => (string) ($x['partner_name'] ?? ''),
                'partner_link' => (string) ($x['partner_link'] ?? ''),
                'served_by_name' => (string) ($x['served_by_name'] ?? ''),
            ];
        }

        return $out;
    }

    private function legacyHasTable(string $table): bool
    {
        try {
            return Schema::connection('legacy')->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function fmtDate(mixed $v): string
    {
        if ($v === null || $v === '') {
            return 'N/A';
        }
        try {
            return Carbon::parse((string) $v)->format('d M Y');
        } catch (\Throwable) {
            return 'N/A';
        }
    }
}
