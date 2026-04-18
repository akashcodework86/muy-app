<?php

namespace App\Http\Controllers\Incubatee;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\ServiceCase;
use Illuminate\View\View;

class IncubateeDashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user()->load([
            'cfaSubmission.district',
            'cfaSubmission.fiscalYear',
            'cfaSubmission.onboardingBatchMembership.batch.hub',
            'cfaSubmission.serviceCases.service.category.parent',
        ]);

        /** @var CfaSubmission|null $submission */
        $submission = $user->cfaSubmission;
        if ($submission === null) {
            abort(404, 'No CFA profile is linked to this account.');
        }

        $cases = $submission->serviceCases;
        $completed = $cases->where('status', ServiceCase::STATUS_COMPLETED)->count();
        $open = $cases->where('status', ServiceCase::STATUS_OPEN)->count();

        $payload = is_array($submission->payload) ? $submission->payload : [];
        $batch = $submission->onboardingBatchMembership?->batch;
        $hubName = $batch?->hub?->name;

        return view('incubatee.dashboard', [
            'user' => $user,
            'submission' => $submission,
            'payload' => $payload,
            'batch' => $batch,
            'hubName' => $hubName,
            'serviceCases' => $cases,
            'servicesCompletedCount' => $completed,
            'servicesOpenCount' => $open,
        ]);
    }
}
