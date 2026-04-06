<?php

namespace App\Services\LegacyPhase2;

use App\Models\Deliverable;
use App\Models\FiscalYear;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 legacy achievements scoped to the **selected** fiscal year (`starts_on` … `ends_on` on `fiscal_years`).
 * Each event is bucketed into M1–M12 via {@see FiscalYear::fiscalMonthIndex()} (calendar ladder from FY start month).
 *
 * Parity with legacy staff dashboard `admin/targets.php`:
 * - CFA: `rbi_applications` + `submitted_by_name` (non-CDO / non–state_team) or district-wide (CDO/state_team).
 * - Block / district / EDP workshops: `block_workshop_entries` + `enworkshop` / `block` rules.
 * - Onboarding: `rbi_onboarded_applicants` + batch `onboard_district` (district-wide; no per-user filter).
 * - BST: `training_sessions` TP1–TP4; CDO/state_team via `COALESCE(served_by, created_by)` district; else `served_by = legacy user id`.
 * - Market link / marketing: `rbi_service_partners` + DISTINCT `application_id` + partner rules.
 * - Other services: `rbi_services_assigned` + {@see self::serviceConditionForKey()} + `<= CURDATE()` on work date.
 * - ATF rollup: convergence category or {@see self::isAccessToFinanceComponent()} on normalized service name.
 *
 * FY **2025-26** in DB should use Phase 2 window **2025-04-02 … 2026-04-01** (see migration `2026_04_21_120000_align_fiscal_year_2025_26_targets_php`).
 * State admin `admin/24.php` uses the same tables with a **custom / quarter** date window instead of a single FY row — counts are still date-filtered the same way per activity.
 */
class Phase2TargetsPhpAchievementService
{
    /** @var array<string, string> */
    private array $normToDeliverable;

    public function __construct()
    {
        $this->normToDeliverable = config('phase2_targets_achievement.norm_type_to_deliverable_code', []);
    }

    /**
     * @return array<int, array<int, int>> deliverable_id => [ 1..12 => count ]
     */
    public function countsByDeliverableAndFiscalMonth(User $user, FiscalYear $fy): array
    {
        if (! $user->legacy_user_id) {
            return [];
        }

        if ((string) config('database.connections.legacy.database', '') === '') {
            return [];
        }

        $districtName = $user->district?->name;
        if ($districtName === null || trim($districtName) === '') {
            return [];
        }

        $variants = $this->districtVariantsForSql($districtName);
        if ($variants === []) {
            return [];
        }

        $codeToId = Deliverable::query()
            ->where('is_active', true)
            ->pluck('id', 'code')
            ->all();

        $out = [];
        $add = function (int $deliverableId, int $monthIdx, int $delta = 1) use (&$out): void {
            if ($monthIdx < 1 || $monthIdx > 12) {
                return;
            }
            if (! isset($out[$deliverableId])) {
                $out[$deliverableId] = array_fill(1, 12, 0);
            }
            $out[$deliverableId][$monthIdx] += $delta;
        };

        $fyStart = Carbon::parse($fy->starts_on)->startOfDay();
        $fyEnd = Carbon::parse($fy->ends_on)->endOfDay();
        $legacyUserId = (int) $user->legacy_user_id;
        $role = strtolower((string) ($user->role ?? ''));
        $isCdo = $role === 'cdo';
        $isStateTeam = $role === 'state_team';
        $districtWide = $isCdo || $isStateTeam;
        $nameLower = mb_strtolower(trim($user->name));

        $this->addCfaCounts($add, $codeToId, $fy, $fyStart, $fyEnd, $variants, $districtWide, $nameLower);
        $this->addWorkshopCounts($add, $codeToId, $fy, $fyStart, $fyEnd, $variants, $districtWide, $legacyUserId);
        $this->addOnboardingCounts($add, $codeToId, $fy, $fyStart, $fyEnd, $variants);
        $this->addBstSessionCounts($add, $codeToId, $fy, $fyStart, $fyEnd, $variants, $districtWide, $legacyUserId);
        $this->addMarketPartnerCounts($add, $codeToId, $fy, $fyStart, $fyEnd, $variants, $districtWide, $legacyUserId);
        $this->addGenericServiceCounts($add, $codeToId, $fy, $fyStart, $fyEnd, $variants, $districtWide, $legacyUserId);
        $this->addAccessToFinanceRollup($add, $codeToId, $fy, $fyStart, $fyEnd, $variants, $districtWide, $legacyUserId);

        return $out;
    }

    /**
     * @param  callable(int, int, int): void  $add
     * @param  array<string, int>  $codeToId
     * @param  list<string>  $variants
     */
    private function addCfaCounts(
        callable $add,
        array $codeToId,
        FiscalYear $fy,
        Carbon $fyStart,
        Carbon $fyEnd,
        array $variants,
        bool $districtWide,
        string $nameLower,
    ): void {
        if (! isset($codeToId['cfa'])
            || ! Schema::connection('legacy')->hasTable('rbi_applications')
            || ! Schema::connection('legacy')->hasTable('rbi_applicant_details')) {
            return;
        }

        $q = DB::connection('legacy')
            ->table('rbi_applications as a')
            ->leftJoin('rbi_applicant_details as d', 'a.id', '=', 'd.application_id')
            ->whereNotNull('a.submission_date')
            ->whereBetween(DB::raw('DATE(a.submission_date)'), [$fyStart->toDateString(), $fyEnd->toDateString()])
            ->whereIn(DB::raw('LOWER(TRIM(d.district))'), $variants)
            ->when(! $districtWide, function ($q) use ($nameLower) {
                $q->whereRaw('LOWER(d.submitted_by_name) LIKE ?', ['%'.$nameLower.'%']);
            })
            ->selectRaw('a.submission_date as ev');

        foreach ($q->cursor() as $row) {
            $at = $this->parseEventDate($row->ev ?? null);
            if ($at === null) {
                continue;
            }
            $i = $fy->fiscalMonthIndex($at);
            if ($i !== null) {
                $add((int) $codeToId['cfa'], $i);
            }
        }
    }

    /**
     * @param  callable(int, int, int): void  $add
     * @param  array<string, int>  $codeToId
     * @param  list<string>  $variants
     */
    private function addWorkshopCounts(
        callable $add,
        array $codeToId,
        FiscalYear $fy,
        Carbon $fyStart,
        Carbon $fyEnd,
        array $variants,
        bool $districtWide,
        int $legacyUserId,
    ): void {
        if (! Schema::connection('legacy')->hasTable('block_workshop_entries')) {
            return;
        }

        $scan = function (int $deliverableId, callable $applyConstraints) use ($add, $districtWide, $legacyUserId, $variants, $fy, $fyStart, $fyEnd): void {
            $q = DB::connection('legacy')
                ->table('block_workshop_entries as bwe')
                ->whereBetween('bwe.workshop_date', [$fyStart->toDateString(), $fyEnd->toDateString()])
                ->whereNotNull('bwe.workshop_date');
            if ($districtWide) {
                $q->leftJoin('users as u', 'u.id', '=', 'bwe.user_id')
                    ->whereIn(DB::raw('LOWER(TRIM(u.district))'), $variants);
            } else {
                $q->where('bwe.user_id', $legacyUserId);
            }
            $applyConstraints($q);
            $q->selectRaw('bwe.workshop_date as ev');

            foreach ($q->cursor() as $row) {
                $at = $this->parseEventDate($row->ev ?? null);
                $i = $at ? $fy->fiscalMonthIndex($at) : null;
                if ($i !== null) {
                    $add($deliverableId, $i);
                }
            }
        };

        if (isset($codeToId['lakhpati_block'])) {
            $scan((int) $codeToId['lakhpati_block'], function ($q): void {
                $q->whereRaw("(bwe.block IS NOT NULL AND TRIM(bwe.block) <> '')")
                    ->whereRaw("(bwe.enworkshop IS NULL OR LOWER(TRIM(bwe.enworkshop)) <> 'yes')");
            });
        }

        if (isset($codeToId['awareness_district'])) {
            $scan((int) $codeToId['awareness_district'], function ($q): void {
                $q->whereRaw("(bwe.block IS NULL OR TRIM(bwe.block) = '')")
                    ->whereRaw("(bwe.enworkshop IS NULL OR LOWER(TRIM(bwe.enworkshop)) <> 'yes')");
            });
        }

        if (isset($codeToId['edp_workshop'])) {
            $scan((int) $codeToId['edp_workshop'], function ($q): void {
                $q->whereRaw("LOWER(TRIM(bwe.enworkshop)) = 'yes'");
            });
        }
    }

    /**
     * @param  callable(int, int, int): void  $add
     * @param  array<string, int>  $codeToId
     * @param  list<string>  $variants
     */
    private function addOnboardingCounts(
        callable $add,
        array $codeToId,
        FiscalYear $fy,
        Carbon $fyStart,
        Carbon $fyEnd,
        array $variants,
    ): void {
        if (! isset($codeToId['onboarding'])
            || ! Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')
            || ! Schema::connection('legacy')->hasTable('rbi_onboarding_batches')) {
            return;
        }

        $dateCol = Schema::connection('legacy')->hasColumn('rbi_onboarded_applicants', 'onboarded_at')
            ? 'boa.onboarded_at' : 'boa.created_at';

        $q = DB::connection('legacy')
            ->table('rbi_onboarded_applicants as boa')
            ->join('rbi_onboarding_batches as b', 'boa.onboarding_batch_id', '=', 'b.id')
            ->whereNotNull($dateCol)
            ->whereBetween(DB::raw('DATE('.$dateCol.')'), [$fyStart->toDateString(), $fyEnd->toDateString()])
            ->whereIn(DB::raw('LOWER(TRIM(b.onboard_district))'), $variants)
            ->selectRaw($dateCol.' as ev');

        foreach ($q->cursor() as $row) {
            $at = $this->parseEventDate($row->ev ?? null);
            $i = $at ? $fy->fiscalMonthIndex($at) : null;
            if ($i !== null) {
                $add((int) $codeToId['onboarding'], $i);
            }
        }
    }

    /**
     * @param  callable(int, int, int): void  $add
     * @param  array<string, int>  $codeToId
     * @param  list<string>  $variants
     */
    private function addBstSessionCounts(
        callable $add,
        array $codeToId,
        FiscalYear $fy,
        Carbon $fyStart,
        Carbon $fyEnd,
        array $variants,
        bool $districtWide,
        int $legacyUserId,
    ): void {
        if (! isset($codeToId['bst_sessions']) || ! Schema::connection('legacy')->hasTable('training_sessions')) {
            return;
        }

        $packages = ['Training Package 1', 'Training Package 2', 'Training Package 3', 'Training Package 4'];

        $q = DB::connection('legacy')
            ->table('training_sessions as ts')
            ->whereBetween('ts.session_date', [$fyStart->toDateString(), $fyEnd->toDateString()])
            ->whereNotNull('ts.session_date')
            ->whereIn('ts.service_name', $packages);

        if ($districtWide) {
            $q->leftJoin('users as u', function ($join): void {
                $join->whereRaw('u.id = COALESCE(ts.served_by, ts.created_by)');
            })->whereIn(DB::raw('LOWER(TRIM(u.district))'), $variants);
        } else {
            $q->where('ts.served_by', $legacyUserId);
        }

        $q->selectRaw('ts.session_date as ev');

        foreach ($q->cursor() as $row) {
            $at = $this->parseEventDate($row->ev ?? null);
            $i = $at ? $fy->fiscalMonthIndex($at) : null;
            if ($i !== null) {
                $add((int) $codeToId['bst_sessions'], $i);
            }
        }
    }

    /**
     * @param  callable(int, int, int): void  $add
     * @param  array<string, int>  $codeToId
     * @param  list<string>  $variants
     */
    private function addMarketPartnerCounts(
        callable $add,
        array $codeToId,
        FiscalYear $fy,
        Carbon $fyStart,
        Carbon $fyEnd,
        array $variants,
        bool $districtWide,
        int $legacyUserId,
    ): void {
        if (! isset($codeToId['market_link'])
            || ! Schema::connection('legacy')->hasTable('rbi_service_partners')
            || ! Schema::connection('legacy')->hasTable('rbi_applicant_details')) {
            return;
        }

        $q = DB::connection('legacy')
            ->table('rbi_service_partners as sp')
            ->leftJoin('rbi_services_assigned as sa', 'sa.id', '=', 'sp.service_assigned_id')
            ->join('rbi_applicant_details as d', 'd.application_id', '=', 'sp.application_id')
            ->whereBetween(DB::raw('DATE(COALESCE(sp.added_at, sa.assigned_date, sa.doc_date))'), [$fyStart->toDateString(), $fyEnd->toDateString()])
            ->whereRaw("COALESCE(sp.status,'Active') = 'Active'")
            ->whereIn(DB::raw('LOWER(TRIM(d.district))'), $variants)
            ->when(! $districtWide, fn ($q) => $q->where('sp.added_by', $legacyUserId))
            ->whereRaw('(
                COALESCE(NULLIF(TRIM(sp.partner_link),\'\'), \'\') <> \'\'
                OR (COALESCE(sp.partner_type,\'online\') = \'offline\'
                    AND EXISTS (SELECT 1 FROM rbi_service_partner_products spp WHERE spp.partner_id = sp.id))
            )')
            ->select([
                'sp.application_id',
                DB::raw('COALESCE(sp.added_at, sa.assigned_date, sa.doc_date) as ev'),
            ]);

        $seen = [];
        foreach ($q->cursor() as $row) {
            $at = $this->parseEventDate($row->ev ?? null);
            $i = $at ? $fy->fiscalMonthIndex($at) : null;
            if ($i === null) {
                continue;
            }
            $k = ((string) ($row->application_id ?? '')).'-'.$i;
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $add((int) $codeToId['market_link'], $i);
        }
    }

    /**
     * @param  callable(int, int, int): void  $add
     * @param  array<string, int>  $codeToId
     * @param  list<string>  $variants
     */
    private function addGenericServiceCounts(
        callable $add,
        array $codeToId,
        FiscalYear $fy,
        Carbon $fyStart,
        Carbon $fyEnd,
        array $variants,
        bool $districtWide,
        int $legacyUserId,
    ): void {
        if (! Schema::connection('legacy')->hasTable('rbi_services_assigned')
            || ! Schema::connection('legacy')->hasTable('rbi_applicant_details')) {
            return;
        }

        $skipNorm = [
            'call for application', 'district workshop', 'block workshop', 'edp workshop',
            'onboarding', 'business skills training', 'market link', 'marketing support',
            'access to finance',
        ];

        $groups = [];
        foreach ($this->normToDeliverable as $norm => $code) {
            if ($code === 'access_to_finance') {
                continue;
            }
            if (in_array($norm, $skipNorm, true)) {
                continue;
            }
            if (! isset($codeToId[$code])) {
                continue;
            }
            $clause = $this->serviceConditionForKey($norm);
            if ($clause === null) {
                continue;
            }
            $gkey = $clause['sql'].'|'.json_encode($clause['bindings'], JSON_THROW_ON_ERROR);
            if (! isset($groups[$gkey])) {
                $groups[$gkey] = ['clause' => $clause, 'codes' => []];
            }
            $groups[$gkey]['codes'][$code] = true;
        }

        foreach ($groups as $group) {
            /** @var array{sql: string, bindings: list<mixed>} $clause */
            $clause = $group['clause'];
            /** @var array<string, bool> $codes */
            $codes = $group['codes'];
            $deliverableIds = [];
            foreach (array_keys($codes) as $code) {
                $deliverableIds[] = (int) $codeToId[$code];
            }
            $deliverableIds = array_values(array_unique($deliverableIds));

            $q = DB::connection('legacy')
                ->table('rbi_services_assigned as sa')
                ->leftJoin('rbi_applicant_details as d', 'sa.application_id', '=', 'd.application_id')
                ->whereBetween(DB::raw('DATE(COALESCE(sa.assigned_date, sa.doc_date))'), [$fyStart->toDateString(), $fyEnd->toDateString()])
                ->whereRaw('DATE(COALESCE(sa.assigned_date, sa.doc_date)) <= CURDATE()')
                ->whereIn(DB::raw('LOWER(TRIM(d.district))'), $variants)
                ->when(! $districtWide, fn ($q) => $q->where('sa.served_by', $legacyUserId));

            $q->whereRaw('('.$clause['sql'].')', $clause['bindings']);
            $q->selectRaw('COALESCE(sa.assigned_date, sa.doc_date) as ev');

            foreach ($q->cursor() as $row) {
                $at = $this->parseEventDate($row->ev ?? null);
                $i = $at ? $fy->fiscalMonthIndex($at) : null;
                if ($i === null) {
                    continue;
                }
                foreach ($deliverableIds as $did) {
                    $add($did, $i);
                }
            }
        }
    }

    /**
     * @param  callable(int, int, int): void  $add
     * @param  array<string, int>  $codeToId
     * @param  list<string>  $variants
     */
    private function addAccessToFinanceRollup(
        callable $add,
        array $codeToId,
        FiscalYear $fy,
        Carbon $fyStart,
        Carbon $fyEnd,
        array $variants,
        bool $districtWide,
        int $legacyUserId,
    ): void {
        if (! isset($codeToId['access_to_finance'])
            || ! Schema::connection('legacy')->hasTable('rbi_services_assigned')
            || ! Schema::connection('legacy')->hasTable('rbi_applicant_details')) {
            return;
        }

        $q = DB::connection('legacy')
            ->table('rbi_services_assigned as sa')
            ->leftJoin('rbi_applicant_details as d', 'sa.application_id', '=', 'd.application_id')
            ->whereBetween(DB::raw('DATE(COALESCE(sa.assigned_date, sa.doc_date))'), [$fyStart->toDateString(), $fyEnd->toDateString()])
            ->whereRaw('DATE(COALESCE(sa.assigned_date, sa.doc_date)) <= CURDATE()')
            ->whereIn(DB::raw('LOWER(TRIM(d.district))'), $variants)
            ->when(! $districtWide, fn ($q) => $q->where('sa.served_by', $legacyUserId))
            ->selectRaw("TRIM(COALESCE(sa.service_name,'-')) AS service_name, TRIM(COALESCE(sa.category,'')) AS category, COALESCE(sa.assigned_date, sa.doc_date) as ev");

        foreach ($q->cursor() as $row) {
            $at = $this->parseEventDate($row->ev ?? null);
            $i = $at ? $fy->fiscalMonthIndex($at) : null;
            if ($i === null) {
                continue;
            }
            $cat = strtolower(trim((string) ($row->category ?? '')));
            $akey = $this->normType((string) ($row->service_name ?? ''));
            $isAtf = $cat === 'convergence' || $this->isAccessToFinanceComponent($akey);
            if ($isAtf) {
                $add((int) $codeToId['access_to_finance'], $i);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function districtVariantsForSql(string $selected): array
    {
        $norm = $this->normtxt($selected);
        $variantsMap = [
            'udham singh nagar' => ['udham singh nagar', 'udham singh nagr', 'us nagar', 'u s nagar', 'u.s. nagar', 'u s n'],
            'pauri garhwal' => ['pauri garhwal', 'pauri'],
            'tehri garhwal' => ['tehri garhwal', 'tehri'],
            'haridwar' => ['haridwar', 'hardwar'],
            'dehradun' => ['dehradun', 'doon'],
            'rudraprayag' => ['rudraprayag', 'rudra prayag'],
        ];
        $list = $variantsMap[$norm] ?? [$norm];

        return array_values(array_unique(array_map(fn (string $d) => strtolower(trim($d)), $list)));
    }

    private function normtxt(string $s): string
    {
        $s = strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
        $aliasesMap = [
            'udham singh nagar' => ['udham singh nagar', 'udham singh nagr', 'us nagar', 'u s nagar', 'u.s. nagar', 'u s n', 'uddham singh nagar'],
            'pauri garhwal' => ['pauri garhwal', 'pauri', 'kotdwar'],
            'tehri garhwal' => ['tehri garhwal', 'tehri'],
            'haridwar' => ['haridwar', 'hardwar'],
            'dehradun' => ['dehradun', 'doon'],
            'rudraprayag' => ['rudraprayag', 'rudra prayag'],
            'almora' => ['almora', 'al mora'],
            'pithoragarh' => ['pithoragarh', 'pithora garh'],
        ];
        foreach ($aliasesMap as $canon => $variants) {
            if (in_array($s, $variants, true)) {
                return $canon;
            }
        }
        $simple = [
            'us nagar' => 'udham singh nagar',
            'u s nagar' => 'udham singh nagar',
            'udham singh nagr' => 'udham singh nagar',
            'uddham singh nagar' => 'udham singh nagar',
            'pauri' => 'pauri garhwal',
            'kotdwar' => 'pauri garhwal',
            'tehri' => 'tehri garhwal',
            'haridawar' => 'haridwar',
            'rudra prayag' => 'rudraprayag',
            'al mora' => 'almora',
            'pithora garh' => 'pithoragarh',
        ];

        return $simple[$s] ?? $s;
    }

    private function parseEventDate(mixed $v): ?Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            return Carbon::parse($v);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isAccessToFinanceComponent(string $activityKey): bool
    {
        $k = strtolower(trim($activityKey));
        $atf = [
            'msy', 'msy nano', 'pmegp', 'msme', 'mudra',
            'pmfme',
            'ddu grah awas yojana (homestay)',
            'veer chandra singh garhwali self empl.',
            'other support service',
            'support in process',
            'support in application process',
        ];
        if (str_contains($k, 'support in') && (str_contains($k, 'process') || str_contains($k, 'application'))) {
            return true;
        }

        return in_array($k, $atf, true);
    }

    private function normType(string $raw): string
    {
        $s = strtolower(trim((string) $raw));
        $s = str_replace(['_', '-', '/', '.', '(', ')'], ' ', $s);
        $s = (string) preg_replace('/\s+/', ' ', $s);

        $aliases = [
            'access to finance' => 'access to finance',
            'support in process' => 'support in process',
            'support in application process' => 'support in process',
            'other support service' => 'other support service',
            'edp' => 'edp workshop',
            'edp session' => 'edp workshop',
            'edp sessions' => 'edp workshop',
            'district workshop' => 'district workshop',
            'block workshop' => 'block workshop',
            'udyam registration' => 'udyam registration',
            'artisan card' => 'artisan card',
            'fssai' => 'fssai',
            'gst' => 'gst',
            'pan' => 'pan card',
            'pan card' => 'pan card',
            'gi seller registration' => 'gi seller registration',
            'gi registration' => 'gi seller registration',
            'gi seller' => 'gi seller registration',
            'utdb hub wise' => 'utdb hub wise',
            'utdb registration' => 'utdb registration',
            'ipr support' => 'ipr support',
            'trademark filing' => 'trademark filing',
            'market link' => 'market link',
            'marketing support' => 'marketing support',
            'product testing' => 'product testing',
            'business model canvas' => 'business model canvas',
            'business plan' => 'business plan',
            'business acceleration' => 'business acceleration',
            'business skills training' => 'business skills training',
            'training package 1' => 'training package 1',
            'training package 2' => 'training package 2',
            'training package 3' => 'training package 3',
            'training package 4' => 'training package 4',
            'legal vetting of documents' => 'legal vetting of documents',
            'fire noc' => 'fire noc',
            'ayush licence' => 'ayush licence',
            'cooperative' => 'cooperative',
            'company registration' => 'company registration',
            'uk firm registration' => 'uk firm registration',
            'pmfme' => 'pmfme',
            'msy' => 'msy',
            'msy nano' => 'msy nano',
            'pmegp' => 'pmegp',
            'msme' => 'msme',
            'mudra' => 'mudra',
            'veer chandra singh garhwali self empl.' => 'veer chandra singh garhwali self empl.',
            'ddu grah awas yojana (homestay)' => 'ddu grah awas yojana (homestay)',
            'shop establishment' => 'shop establishment',
            'other service' => 'other service',
            'other licensing support' => 'other licensing support',
            'call for application' => 'call for application',
            'onboarding' => 'onboarding',
        ];
        if (isset($aliases[$s])) {
            return $aliases[$s];
        }

        if (str_contains($s, 'fssai')) {
            return 'fssai';
        }
        if (str_contains($s, 'udyam')) {
            return 'udyam registration';
        }
        if (str_contains($s, 'shop') && str_contains($s, 'estab')) {
            return 'shop establishment';
        }
        if (str_contains($s, 'utdb') && str_contains($s, 'hub')) {
            return 'utdb hub wise';
        }
        if (str_contains($s, 'utdb')) {
            return 'utdb registration';
        }
        if (str_contains($s, 'gi') && str_contains($s, 'seller')) {
            return 'gi seller registration';
        }
        if (str_contains($s, 'gst')) {
            return 'gst';
        }
        if (str_contains($s, 'pan card') || $s === 'pan') {
            return 'pan card';
        }
        if (str_contains($s, 'artisan')) {
            return 'artisan card';
        }
        if (str_contains($s, 'pmfme')) {
            return 'pmfme';
        }
        if (str_contains($s, 'ipr')) {
            return 'ipr support';
        }
        if (str_contains($s, 'trademark filing')) {
            return 'trademark filing';
        }
        if (str_contains($s, 'market link')) {
            return 'market link';
        }
        if (str_contains($s, 'marketing support')) {
            return 'marketing support';
        }
        if (str_contains($s, 'product testing')) {
            return 'product testing';
        }
        if (str_contains($s, 'business model canvas')) {
            return 'business model canvas';
        }
        if (str_contains($s, 'business plan')) {
            return 'business plan';
        }
        if (str_contains($s, 'business acceleration')) {
            return 'business acceleration';
        }
        if (str_contains($s, 'access to finance')) {
            return 'access to finance';
        }
        if (str_contains($s, 'business skills training')) {
            return 'business skills training';
        }
        if (str_contains($s, 'training package 1')) {
            return 'training package 1';
        }
        if (str_contains($s, 'training package 2')) {
            return 'training package 2';
        }
        if (str_contains($s, 'training package 3')) {
            return 'training package 3';
        }
        if (str_contains($s, 'training package 4')) {
            return 'training package 4';
        }
        if (str_contains($s, 'legal vetting')) {
            return 'legal vetting of documents';
        }
        if (str_contains($s, 'fire noc')) {
            return 'fire noc';
        }
        if (str_contains($s, 'ayush')) {
            return 'ayush licence';
        }
        if (str_contains($s, 'cooperative')) {
            return 'cooperative';
        }
        if (str_contains($s, 'company registration')) {
            return 'company registration';
        }
        if (str_contains($s, 'msy nano')) {
            return 'msy nano';
        }
        if ($s === 'msy') {
            return 'msy';
        }
        if ($s === 'pmegp') {
            return 'pmegp';
        }
        if ($s === 'msme') {
            return 'msme';
        }
        if ($s === 'mudra') {
            return 'mudra';
        }
        if (str_contains($s, 'veer chandra singh garhwali')) {
            return 'veer chandra singh garhwali self empl.';
        }
        if (str_contains($s, 'ddu grah awas')) {
            return 'ddu grah awas yojana (homestay)';
        }
        if (str_contains($s, 'support in') && (str_contains($s, 'process') || str_contains($s, 'application'))) {
            return 'support in process';
        }

        return $s;
    }

    /**
     * @return array{sql: string, bindings: list<mixed>}|null
     */
    private function serviceConditionForKey(string $key): ?array
    {
        $k = $key;
        $contains = fn (string $needle): array => [
            'sql' => "REPLACE(LOWER(sa.service_name), '\n', ' ') LIKE ?",
            'bindings' => ['%'.$needle.'%'],
        ];
        $eq = fn (string $exact): array => [
            'sql' => 'LOWER(sa.service_name) = ?',
            'bindings' => [strtolower($exact)],
        ];

        return match ($k) {
            'gst' => $eq('gst'),
            'udyam registration' => $eq('udyam registration'),
            'artisan card' => $eq('artisan card'),
            'pan card' => $eq('pan card'),
            'ipr support' => $eq('ipr support'),
            'business model canvas' => $eq('business model canvas'),
            'business plan' => $eq('business plan'),
            'business acceleration' => $eq('business acceleration'),
            'training package 1' => $eq('training package 1'),
            'training package 2' => $eq('training package 2'),
            'training package 3' => $eq('training package 3'),
            'training package 4' => $eq('training package 4'),
            'legal vetting of documents' => $eq('legal vetting of documents'),
            'fire noc' => $eq('fire noc'),
            'ayush licence' => $eq('ayush licence'),
            'cooperative' => $eq('cooperative'),
            'company registration' => $eq('company registration'),
            'uk firm registration' => $eq('uk firm registration'),
            'pmfme' => $eq('pmfme'),
            'msy' => $eq('msy'),
            'msy nano' => $eq('msy nano'),
            'pmegp' => $eq('pmegp'),
            'msme' => $eq('msme'),
            'mudra' => $eq('mudra'),
            'market link' => $eq('market link'),
            'marketing support' => $eq('marketing support'),
            'product testing' => $eq('product testing'),
            'business skills training' => $eq('business skills training'),
            'utdb registration', 'utdb hub wise' => $contains('utdb'),
            'trademark filing', 'trademark' => $contains('trademark'),
            'shop establishment' => [
                'sql' => "REPLACE(LOWER(sa.service_name), '\n', ' ') LIKE ? AND REPLACE(LOWER(sa.service_name), '\n', ' ') LIKE ?",
                'bindings' => ['%shop%', '%estab%'],
            ],
            'gi seller registration' => $contains('gi seller'),
            'fssai' => $contains('fssai'),
            'other service' => $eq('other service'),
            'other licensing support' => $eq('other licensing support'),
            'access to finance' => null,
            default => $contains($k),
        };
    }
}
