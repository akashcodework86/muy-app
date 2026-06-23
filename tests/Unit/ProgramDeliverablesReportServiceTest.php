<?php

namespace Tests\Unit;

use App\Services\ProgramDeliverablesReportService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class ProgramDeliverablesReportServiceTest extends TestCase
{
    #[DataProvider('performanceToneProvider')]
    public function test_performance_tone_maps_achievement_pct_to_bands(?int $pct, ?string $expectedTone): void
    {
        $service = app(ProgramDeliverablesReportService::class);
        $method = new ReflectionMethod($service, 'performanceTone');
        $method->setAccessible(true);

        $this->assertSame($expectedTone, $method->invoke($service, $pct));
    }

    /**
     * @return array<string, array{0: ?int, 1: ?string}>
     */
    public static function performanceToneProvider(): array
    {
        return [
            'null pct' => [null, null],
            'good at 90' => [90, 'good'],
            'good above 90' => [100, 'good'],
            'warn at 60' => [60, 'warn'],
            'warn at 89' => [89, 'warn'],
            'critical below 60' => [59, 'critical'],
            'critical at zero' => [0, 'critical'],
        ];
    }
}
