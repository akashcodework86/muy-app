<?php

namespace Tests\Unit;

use App\Services\ReviewPpt\ReviewPptTemplateExport;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ReviewPptTemplateExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $root = dirname(__DIR__, 2);
        $app = new Application($root);
        $app->instance('config', new Repository(['review_ppt' => require $root.'/config/review_ppt.php']));
    }

    public function test_state_totals_and_regional_rows_use_the_same_district_counts(): void
    {
        $template = base_path('resources/templates/review-ppt/muy-review-2026.pptx');
        $before = hash_file('sha256', $template);
        $slugs = array_merge(config('review_ppt.kumaon'), config('review_ppt.garhwal'));
        $serials = array_merge(config('review_ppt.key_indicators'), config('review_ppt.non_key_indicators'));
        $data = ['districts' => [], 'targets' => [], 'achievements' => []];
        foreach ($slugs as $slug) {
            $data['districts'][$slug] = ucwords(str_replace('-', ' ', $slug));
            foreach ($serials as $serial) {
                $data['targets'][$slug][$serial] = 100;
                $data['achievements'][$slug][$serial] = $slug === 'almora' ? 120 : 70;
            }
        }
        $output = tempnam(sys_get_temp_dir(), 'review-test-');

        try {
            (new ReviewPptTemplateExport($template))->write($data, Carbon::parse('2026-09-14'), 2, $output);
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($output) === true);
            $this->assertSame(5, count(array_filter(array_keys(iterator_to_array($this->zipEntries($zip))),
                fn ($name) => preg_match('~^ppt/slides/slide[1-5]\.xml$~', $name))));

            $slide1 = $this->slideXPath($zip->getFromName('ppt/slides/slide1.xml'));
            $this->assertSame('1300', $slide1->evaluate('string((//a:tbl)[1]/a:tr[15]/a:tc[3]//a:t)'));
            $this->assertSame('960', $slide1->evaluate('string((//a:tbl)[1]/a:tr[15]/a:tc[4]//a:t)'));
            $this->assertSame('1300', $slide1->evaluate('string((//a:tbl)[2]/a:tr[15]/a:tc[3]//a:t)'));
            $this->assertSame('960', $slide1->evaluate('string((//a:tbl)[2]/a:tr[15]/a:tc[4]//a:t)'));
            $this->assertSame('120', $slide1->evaluate('string((//a:tbl)[1]/a:tr[2]/a:tc[4]//a:t)'));
            $this->assertStringContainsString('14-09-2026', $zip->getFromName('ppt/slides/slide2.xml'));

            $slide2 = $this->slideXPath($zip->getFromName('ppt/slides/slide2.xml'));
            $this->assertSame('120', $slide2->evaluate('string((//a:tbl)[1]/a:tr[3]/a:tc[4]//a:t)'));
            $this->assertSame('70', $slide2->evaluate('string((//a:tbl)[1]/a:tr[3]/a:tc[6]//a:t)'));
            $zip->close();
            $this->assertSame($before, hash_file('sha256', $template));
        } finally {
            @unlink($output);
        }
    }

    private function slideXPath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

        return $xpath;
    }

    /** @return \Generator<string, string> */
    private function zipEntries(ZipArchive $zip): \Generator
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            yield $name => $name;
        }
    }
}
