<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Models\OnboardingBatchCfa;
use App\Models\OnboardingBatchDraftCfa;
use App\Models\User;
use App\Services\HubBatchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

        $query = OnboardingBatch::query()
            ->with(['district:id,name,hub_id', 'hub:id,name']);

        if ($scope['type'] === 'hub') {
            $query->where('hub_id', $scope['hub_id']);
        } elseif ($scope['type'] === 'district') {
            $query->where('district_id', $scope['district_id']);
        }

        // Optional filters (state admin can narrow)
        $filterHub = $request->integer('hub') ?: null;
        $filterDistrict = $request->integer('district') ?: null;
        $filterStatus = $request->string('status')->toString();

        if ($scope['type'] === 'state' && $filterHub) {
            $query->where('hub_id', $filterHub);
        }
        if (in_array($scope['type'], ['state', 'hub'], true) && $filterDistrict) {
            $query->where('district_id', $filterDistrict);
        }
        if (in_array($filterStatus, ['draft', 'locked'], true)) {
            $query->where('status', $filterStatus);
        }

        $batches = $query->orderByDesc('id')->paginate(30)->withQueryString();

        // Decorate each batch with member counts & compliance flags (in-memory; small page size).
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

        // Totals for the header strip
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
            ],
            'totals' => $totals,
            'routeIndex' => $this->routeIndex($user),
            'routeShowName' => $this->routeShowName($user),
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
        ]);
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
}
