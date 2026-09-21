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
                'title' => 'Starting a business',
                'hindi' => 'बिज़नेस शुरू करना',
                'emoji' => '🚀',
                'description' => 'How to start, choose a type, and grow a small business.',
                'description_hi' => 'बिज़नेस कैसे शुरू करें, कौन-सा प्रकार चुनें, और आगे कैसे बढ़ाएँ।',
                'videos' => [
                    self::playlist('सफल व्यवसाय की शुरुआत और संचालन', 'Starting and running a successful business', 'PLGd-uiTAXX5o'),
                    self::playlist('व्यवसाय के प्रकार', 'Types of business', 'PLdPbrPq-Sfa4'),
                    self::playlist('व्यवसाय की वृद्धि', 'Growing your business', 'PLK7R7Wp_a3aQ'),
                ],
            ],
            [
                'slug' => 'market-sales',
                'title' => 'Market & sales',
                'hindi' => 'बाज़ार और बिक्री',
                'emoji' => '🛒',
                'description' => 'Know your market, build your identity, and sell better.',
                'description_hi' => 'बाज़ार समझें, अपनी पहचान बनाएँ, और सही तरीके से बेचें।',
                'videos' => [
                    self::playlist('बाज़ार का अध्ययन', 'Studying the market', 'PLcqKiVU_zLkw'),
                    self::playlist('अपने व्यवसाय की पहचान बनाएं', 'Build your business identity', 'PLLMfhc4gUIJ8'),
                    self::playlist('अपने व्यवसाय की मार्केटिंग सीखें', 'Learn to market your business', 'PLax_jPbBkEh0'),
                    self::playlist('सही बिक्री से जुड़ी जानकारी', 'Selling the right way', 'PLHENQYL2I678'),
                    self::playlist('डिजिटल प्लेटफॉर्म के माध्यम से बाज़ार तक पहुँच', 'Reaching the market through digital platforms', 'PLcqnFrLK2Yv8'),
                    self::playlist('बाज़ार से जुड़ाव', 'Market linkages', 'PLbuUFmzj3TYA'),
                ],
            ],
            [
                'slug' => 'money-stock',
                'title' => 'Money, price & stock',
                'hindi' => 'पैसा, कीमत और स्टॉक',
                'emoji' => '💰',
                'description' => 'Cost, price, cash flow, profit, stock and simple accounts.',
                'description_hi' => 'लागत, कीमत, कैशफ्लो, लाभ, स्टॉक और हिसाब-किताब।',
                'videos' => [
                    self::playlist('लागत प्रबंधन', 'Cost management', 'PLEjPHvqqKfGU'),
                    self::playlist('कीमत तय कैसे करें', 'How to set a price', 'PLJ17HqWI3hDQ'),
                    self::playlist('स्टॉक और सामान का सही प्रबंधन', 'Managing stock and goods', 'PLS3A8IcvlhH0'),
                    self::playlist('कैशफ्लो का प्रबंधन', 'Managing cash flow', 'PLcYtZSFDjtCM'),
                    self::playlist('लाभ का सही प्रबंधन', 'Managing profit', 'PLCycru4EHv9Y'),
                    self::playlist('हिसाब-किताब', 'Bookkeeping', 'PLdPbrPq-Sfa4'),
                    self::playlist('व्यापार की स्थिति का आकलन', 'Assessing your business', 'PLeqMKWAilXJw'),
                ],
            ],
            [
                'slug' => 'banking-schemes',
                'title' => 'Banking, loans & schemes',
                'hindi' => 'बैंक, लोन और योजनाएँ',
                'emoji' => '🏦',
                'description' => 'Loans, government schemes, and simple banking tools.',
                'description_hi' => 'व्यावसायिक ऋण, सरकारी योजनाएँ, और बैंकिंग साधन।',
                'videos' => [
                    self::playlist('व्यावसायिक ऋण', 'Business loans', 'PLePP8EfK2ULI'),
                    self::playlist('व्यवसाय के लिए सरकारी योजनाएँ', 'Government schemes for business', 'PLJ1REZysXD7U'),
                    self::playlist('बैंकिंग और वित्तीय साधन', 'Banking and financial tools', 'PLFBZ5OSQ3uEc'),
                ],
            ],
            [
                'slug' => 'legal-rules',
                'title' => 'Legal & government rules',
                'hindi' => 'कानूनी और सरकारी नियम',
                'emoji' => '⚖️',
                'description' => 'Rules and government requirements for your business.',
                'description_hi' => 'व्यवसाय से जुड़े कानूनी और सरकारी नियम।',
                'videos' => [
                    self::playlist('व्यवसाय से जुड़े सभी कानूनी और सरकारी नियम', 'Legal and government rules for business', 'PLGvQY5Md3rO8'),
                ],
            ],
        ];
    }

    /**
     * @return list<array{title:string,type:string,size:string,description:string,url:string}>
     */
    public static function resourceDocuments(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function playlist(string $titleHi, string $titleEn, string $listId): array
    {
        return [
            'title' => $titleHi,
            'title_en' => $titleEn,
            'channel' => 'YouTube playlist',
            'playlist_id' => $listId,
            'youtube_id' => $listId,
            'url' => 'https://www.youtube.com/playlist?list='.$listId,
            'kind' => 'playlist',
        ];
    }
}
