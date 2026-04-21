<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\Hub;
use App\Models\User;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manages the District -> State Staff (SPOC) mapping used by the maker-checker
 * service flow. A district can only have ONE SPOC at a time (enforced by a
 * unique index on district_service_spocs.district_id), but a single SPOC user
 * may cover multiple districts.
 */
class DistrictSpocController extends Controller
{
    public function __construct(
        private AdminAuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        $hubs = Hub::query()
            ->with(['districts' => function ($q) {
                $q->orderBy('sort_order')->orderBy('name')->with('serviceSpoc.stateStaff');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $spocs = User::query()
            ->where('role', 'state_staff')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Per-SPOC district count for summary badges.
        $counts = DistrictServiceSpoc::query()
            ->select('state_staff_user_id', DB::raw('COUNT(*) as c'))
            ->groupBy('state_staff_user_id')
            ->pluck('c', 'state_staff_user_id');

        return view('admin.service-spocs.index', [
            'hubs' => $hubs,
            'spocs' => $spocs,
            'counts' => $counts,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*' => ['nullable', 'integer'],
        ]);

        $validSpocIds = User::query()
            ->where('role', 'state_staff')
            ->where('is_active', true)
            ->pluck('id')
            ->all();
        $spocIdSet = array_flip($validSpocIds);

        $districtIds = array_map('intval', array_keys($validated['assignments']));
        $districts = District::query()->whereIn('id', $districtIds)->pluck('name', 'id');

        $now = now();
        $changes = [
            'added' => [],
            'changed' => [],
            'cleared' => [],
        ];

        DB::transaction(function () use ($validated, $request, $spocIdSet, $districts, $now, &$changes) {
            foreach ($validated['assignments'] as $districtId => $spocId) {
                $districtId = (int) $districtId;
                if (! isset($districts[$districtId])) {
                    continue; // unknown district, skip silently
                }

                $existing = DistrictServiceSpoc::query()->where('district_id', $districtId)->first();
                $spocId = $spocId === null || $spocId === '' ? null : (int) $spocId;

                // Reject SPOC ids that aren't active state_staff.
                if ($spocId !== null && ! isset($spocIdSet[$spocId])) {
                    continue;
                }

                if ($spocId === null) {
                    if ($existing) {
                        $changes['cleared'][] = [
                            'district_id' => $districtId,
                            'district_name' => $districts[$districtId],
                            'previous_spoc_id' => $existing->state_staff_user_id,
                        ];
                        $existing->delete();
                    }
                    continue;
                }

                if (! $existing) {
                    DistrictServiceSpoc::query()->create([
                        'district_id' => $districtId,
                        'state_staff_user_id' => $spocId,
                        'assigned_by' => $request->user()->id,
                        'assigned_at' => $now,
                    ]);
                    $changes['added'][] = [
                        'district_id' => $districtId,
                        'district_name' => $districts[$districtId],
                        'spoc_id' => $spocId,
                    ];
                } elseif ((int) $existing->state_staff_user_id !== $spocId) {
                    $changes['changed'][] = [
                        'district_id' => $districtId,
                        'district_name' => $districts[$districtId],
                        'previous_spoc_id' => $existing->state_staff_user_id,
                        'new_spoc_id' => $spocId,
                    ];
                    $existing->state_staff_user_id = $spocId;
                    $existing->assigned_by = $request->user()->id;
                    $existing->assigned_at = $now;
                    $existing->save();
                }
            }
        });

        $touched = count($changes['added']) + count($changes['changed']) + count($changes['cleared']);

        if ($touched > 0) {
            $this->auditLogger->record(
                $request,
                'service_spocs.updated',
                DistrictServiceSpoc::class,
                null,
                null,
                $changes,
                "District SPOC assignments updated ({$touched} change(s))",
            );
        }

        $msg = $touched === 0
            ? 'No changes to save.'
            : "Saved {$touched} assignment change(s).";

        return redirect()->route('admin.service-spocs.index')->with('status', $msg);
    }
}
