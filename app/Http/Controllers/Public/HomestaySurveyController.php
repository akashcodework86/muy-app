<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\HomestaySurveyResponse;
use App\Services\AppSettingsService;
use App\Services\HomestaySurveyLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomestaySurveyController extends Controller
{
    public function __construct(
        private HomestaySurveyLookupService $lookup,
        private AppSettingsService $settings,
    ) {}

    public function show(): View
    {
        return view('public.homestay-survey.show', [
            'options' => config('homestay_survey'),
            'prefillLocked' => $this->settings->isEnabled('homestay_survey.prefill_locked'),
        ]);
    }

    public function thanks(Request $request): View|RedirectResponse
    {
        if (! $request->session()->pull('homestay_survey.thanks_ok')) {
            return redirect()->route('homestay-survey.show');
        }

        return view('public.homestay-survey.thanks', [
            'name' => (string) $request->session()->pull('homestay_survey.thanks_name', ''),
        ]);
    }

    public function alreadySubmitted(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->pull('homestay_survey.already_phone');

        if ($phone === null) {
            $queryPhone = preg_replace('/\D+/', '', (string) $request->query('phone', '')) ?? '';
            if (preg_match('/^[6-9]\d{9}$/', $queryPhone)) {
                return redirect()
                    ->route('homestay-survey.already-submitted')
                    ->with('homestay_survey.already_phone', $queryPhone);
            }

            return redirect()->route('homestay-survey.show');
        }

        return view('public.homestay-survey.already-submitted', [
            'phone' => $phone,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ]);

        $result = $this->lookup->resolve($validated['phone']);

        if ($result['status'] === 'ok') {
            $summary = $result['profile']['summary'] ?? [];

            return response()->json([
                'ok' => true,
                'status' => 'ok',
                'profile' => [
                    'phase' => $result['profile']['phase'] ?? null,
                    'application_no' => $result['profile']['application_no'] ?? null,
                    'source_id' => $result['profile']['source_id'] ?? null,
                    'summary' => $summary,
                ],
                'prefill' => $result['prefill'] ?? [],
                'prefill_locked' => $this->settings->isEnabled('homestay_survey.prefill_locked'),
            ]);
        }

        $code = match ($result['status']) {
            'already_submitted' => 409,
            'invalid' => 422,
            default => 404,
        };

        return response()->json([
            'ok' => false,
            'status' => $result['status'],
            'message' => $result['message'] ?? 'Unable to continue.',
            'redirect' => ($result['status'] ?? '') === 'already_submitted'
                ? route('homestay-survey.already-submitted', ['phone' => $validated['phone']])
                : null,
        ], $code);
    }

    public function store(Request $request): RedirectResponse
    {
        $phone = (string) $request->input('phone', '');
        $phone = preg_replace('/\D+/', '', $phone) ?? '';

        if (! preg_match('/^[6-9]\d{9}$/', $phone)) {
            return back()->withInput()->withErrors(['phone' => 'Enter a valid 10-digit mobile number.']);
        }

        if (HomestaySurveyResponse::query()->where('phone', $phone)->exists()) {
            return redirect()
                ->route('homestay-survey.already-submitted')
                ->with('homestay_survey.already_phone', $phone);
        }

        $resolved = $this->lookup->resolve($phone);
        if (($resolved['status'] ?? '') !== 'ok') {
            return back()->withInput()->withErrors([
                'phone' => $resolved['message'] ?? 'This mobile number was not found as a Homestay incubatee.',
            ]);
        }

        $answers = $request->input('answers', []);
        if (! is_array($answers)) {
            $answers = [];
        }

        foreach ($answers as $key => $value) {
            if (is_array($value)) {
                $answers[$key] = array_values(array_filter(array_map(
                    static fn ($v) => is_string($v) ? trim($v) : $v,
                    $value
                ), static fn ($v) => $v !== null && $v !== ''));
            } elseif (is_string($value)) {
                $answers[$key] = trim($value);
            }
        }

        $answers['consent'] = (bool) $request->boolean('consent');
        if (! $answers['consent']) {
            return back()->withInput()->withErrors(['consent' => 'Consent is required to submit the survey.']);
        }

        $answers['phone'] = $phone;

        $profile = $resolved['profile'];
        $prefill = $resolved['prefill'] ?? [];
        $name = (string) ($answers['respondent_name'] ?? ($prefill['respondent_name'] ?? ''));

        try {
            HomestaySurveyResponse::query()->create([
                'phone' => $phone,
                'phase' => $profile['phase'] ?? null,
                'source_id' => $profile['source_id'] ?? null,
                'application_no' => $profile['application_no'] ?? null,
                'applicant_name' => $name !== '' ? $name : null,
                'district' => $answers['district'] ?? ($prefill['district'] ?? null),
                'prefill_snapshot' => $prefill,
                'answers' => $answers,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'submitted_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return redirect()
                ->route('homestay-survey.already-submitted')
                ->with('homestay_survey.already_phone', $phone);
        }

        return redirect()
            ->route('homestay-survey.thanks')
            ->with('homestay_survey.thanks_ok', true)
            ->with('homestay_survey.thanks_name', $name);
    }
}
