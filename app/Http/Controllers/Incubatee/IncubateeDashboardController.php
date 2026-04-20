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

    public function udmitaKosh(): View
    {
        $user = auth()->user();

        $categories = [
            [
                'slug' => 'starting-business',
                'title' => 'Starting a Business',
                'hindi' => 'बिज़नेस कैसे शुरू करें',
                'emoji' => '🚀',
                'description' => 'Foundation videos on idea validation, registration and first steps.',
                'videos' => [
                    [
                        'title' => 'How to Start a Business in India | Step by Step Guide (Hindi)',
                        'channel' => 'Dr. Vivek Bindra: Motivational Speaker',
                        'youtube_id' => 'Z4UMjRJTlEU',
                        'duration' => '18:42',
                    ],
                    [
                        'title' => 'Business Registration Process in India (Hindi)',
                        'channel' => 'Labour Law Advisor',
                        'youtube_id' => 'YxJ6e7YixIE',
                        'duration' => '14:08',
                    ],
                    [
                        'title' => 'How to Validate Your Business Idea (Hindi)',
                        'channel' => 'Think School',
                        'youtube_id' => 'Fq4p3-f0nyE',
                        'duration' => '12:30',
                    ],
                ],
            ],
            [
                'slug' => 'finance-funding',
                'title' => 'Finance & Funding',
                'hindi' => 'वित्त और फंडिंग',
                'emoji' => '💰',
                'description' => 'Cash flow, bookkeeping, MSME loans and government schemes.',
                'videos' => [
                    [
                        'title' => 'MSME Loan Yojana 2025 — Complete Process (Hindi)',
                        'channel' => 'MyOnlineCA',
                        'youtube_id' => 'b1H9ftfMdHQ',
                        'duration' => '16:20',
                    ],
                    [
                        'title' => 'Mudra Loan — How to Apply Step by Step (Hindi)',
                        'channel' => 'Finance With Sharan',
                        'youtube_id' => 'Ou8gVn0MhB8',
                        'duration' => '09:55',
                    ],
                    [
                        'title' => 'Bookkeeping & Accounting Basics for Small Business (Hindi)',
                        'channel' => 'Labour Law Advisor',
                        'youtube_id' => 'Xn3aXkYhKm0',
                        'duration' => '22:10',
                    ],
                ],
            ],
            [
                'slug' => 'marketing',
                'title' => 'Marketing & Branding',
                'hindi' => 'मार्केटिंग और ब्रांडिंग',
                'emoji' => '📣',
                'description' => 'Digital marketing, social media and customer acquisition.',
                'videos' => [
                    [
                        'title' => 'Digital Marketing for Beginners (Hindi)',
                        'channel' => 'WsCube Tech',
                        'youtube_id' => 'nU-IIXBWlS4',
                        'duration' => '21:45',
                    ],
                    [
                        'title' => 'Instagram Marketing for Small Business (Hindi)',
                        'channel' => 'Social Seller Academy',
                        'youtube_id' => 'X1r-V7Q5J4A',
                        'duration' => '15:12',
                    ],
                    [
                        'title' => 'Branding 101 — Build a Brand from Zero (Hindi)',
                        'channel' => 'Think School',
                        'youtube_id' => 'eR3N9kAH1hE',
                        'duration' => '17:00',
                    ],
                ],
            ],
            [
                'slug' => 'legal-compliance',
                'title' => 'Legal & Compliance',
                'hindi' => 'कानूनी और अनुपालन',
                'emoji' => '⚖️',
                'description' => 'GST, company registration, contracts and IP basics.',
                'videos' => [
                    [
                        'title' => 'GST Registration Process for Business (Hindi)',
                        'channel' => 'CA Guru Ji',
                        'youtube_id' => 'XoyT60aT0fg',
                        'duration' => '19:25',
                    ],
                    [
                        'title' => 'Private Limited vs LLP vs Proprietorship (Hindi)',
                        'channel' => 'MyOnlineCA',
                        'youtube_id' => 'R5Ef8UJ6n4A',
                        'duration' => '11:40',
                    ],
                ],
            ],
            [
                'slug' => 'pitch-growth',
                'title' => 'Pitch & Growth',
                'hindi' => 'पिच और ग्रोथ',
                'emoji' => '📈',
                'description' => 'Pitch decks, investor meetings and scaling your venture.',
                'videos' => [
                    [
                        'title' => 'How to Make a Killer Pitch Deck (Hindi)',
                        'channel' => 'WarikooShow',
                        'youtube_id' => 'CBzVdxxp9M0',
                        'duration' => '13:18',
                    ],
                    [
                        'title' => 'Shark Tank India — Pitches Decoded (Hindi)',
                        'channel' => 'Finance With Sharan',
                        'youtube_id' => 'I4yRqk9mY1E',
                        'duration' => '20:02',
                    ],
                ],
            ],
        ];

        $documents = [
            [
                'title' => 'MSME Udyam Registration — Quick Guide',
                'type' => 'PDF',
                'size' => '420 KB',
                'description' => 'Step-by-step walkthrough for Udyam registration with screenshots.',
                'url' => 'https://udyamregistration.gov.in/docs/Udyam-Registration-Booklet.pdf',
            ],
            [
                'title' => 'Business Model Canvas — Template',
                'type' => 'PPT',
                'size' => '280 KB',
                'description' => 'Editable 9-block canvas for mapping your venture.',
                'url' => 'https://www.strategyzer.com/library/the-business-model-canvas',
            ],
            [
                'title' => 'Simple Cashflow Sheet (90 days)',
                'type' => 'XLSX',
                'size' => '95 KB',
                'description' => 'Plug-and-play cash-in / cash-out tracker for early-stage ventures.',
                'url' => 'https://docs.google.com/spreadsheets/d/1AmXn3ndXOBPs6RFQ8Iyrk2H2QVzQhLQxOw8vEgx-example/edit',
            ],
            [
                'title' => 'Pitch Deck Starter — 10 slide template',
                'type' => 'PPT',
                'size' => '1.1 MB',
                'description' => 'Investor-ready pitch deck layout inspired by Sequoia template.',
                'url' => 'https://www.sequoiacap.com/article/writing-a-business-plan/',
            ],
        ];

        return view('incubatee.udmita-kosh', [
            'user' => $user,
            'categories' => $categories,
            'documents' => $documents,
        ]);
    }
}
