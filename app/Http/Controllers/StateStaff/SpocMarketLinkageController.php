<?php

namespace App\Http\Controllers\StateStaff;

use App\Http\Controllers\Controller;
use App\Models\DistrictServiceSpoc;
use App\Models\MarketLinkagePartner;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\MarketLinkageWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpocMarketLinkageController extends Controller
{
    public function __construct(
        private AppSettingsService $settings,
        private MarketLinkageWorkflowService $workflow,
    ) {}

    public function show(Request $request, MarketLinkageSubmission $market_linkage): View
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertSubmissionInSpocDistrict($market_linkage, (int) $spoc->id);

        $market_linkage->load(['partners', 'submitter:id,name', 'district:id,name', 'spoc:id,name', 'approver:id,name', 'rejector:id,name']);

        return view('spoc.market-linkages.show', [
            'submission' => $market_linkage,
            'documentRoutePrefix' => 'spoc.market-linkages.document',
        ]);
    }

    public function approve(Request $request, MarketLinkageSubmission $market_linkage): RedirectResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertSubmissionInSpocDistrict($market_linkage, (int) $spoc->id);

        $this->workflow->approve($market_linkage, $spoc);

        return $this->redirectToQueue($request)
            ->with('status', 'Market linkage approved.');
    }

    public function sendBack(Request $request, MarketLinkageSubmission $market_linkage): RedirectResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertSubmissionInSpocDistrict($market_linkage, (int) $spoc->id);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $this->workflow->sendBack($market_linkage, $spoc, (string) $validated['note']);

        return $this->redirectToQueue($request)
            ->with('status', 'Market linkage sent back to district staff.');
    }

    public function reject(Request $request, MarketLinkageSubmission $market_linkage): RedirectResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertSubmissionInSpocDistrict($market_linkage, (int) $spoc->id);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $this->workflow->reject($market_linkage, $spoc, (string) $validated['note']);

        return $this->redirectToQueue($request)
            ->with('status', 'Market linkage rejected.');
    }

    public function downloadDocument(Request $request, MarketLinkageSubmission $market_linkage, MarketLinkagePartner $partner): StreamedResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertSubmissionInSpocDistrict($market_linkage, (int) $spoc->id);
        abort_unless((int) $partner->market_linkage_submission_id === (int) $market_linkage->id, 404);
        abort_unless($partner->hasDocument(), 404);

        $disk = Storage::disk((string) ($partner->document_disk ?: 'local'));
        $path = (string) $partner->document_path;
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, (string) ($partner->document_original_name ?: 'document'));
    }

    private function ensureModuleOn(): void
    {
        abort_unless(
            $this->settings->isEnabled('service_module.enabled'),
            403,
            'The service module is turned off. Ask your state admin to enable it under Admin → More → Service module settings.'
        );
    }

    private function spocOrAbort(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->role === 'state_staff', 403);

        return $u;
    }

    private function assertSubmissionInSpocDistrict(MarketLinkageSubmission $submission, int $spocUserId): void
    {
        $districtId = (int) $submission->district_id;
        abort_unless($districtId > 0, 404);

        $allowed = DistrictServiceSpoc::query()
            ->where('state_staff_user_id', $spocUserId)
            ->where('district_id', $districtId)
            ->exists();

        abort_unless($allowed, 403);
    }

    private function redirectToQueue(Request $request): RedirectResponse
    {
        $target = trim((string) $request->input('redirect_to', ''));
        if ($target !== '' && str_starts_with($target, '/')) {
            return redirect()->to($target);
        }

        return redirect()->route('spoc.service-cases.index', array_filter([
            'status' => ServiceCase::STATUS_PENDING_APPROVAL,
        ]));
    }
}
