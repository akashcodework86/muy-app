<?php

namespace App\Http\Controllers\Incubatee;

use App\Http\Controllers\Controller;
use App\Models\IncubateeServiceRequest;
use App\Models\Service;
use App\Services\ActivityLogger;
use App\Services\IncubateeServiceRequestNotifier;
use App\Support\IncubateeServiceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncubateeServiceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load(['cfaSubmission.district']);
        $submission = $user->cfaSubmission;
        if ($submission === null) {
            abort(404, 'No CFA profile is linked to this account.');
        }

        $requests = IncubateeServiceRequest::query()
            ->where('cfa_submission_id', $submission->id)
            ->with('service.category.parent')
            ->latest('id')
            ->limit(50)
            ->get();

        return view('incubatee.service-request', [
            'user' => $user,
            'submission' => $submission,
            'requests' => $requests,
            'serviceGroups' => IncubateeServiceCatalog::grouped(),
        ]);
    }

    public function store(Request $request, IncubateeServiceRequestNotifier $notifier, ActivityLogger $activity): RedirectResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $submission = $user->cfaSubmission;
        if ($submission === null) {
            abort(404, 'No CFA profile is linked to this account.');
        }

        $service = Service::query()->findOrFail((int) $validated['service_id']);

        $row = IncubateeServiceRequest::query()->create([
            'cfa_submission_id' => $submission->id,
            'requested_by_user_id' => $user->id,
            'service_id' => $service->id,
            'comment' => $validated['comment'] ?? null,
            'status' => IncubateeServiceRequest::STATUS_PENDING,
        ]);

        $count = $notifier->notify($row);
        $label = IncubateeServiceCatalog::serviceLabel($service);

        $activity->log(
            type: 'service.requested',
            title: ($user->name ?? 'An incubatee').' requested '.$label,
            actor: $user,
            subject: $row,
            districtId: $submission->district_id ? (int) $submission->district_id : null,
            meta: [
                'service_id' => $service->id,
                'service_label' => $label,
                'application_no' => $submission->application_no,
            ],
        );

        if ($count > 0) {
            return back()->with('status', __('incubatee.service_page.sent', ['count' => $count]));
        }

        return back()->with('status', __('incubatee.service_page.saved_no_recipients'));
    }

    public function cancel(Request $request, IncubateeServiceRequest $serviceRequest): RedirectResponse
    {
        $user = $request->user();
        $submission = $user->cfaSubmission;
        if ($submission === null || (int) $serviceRequest->cfa_submission_id !== (int) $submission->id) {
            abort(403);
        }
        if (! $serviceRequest->incubateeCanCancel()) {
            return back()->withErrors(['cancel' => 'This request can no longer be cancelled.']);
        }

        $serviceRequest->status = IncubateeServiceRequest::STATUS_CANCELLED;
        $serviceRequest->cancelled_at = now();
        $serviceRequest->cancelled_by_user_id = (int) $user->id;
        $serviceRequest->save();

        return back()->with('status', __('incubatee.service_page.cancelled'));
    }
}
