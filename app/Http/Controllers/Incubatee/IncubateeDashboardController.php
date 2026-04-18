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
            'cfaSubmission.serviceCases.service',
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

        $scalar = static function ($value, string $fallback = '—'): string {
            if ($value === null || $value === '') {
                return $fallback;
            }
            if (is_scalar($value)) {
                return (string) $value;
            }

            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        };

        $emailFromPayload = $payload['email'] ?? null;
        $displayEmail = (is_scalar($emailFromPayload) && trim((string) $emailFromPayload) !== '')
            ? (string) $emailFromPayload
            : ($user->email ?? '—');

        return view('incubatee.dashboard', [
            'user' => $user,
            'submission' => $submission,
            'payload' => $payload,
            'displayEmail' => $displayEmail,
            'displayFormStage' => $scalar($payload['form_stage'] ?? null),
            'displayProduct' => $scalar($payload['product'] ?? ($payload['business_category'] ?? null)),
            'batch' => $batch,
            'hubName' => $hubName,
            'serviceCases' => $cases,
            'servicesCompletedCount' => $completed,
            'servicesOpenCount' => $open,
        ]);
    }
}
