<?php

namespace Tests\Unit;

use App\Services\DataCentre\ProgramDataCentreService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DataCentreAgeTest extends TestCase
{
    public function test_age_boundaries_and_invalid_dates(): void
    {
        $container = new \Illuminate\Container\Container;
        $container->instance('config', new \Illuminate\Config\Repository(['legacy_phase1' => require __DIR__.'/../../config/legacy_phase1.php']));
        \Illuminate\Container\Container::setInstance($container);
        Carbon::setTestNow(Carbon::parse('2026-09-16', 'Asia/Kolkata'));
        try {
            $reflection = new ReflectionClass(ProgramDataCentreService::class);
            $service = $reflection->newInstanceWithoutConstructor();
            $method = $reflection->getMethod('normAge');
            foreach ([
                '2008-09-17' => 'Below 18', '2008-09-16' => '18–25',
                '2000-09-17' => '18–25', '2000-09-16' => '26–35',
                '1990-09-16' => '36–45', '1980-09-16' => '46–60',
                '1966-09-16' => '46–60', '1965-09-16' => '60+',
                '16/09/2000' => '26–35', '2026-09-17' => 'NA/Blank',
                '2000-02-30' => 'NA/Blank', '' => 'NA/Blank',
                '0000-00-00' => 'NA/Blank', '1800-01-01' => 'NA/Blank',
                '__organisation__' => 'Not applicable (SHG/CBO)',
            ] as $dob => $expected) {
                $this->assertSame($expected, $method->invoke($service, $dob), $dob);
            }
            foreach (['p1Age' => 2, 'p2Age' => 3, 'p3Age' => 4] as $property => $count) {
                $reflection->getProperty($property)->setValue($service, ['dehradun' => ['2000-09-16' => $count, '' => 1]]);
            }
            $rows = $service->ageState(['Dehradun']);
            $this->assertSame(12, $rows[3]['total']);
            $this->assertSame(9, $rows[3]['26–35']);
            $district = $service->ageByDistrict(['Dehradun']);
            $this->assertSame(12, $district[0]['total']);
            $this->assertSame(12, $district[1]['total']);
            $phase3 = $service->ageStatePhase3Only(['Dehradun']);
            $this->assertCount(1, $phase3);
            $this->assertSame(5, $phase3[0]['total']);
        } finally {
            Carbon::setTestNow();
            \Illuminate\Container\Container::setInstance(null);
        }
    }
}
