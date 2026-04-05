<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Hub;
use App\Models\OnboardingBatch;
use App\Services\HubBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HubBatchController extends Controller
{
    public function __construct(
        private HubBatchService $batches
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $hub = Hub::query()->findOrFail((int) $user->hub_id);
        $districts = District::query()
            ->where('hub_id', $hub->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('hub.batches.index', [
            'hub' => $hub,
            'districts' => $districts,
            'stats' => [
                'blocked' => $this->batches->hubWriteBlocked($hub->id),
                'overdue_cdo' => $this->batches->countOverdueBatches($hub->id),
                'pending_cdo' => $this->batches->countPendingCdo($hub->id),
            ],
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        $action = (string) $request->input('action', '');
        $result = $this->batches->handleApi($action, $request->user(), $request->all());

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['error'] ?? 'Error'], 422);
        }

        return response()->json(array_merge(['ok' => true], $result['data'] ?? []));
    }

    public function uploadCdo(Request $request): RedirectResponse
    {
        $request->validate([
            'onboarding_batch_id' => ['required', 'integer', 'exists:onboarding_batches,id'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $user = $request->user();
        $hubId = (int) $user->hub_id;
        $batch = OnboardingBatch::query()->findOrFail((int) $request->input('onboarding_batch_id'));
        if ((int) $batch->hub_id !== $hubId || ! $batch->isLocked() || ! $batch->locked_at) {
            abort(403);
        }

        $this->batches->storeCdoDocument($batch, $user, $request->file('file'));

        return redirect()
            ->route('hub.batches.index')
            ->with('status', 'CDO signed PDF uploaded.');
    }
}
