<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\OnboardingBatchDocument;
use App\Models\OnboardingBatchDraftCfa;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\HubBatchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only view of onboarding batches for roles that shouldn't manage them:
 *   - state_admin : all batches (filterable by hub / district)
 *   - district_staff : batches in their own district only
 *
 * Hub admins continue to use the existing interactive tool at hub.batches.*.
 */
class BatchReadOnlyController extends Controller
{
    public function __construct(private HubBatchService $batches) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = $this->resolveScope($user);

        $hubs = Hub::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $districts = District::query()
            ->when($scope['type'] === 'hub', fn ($q) => $q->where('hub_id', $scope['hub_id']))
            ->orderBy('hub_id')->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'hub_id']);

        $filterHub = $request->integer('hub') ?: null;
        $filterDistrict = $request->integer('district') ?: null;
        $filterStatus = $request->string('status')->toString();
        $searchQ = trim($request->string('q')->toString());
        $source = $request->string('source')->toString() === 'legacy' ? 'legacy' : 'phase3';

        $legacyTablesOk = $this->hasLegacyBatchTables();
        $legacyDbConfigured = (string) config('database.connections.legacy.database', '') !== '';
        $legacyRequestedButUnavailable = $request->string('source')->toString() === 'legacy'
            && (! $legacyDbConfigured || ! $legacyTablesOk);

        if ($source === 'legacy' && (! $legacyDbConfigured || ! $legacyTablesOk)) {
            $source = 'phase3';
        }

        if ($source === 'legacy') {
            return $this->indexLegacy(
                $request,
                $user,
                $scope,
                $hubs,
                $districts,
                $filterHub,
                $filterDistrict,
                $searchQ,
            );
        }

        $query = OnboardingBatch::query()
            ->with(['district:id,name,hub_id', 'hub:id,name']);

        if ($scope['type'] === 'hub') {
            $query->where('hub_id', $scope['hub_id']);
        } elseif ($scope['type'] === 'district') {
            $query->where('district_id', $scope['district_id']);
        }

        if ($scope['type'] === 'state' && $filterHub) {
            $query->where('hub_id', $filterHub);
        }
        if (in_array($scope['type'], ['state', 'hub'], true) && $filterDistrict) {
            $query->where('district_id', $filterDistrict);
        }
        if (in_array($filterStatus, ['draft', 'locked'], true)) {
            $query->where('status', $filterStatus);
        }

        if ($searchQ !== '') {
            $this->applyPhase3ApplicantSearch($query, $searchQ);
        }

        $batches = $query->orderByDesc('id')->paginate(30)->withQueryString();

        $batchIds = collect($batches->items())->pluck('id')->all();
        $lockedCounts = OnboardingBatchCfa::query()
            ->whereIn('onboarding_batch_id', $batchIds)
            ->selectRaw('onboarding_batch_id, COUNT(*) as c')
            ->groupBy('onboarding_batch_id')
            ->pluck('c', 'onboarding_batch_id');
        $draftCounts = OnboardingBatchDraftCfa::query()
            ->whereIn('onboarding_batch_id', $batchIds)
            ->selectRaw('onboarding_batch_id, COUNT(*) as c')
            ->groupBy('onboarding_batch_id')
            ->pluck('c', 'onboarding_batch_id');

        foreach ($batches as $b) {
            $b->member_count = $b->isDraft()
                ? (int) ($draftCounts[$b->id] ?? 0)
                : (int) ($lockedCounts[$b->id] ?? 0);
            $b->has_cdo_pdf = $this->batches->hasCdoPdf($b);
            $b->cdo_overdue = $this->batches->cdoIsOverdue($b);
            $b->cdo_pending = $this->batches->cdoIsPendingWithinWindow($b);
        }

        $totalsQuery = OnboardingBatch::query();
        if ($scope['type'] === 'hub') {
            $totalsQuery->where('hub_id', $scope['hub_id']);
        } elseif ($scope['type'] === 'district') {
            $totalsQuery->where('district_id', $scope['district_id']);
        }
        $totals = [
            'total' => (clone $totalsQuery)->count(),
            'locked' => (clone $totalsQuery)->where('status', 'locked')->count(),
            'draft' => (clone $totalsQuery)->where('status', 'draft')->count(),
        ];

        return view('batches.read.index', [
            'batches' => $batches,
            'hubs' => $hubs,
            'districts' => $districts,
            'scope' => $scope,
            'filters' => [
                'hub' => $filterHub,
                'district' => $filterDistrict,
                'status' => $filterStatus,
                'q' => $searchQ,
                'source' => 'phase3',
            ],
            'totals' => $totals,
            'routeIndex' => $this->routeIndex($user),
            'routeShowName' => $this->routeShowName($user),
            'routeLegacyShowName' => $this->routeLegacyShowName($user),
            'batchSource' => 'phase3',
            'legacyTablesOk' => $legacyTablesOk,
            'legacyDbConfigured' => $legacyDbConfigured,
            'legacyRequestedButUnavailable' => $legacyRequestedButUnavailable,
        ]);
    }

    /**
     * Read-only legacy batch (rbiphase2 / connection {@see config('database.connections.legacy')}).
     */
    public function showLegacy(Request $request, int $legacy_batch): View
    {
        $user = $request->user();
        $scope = $this->resolveScope($user);

        if ($scope['type'] === 'none' || $scope['type'] === 'hub') {
            abort(403);
        }

        if (! $this->hasLegacyBatchTables()) {
            abort(503, 'Legacy onboarding tables are not available.');
        }

        $batchRow = DB::connection('legacy')
            ->table('rbi_onboarding_batches')
            ->where('id', $legacy_batch)
            ->first();

        if ($batchRow === null) {
            abort(404);
        }

        $districtNamesFilter = null;
        if ($scope['type'] === 'district') {
            $dm = District::query()->find($scope['district_id']);
            $districtNamesFilter = $dm ? $this->legacyDistrictDisplayNames($dm) : [];
        }

        $membersQuery = DB::connection('legacy')
            ->table('rbi_onboarded_applicants as oa')
            ->join('rbi_applicant_details as d', 'd.application_id', '=', 'oa.application_id')
            ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->where('oa.onboarding_batch_id', $legacy_batch)
            ->orderBy('d.applicant_name')
            ->select([
                'd.application_id',
                'd.applicant_name',
                'd.phone',
                'd.district',
                'a.application_no',
                'a.form_stage',
            ]);

        if ($districtNamesFilter !== null) {
            if ($districtNamesFilter === []) {
                $membersQuery->whereRaw('1 = 0');
            } else {
                $membersQuery->whereIn('d.district', $districtNamesFilter);
            }
        }

        $members = $membersQuery->get()->map(fn ($row) => [
            'application_id' => (int) ($row->application_id ?? 0),
            'application_no' => (string) ($row->application_no ?? ''),
            'applicant_name' => (string) ($row->applicant_name ?? ''),
            'phone' => (string) ($row->phone ?? ''),
            'district' => (string) ($row->district ?? ''),
            'form_stage' => (string) ($row->form_stage ?? ''),
        ])->values();

        if ($scope['type'] === 'district') {
            $names = $districtNamesFilter ?? [];
            if ($names === []) {
                abort(403);
            }
            $anyInDistrict = DB::connection('legacy')
                ->table('rbi_onboarded_applicants as oa')
                ->join('rbi_applicant_details as d', 'd.application_id', '=', 'oa.application_id')
                ->where('oa.onboarding_batch_id', $legacy_batch)
                ->whereIn('d.district', $names)
                ->exists();
            if (! $anyInDistrict) {
                abort(403);
            }
        }

        return view('batches.read.show-legacy', [
            'batch' => $batchRow,
            'members' => $members,
            'scope' => $scope,
            'routeIndex' => $this->routeIndex($user),
            'legacyBatchId' => $legacy_batch,
        ]);
    }

    public function show(Request $request, OnboardingBatch $batch): View
    {
        $user = $request->user();
        $scope = $this->resolveScope($user);

        if ($scope['type'] === 'hub' && (int) $batch->hub_id !== $scope['hub_id']) {
            abort(403);
        }
        if ($scope['type'] === 'district' && (int) $batch->district_id !== $scope['district_id']) {
            abort(403);
        }

        $batch->load(['hub:id,name', 'district:id,name']);

        $members = ($batch->isDraft()
            ? $batch->draftCfas()->with('cfaSubmission')
            : $batch->batchCfas()->with('cfaSubmission'))
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                $cfa = $row->cfaSubmission;
                $payload = is_array($cfa?->payload) ? $cfa->payload : [];
                $stage = strtolower(trim((string) ($payload['form_stage'] ?? $payload['stage'] ?? '')));
                $stageKey = in_array($stage, ['seed', 'early', 'growth'], true) ? $stage : 'unknown';
                $biz = trim((string) ($payload['business_category'] ?? '')) ?: 'Not specified';

                return [
                    'id' => (int) ($cfa?->id ?? 0),
                    'application_no' => (string) ($cfa?->application_no ?? ($row->cfa_submission_id ?? '')),
                    'applicant_name' => (string) ($cfa?->applicant_name ?? 'N/A'),
                    'phone' => (string) ($cfa?->phone ?? ''),
                    'stage_key' => $stageKey,
                    'stage_label' => $stageKey === 'unknown' ? '—' : strtoupper($stageKey),
                    'business_category' => $biz,
                ];
            })
            ->values();

        $stageMix = ['seed' => 0, 'early' => 0, 'growth' => 0, 'unknown' => 0];
        $categoryMix = [];
        foreach ($members as $m) {
            $stageMix[$m['stage_key']]++;
            $categoryMix[$m['business_category']] = (int) ($categoryMix[$m['business_category']] ?? 0) + 1;
        }
        arsort($categoryMix);

        $serviceModuleOn = false;
        if ($user->role === 'district_staff') {
            $serviceModuleOn = app(AppSettingsService::class)->isEnabled('service_module.enabled');
        }

        return view('batches.read.show', [
            'batch' => $batch,
            'members' => $members,
            'stageMix' => $stageMix,
            'categoryMix' => $categoryMix,
            'hasCdoPdf' => $this->batches->hasCdoPdf($batch),
            'cdoOverdue' => $this->batches->cdoIsOverdue($batch),
            'cdoPending' => $this->batches->cdoIsPendingWithinWindow($batch),
            'effectiveDeadline' => $this->batches->effectiveDeadline($batch),
            'scope' => $scope,
            'routeIndex' => $this->routeIndex($user),
            'serviceModuleOn' => $serviceModuleOn,
        ]);
    }

    public function downloadOnboardingLetter(Request $request, OnboardingBatch $batch): StreamedResponse
    {
        $user = $request->user();
        $scope = $this->resolveScope($user);

        if ($scope['type'] === 'hub' && (int) $batch->hub_id !== $scope['hub_id']) {
            abort(403);
        }
        if ($scope['type'] === 'district' && (int) $batch->district_id !== $scope['district_id']) {
            abort(403);
        }
        if ($scope['type'] === 'none') {
            abort(403);
        }

        $doc = OnboardingBatchDocument::query()
            ->where('onboarding_batch_id', $batch->id)
            ->where('doc_type', HubBatchService::DOC_CDO)
            ->latest('id')
            ->first();

        if (! $doc || ! $doc->path || ! Storage::disk('local')->exists($doc->path)) {
            abort(404, 'Onboarding Letter not found.');
        }

        $filename = $doc->original_name ?: ('onboarding-letter-'.$batch->id.'.pdf');

        return Storage::disk('local')->download($doc->path, $filename);
    }

    private function indexLegacy(
        Request $request,
        User $user,
        array $scope,
        $hubs,
        $districts,
        ?int $filterHub,
        ?int $filterDistrict,
        string $searchQ,
    ): View {
        $base = DB::connection('legacy')
            ->table('rbi_onboarding_batches as ob')
            ->join('rbi_onboarded_applicants as oa', 'oa.onboarding_batch_id', '=', 'ob.id')
            ->join('rbi_applicant_details as d', 'd.application_id', '=', 'oa.application_id')
            ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id');

        $hasOnboardDistrict = Schema::connection('legacy')->hasColumn('rbi_onboarding_batches', 'onboard_district');
        $selectOnboardDistrict = $hasOnboardDistrict
            ? DB::raw('MAX(ob.onboard_district) as onboard_district')
            : DB::raw('NULL as onboard_district');

        if ($scope['type'] === 'district') {
            $districtModel = District::query()->find($scope['district_id']);
            if ($districtModel === null) {
                $base->whereRaw('1 = 0');
            } else {
                $names = $this->legacyDistrictDisplayNames($districtModel);
                $base->whereIn('d.district', $names);
            }
        } elseif ($scope['type'] === 'state') {
            if ($filterDistrict) {
                $dModel = District::query()->find($filterDistrict);
                if ($dModel !== null) {
                    $base->whereIn('d.district', $this->legacyDistrictDisplayNames($dModel));
                }
            } elseif ($filterHub) {
                $names = $this->legacyDistrictNamesForHub((int) $filterHub);
                if ($names === []) {
                    $base->whereRaw('1 = 0');
                } else {
                    $base->whereIn('d.district', $names);
                }
            }
        }

        if ($searchQ !== '') {
            $like = '%'.$searchQ.'%';
            $base->where(function ($q) use ($like, $searchQ) {
                $q->where('d.applicant_name', 'like', $like)
                    ->orWhere('d.phone', 'like', $like)
                    ->orWhere('a.application_no', 'like', $like)
                    ->orWhere('ob.batch_name', 'like', $like);
                if (ctype_digit($searchQ)) {
                    $id = (int) $searchQ;
                    $q->orWhere('d.application_id', $id)
                        ->orWhere('a.id', $id)
                        ->orWhere('ob.id', $id);
                }
            });
        }

        $totalLegacyBatches = (int) ((clone $base)
            ->select(DB::raw('COUNT(DISTINCT ob.id) as cnt'))
            ->value('cnt') ?? 0);

        $batches = (clone $base)
            ->groupBy('ob.id')
            ->orderByDesc('ob.id')
            ->select([
                'ob.id',
                DB::raw('MAX(ob.batch_name) as batch_name'),
                $selectOnboardDistrict,
                DB::raw('COUNT(DISTINCT oa.application_id) as member_count'),
            ])
            ->paginate(30)
            ->withQueryString();

        return view('batches.read.index', [
            'batches' => $batches,
            'hubs' => $hubs,
            'districts' => $districts,
            'scope' => $scope,
            'filters' => [
                'hub' => $filterHub,
                'district' => $filterDistrict,
                'status' => '',
                'q' => $searchQ,
                'source' => 'legacy',
            ],
            'totals' => [
                'total' => $totalLegacyBatches,
                'locked' => 0,
                'draft' => 0,
            ],
            'routeIndex' => $this->routeIndex($user),
            'routeShowName' => $this->routeShowName($user),
            'routeLegacyShowName' => $this->routeLegacyShowName($user),
            'batchSource' => 'legacy',
            'legacyTablesOk' => true,
            'legacyDbConfigured' => true,
            'legacyRequestedButUnavailable' => false,
        ]);
    }

    /**
     * @param  Builder<OnboardingBatch>  $query
     */
    private function applyPhase3ApplicantSearch($query, string $searchQ): void
    {
        $like = '%'.$searchQ.'%';
        $query->where(function ($outer) use ($like, $searchQ) {
            $outer->where('onboarding_batches.name', 'like', $like);

            if (ctype_digit($searchQ)) {
                $id = (int) $searchQ;
                $outer->orWhere('onboarding_batches.id', $id);
            }

            $outer->orWhereHas('batchCfas.cfaSubmission', function ($q) use ($like, $searchQ) {
                $q->where(function ($inner) use ($like, $searchQ) {
                    $inner->where('cfa_submissions.applicant_name', 'like', $like)
                        ->orWhere('cfa_submissions.phone', 'like', $like)
                        ->orWhere('cfa_submissions.application_no', 'like', $like);
                    if (ctype_digit($searchQ)) {
                        $inner->orWhere('cfa_submissions.id', (int) $searchQ);
                    }
                });
            });

            $outer->orWhereHas('draftCfas.cfaSubmission', function ($q) use ($like, $searchQ) {
                $q->where(function ($inner) use ($like, $searchQ) {
                    $inner->where('cfa_submissions.applicant_name', 'like', $like)
                        ->orWhere('cfa_submissions.phone', 'like', $like)
                        ->orWhere('cfa_submissions.application_no', 'like', $like);
                    if (ctype_digit($searchQ)) {
                        $inner->orWhere('cfa_submissions.id', (int) $searchQ);
                    }
                });
            });
        });
    }

    /**
     * @return list<string>
     */
    private function legacyDistrictDisplayNames(District $district): array
    {
        $canonical = trim((string) $district->name);
        $aliasesMap = (array) config('legacy_phase2.staff_import.district_aliases', []);
        $rawAliases = (array) ($aliasesMap[$canonical] ?? []);
        $names = [$canonical];
        foreach ($rawAliases as $alias) {
            $a = trim((string) $alias);
            if ($a !== '') {
                $names[] = $a;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function legacyDistrictNamesForHub(int $hubId): array
    {
        $out = [];
        $districts = District::query()->where('hub_id', $hubId)->get();
        foreach ($districts as $d) {
            foreach ($this->legacyDistrictDisplayNames($d) as $n) {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }

    private function hasLegacyBatchTables(): bool
    {
        if ((string) config('database.connections.legacy.database', '') === '') {
            return false;
        }
        try {
            return Schema::connection('legacy')->hasTable('rbi_onboarding_batches')
                && Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details')
                && Schema::connection('legacy')->hasTable('rbi_applications');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{type: string, hub_id?: int, district_id?: int}
     */
    private function resolveScope(User $user): array
    {
        return match ($user->role) {
            'state_admin' => ['type' => 'state'],
            'hub_admin' => ['type' => 'hub', 'hub_id' => (int) $user->hub_id],
            'district_staff' => [
                'type' => 'district',
                'district_id' => (int) $user->district_id,
                'hub_id' => (int) $user->hub_id,
            ],
            default => ['type' => 'none'],
        };
    }

    private function routeIndex(User $user): string
    {
        return match ($user->role) {
            'state_admin' => route('admin.batches.index'),
            'district_staff' => route('staff.batches.index'),
            'hub_admin' => route('hub.batches.index'),
            default => '#',
        };
    }

    private function routeShowName(User $user): string
    {
        return match ($user->role) {
            'state_admin' => 'admin.batches.show',
            'district_staff' => 'staff.batches.show',
            default => 'admin.batches.show',
        };
    }

    private function routeLegacyShowName(User $user): string
    {
        return match ($user->role) {
            'state_admin' => 'admin.batches.legacy.show',
            'district_staff' => 'staff.batches.legacy.show',
            default => 'admin.batches.legacy.show',
        };
    }
}
