<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Services\ServiceCaseRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IncubateeServiceCaseController extends Controller
{
    public function store(Request $request, CfaSubmission $cfa_submission, ServiceCaseRecorder $recorder): RedirectResponse
    {
        $this->assertOwnReferral($request, $cfa_submission);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'payload' => ['nullable', 'array'],
        ]);

        $service = Service::query()->whereKey((int) $validated['service_id'])->firstOrFail();

        try {
            $recorder->create(
                $cfa_submission,
                $service,
                (int) $request->user()->id,
                is_array($validated['payload'] ?? null) ? $validated['payload'] : []
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('staff.applications.show', $cfa_submission)
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('staff.applications.show', $cfa_submission)
            ->with('status', 'Service case added.');
    }

    public function complete(Request $request, CfaSubmission $cfa_submission, ServiceCase $service_case, ServiceCaseRecorder $recorder): RedirectResponse
    {
        $this->assertOwnReferral($request, $cfa_submission);
        abort_unless((int) $service_case->cfa_submission_id === (int) $cfa_submission->id, 404);

        $validated = $request->validate([
            'reference_number' => ['nullable', 'string', 'max:191'],
            'payload' => ['nullable', 'array'],
        ]);

        try {
            $recorder->complete($service_case, $validated);
        } catch (ValidationException $e) {
            return redirect()
                ->route('staff.applications.show', $cfa_submission)
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('staff.applications.show', $cfa_submission)
            ->with('status', 'Case marked completed.');
    }

    private function assertOwnReferral(Request $request, CfaSubmission $submission): void
    {
        abort_unless(
            (int) $submission->referral_user_id === (int) $request->user()->id,
            403,
            'You can only access applications submitted through your referral link.'
        );
    }
}
