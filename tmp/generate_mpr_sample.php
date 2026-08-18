<?php

use App\Services\Reports\MonthlyProgressReportWordExport;
use Carbon\Carbon;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source = 'C:/Users/hp/Downloads/MUY-MPR-August-2026 (1).doc';
$html = file_get_contents($source);
if ($html === false) {
    throw new RuntimeException('Downloaded compatibility document not found.');
}

$dom = new DOMDocument;
libxml_use_internal_errors(true);
$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($dom);
$text = static fn (DOMNode $node): string => trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
$number = static fn (string $value): ?float => is_numeric(str_replace([',', '%'], '', $value)) ? (float) str_replace([',', '%'], '', $value) : null;

$rows = [];
$quantitative = $xpath->query("//section[contains(@class,'page')][h1[contains(normalize-space(.),'2. Quantitative Progress')]]//tbody/tr");
foreach ($quantitative ?: [] as $tr) {
    $cells = $xpath->query('./td', $tr);
    if ($cells === false || $cells->length < 2) {
        continue;
    }
    $serial = $text($cells->item(0));
    $name = $text($cells->item(1));
    $heading = $cells->length < 9;
    $rows[] = $heading ? [
        'row_type' => str_contains($serial, '.') ? 'subcategory' : 'pillar',
        'serial' => $serial,
        'name' => $name,
    ] : [
        'row_type' => 'indicator',
        'serial' => $serial,
        'name' => $name,
        'indicator_type' => $text($cells->item(2)),
        'target' => $number($text($cells->item(3))),
        'target_label' => $number($text($cells->item(3))) === null ? $text($cells->item(3)) : null,
        'achievement' => $number($text($cells->item(4))),
        'achievement_pct' => $number($text($cells->item(5))),
        'cumul_target' => $number($text($cells->item(6))),
        'cumul_achievement' => $number($text($cells->item(7))),
        'cumul_achievement_pct' => $number($text($cells->item(8))),
    ];
}

$districtRows = [];
$districtTableRows = $xpath->query("//section[contains(@class,'page')][h1[contains(normalize-space(.),'3. District-wise Progress')]]//tbody/tr");
foreach ($districtTableRows ?: [] as $tr) {
    $cells = $xpath->query('./td', $tr);
    if ($cells === false || $cells->length < 4) {
        continue;
    }
    $districtRows[] = [
        'district' => $text($cells->item(1)),
        'cfa' => (int) str_replace(',', '', $text($cells->item(2))),
        'onboarding' => (int) str_replace(',', '', $text($cells->item(3))),
    ];
}

$photos = [];
$imageNodes = $xpath->query("//section[contains(@class,'page')][h1[contains(normalize-space(.),'5. Field Highlights')]]//td[contains(@class,'photo')]");
$photoDir = __DIR__.'/mpr-compare/extracted-photos';
if (! is_dir($photoDir)) {
    mkdir($photoDir, 0777, true);
}
foreach ($imageNodes ?: [] as $index => $cell) {
    $image = $xpath->query('.//img', $cell)?->item(0);
    $paragraph = $xpath->query('.//p', $cell)?->item(0);
    if (! $image instanceof DOMElement || ! $paragraph instanceof DOMNode) {
        continue;
    }
    $src = $image->getAttribute('src');
    if (! preg_match('#^data:([^;]+);base64,(.+)$#s', $src, $matches)) {
        continue;
    }
    $binary = base64_decode($matches[2], true);
    if ($binary === false) {
        continue;
    }
    $path = $photoDir.'/photo-'.($index + 1).'.jpg';
    file_put_contents($path, $binary);
    $strong = $xpath->query('.//b|.//strong', $paragraph)?->item(0);
    $span = $xpath->query('.//span', $paragraph)?->item(0);
    $caption = $strong instanceof DOMNode ? $text($strong) : 'Field highlight';
    [$section, $title] = array_pad(array_map('trim', explode(' - ', $caption, 2)), 2, 'Field activity');
    $meta = $span instanceof DOMNode ? $text($span) : '';
    [$district, $date] = array_pad(array_map('trim', explode('|', $meta, 2)), 2, '');
    $photos[] = compact('path', 'section', 'title', 'district', 'date');
}

$app->make(MonthlyProgressReportWordExport::class)->save(
    __DIR__.'/mpr-compare/MUY-MPR-August-2026-fixed.docx',
    Carbon::create(2026, 8, 1),
    'FY 2026-27',
    $rows,
    $districtRows,
    $photos,
);

echo 'rows='.count($rows).' districts='.count($districtRows).' photos='.count($photos).PHP_EOL;
