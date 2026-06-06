<?php

namespace Tests\Unit;

use App\Services\MarketLinkagePartnerCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketLinkagePartnerCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_partner_key_merges_compound_fresh_point_variants(): void
    {
        $service = app(MarketLinkagePartnerCatalogService::class);

        $short = 'Fresh Point Distribution Company Haldwani';
        $long = 'Fresh Point Distribution Company Haldwani and Supreme mart Haldwani store linkage';

        $this->assertSame(
            $service->normalizePartnerKey($short),
            $service->normalizePartnerKey($long)
        );
    }

    public function test_count_unique_partner_keys_treats_similar_names_as_one(): void
    {
        $service = app(MarketLinkagePartnerCatalogService::class);

        $count = $service->countUniquePartnerKeys([
            'Fresh Point Distribution Company Haldwani',
            'Fresh Point Distribution Company Haldwani and Supreme mart Haldwani store linkage',
            'Amazon India',
            'amazon india',
        ]);

        $this->assertSame(2, $count);
    }

    public function test_options_include_whatsapp_business_canonical_label(): void
    {
        if (! $this->legacyReady()) {
            $this->markTestSkipped('Legacy DB not configured for rbi_service_partners.');
        }

        $options = app(MarketLinkagePartnerCatalogService::class)->options();

        $this->assertContains('WhatsApp Business', $options);
        $this->assertNotContains('Whatsp Business', $options);
    }

    public function test_options_are_unique_and_sorted(): void
    {
        if (! $this->legacyReady()) {
            $this->markTestSkipped('Legacy DB not configured for rbi_service_partners.');
        }

        $options = app(MarketLinkagePartnerCatalogService::class)->options();
        $sorted = $options;
        natcasesort($sorted);
        $sorted = array_values($sorted);

        $this->assertGreaterThan(50, count($options));
        $this->assertSame(count($options), count(array_unique($options)));
        $this->assertSame($sorted, array_values($options));
    }

    private function legacyReady(): bool
    {
        try {
            return (string) config('database.connections.legacy.database') !== ''
                && DB::connection('legacy')->getSchemaBuilder()->hasTable('rbi_service_partners');
        } catch (\Throwable) {
            return false;
        }
    }
}
