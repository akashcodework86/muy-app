<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Support\TodayOnlyDate;
use App\Services\ServiceCaseRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IncubateeServiceCaseController extends Controller
{
    public function store(Request $request, CfaSubmission $cfa_submission, ServiceCaseRecorder $recorder): RedirectResponse
    {
        if (! $this->featureEnabled()) {
            return $this->disabledRedirect($cfa_submission);
        }

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
        if (! $this->featureEnabled()) {
            return $this->disabledRedirect($cfa_submission);
        }

        $this->assertOwnReferral($request, $cfa_submission);
        abort_unless((int) $service_case->cfa_submission_id === (int) $cfa_submission->id, 404);

        $validated = $request->validate([
            'reference_number' => ['nullable', 'string', 'max:191'],
            'delivered_on' => TodayOnlyDate::rulesAllowingExisting($service_case->delivered_on?->toDateString(), false),
            'payload' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        try {
            $recorder->complete($service_case, array_merge($validated, [
                'actor_id' => (int) $request->user()->id,
            ]), array_values($request->file('attachments', [])));
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
        $user = $request->user();
        $isOwnReferral = (int) $submission->referral_user_id === (int) $user->id;
        $sameDistrict = $user->district_id
            && (int) $submission->district_id === (int) $user->district_id;

        abort_unless(
            $isOwnReferral || $sameDistrict,
            403,
            'You can only access applications from your assigned district or your own referrals.'
        );
    }

    private function featureEnabled(): bool
    {
        return (bool) config('features.service_case_assignment', false);
    }

    private function disabledRedirect(CfaSubmission $submission): RedirectResponse
    {
        return redirect()
            ->route('staff.applications.show', $submission)
            ->with('status', 'Service assignment is being redesigned and is temporarily unavailable. Please wait for the new workflow.');
    }
}
