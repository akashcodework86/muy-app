<?php

namespace App\Support;

final class UdmitaKoshCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function categories(): array
    {
        return [
            [
                'slug' => 'starting-business',
                'title' => 'Starting a Business',
                'hindi' => 'बिज़नेस कैसे शुरू करें',
                'emoji' => '🚀',
                'description' => 'Foundation videos on idea validation, registration and first steps.',
                'description_hi' => 'विचार जाँच, रजिस्ट्रेशन और पहली सीढ़ी पर वीडियो।',
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
                'description_hi' => 'नकदी, हिसाब, MSME लोन और सरकारी योजनाएँ।',
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
                'description_hi' => 'डिजिटल मार्केटिंग, सोशल मीडिया और ग्राहक।',
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
                'description_hi' => 'GST, कंपनी रजिस्ट्रेशन, अनुबंध और IP।',
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
                'description_hi' => 'पिच डेक, निवेशक बैठक और विस्तार।',
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
    }

    /**
     * @return list<array{title:string,type:string,size:string,description:string,url:string}>
     */
    public static function resourceDocuments(): array
    {
        return [
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
    }
}
