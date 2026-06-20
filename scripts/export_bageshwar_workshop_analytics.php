<?php

/**
 * One-off / repeatable export: Bageshwar FY 2026-27 Phase 3 workshop analytics (Excel).
 *
 * Usage: php scripts/export_bageshwar_workshop_analytics.php
 */

use App\Models\FiscalYear;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const DISTRICT_NAME = 'Bageshwar';
const BLOCKS = ['Garur', 'Kapkote', 'Bageshwar'];

$fy = FiscalYear::phase3Default();
$district = DB::table('districts')->where('name', DISTRICT_NAME)->first();
if (! $district) {
    fwrite(STDERR, "District not found: ".DISTRICT_NAME.PHP_EOL);
    exit(1);
}

$districtId = (int) $district->id;
$total = baseQuery($districtId, $fy)->count();

$outDir = storage_path('app/exports');
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
$filename = 'Bageshwar_Phase3_FY2026-27_Workshop_Analytics_'.date('Y-m-d').'.xlsx';
$outPath = $outDir.'/'.$filename;

$spreadsheet = new Spreadsheet;
$spreadsheet->getProperties()
    ->setTitle('Bageshwar Phase 3 Workshop Analytics')
    ->setSubject('FY 2026-27 CFA analytics')
    ->setDescription('District and block-wise breakdown for incubatee workshop');

// ── Sheet 1: Summary ─────────────────────────────────────────────────────
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Summary');
writeTitle($sheet, 'Bageshwar — Phase 3 Workshop Analytics (FY 2026-27)', 1, 4);
$meta = [
    ['District', DISTRICT_NAME],
    ['Fiscal Year', $fy?->name ?? 'FY 2026-27'],
    ['Total Applications', $total],
    ['Generated At', now()->timezone('Asia/Kolkata')->format('d M Y, g:i A IST')],
    ['Data Source', 'cfa_submissions (Phase 3, excl. legacy_phase2)'],
];
$r = 3;
foreach ($meta as [$label, $value]) {
    $sheet->setCellValue('A'.$r, $label);
    $sheet->setCellValue('B'.$r, $value);
    $r++;
}

$r += 1;
writeSectionHeader($sheet, 'Key Highlights', $r, 2);
$r++;
$female = countWhere($districtId, $fy, "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.gender')) = 'Female'");
$male = countWhere($districtId, $fy, "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.gender')) = 'Male'");
$agri = countWhere($districtId, $fy, "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.business_category')) = 'Agri Allied'");
$shg = countWhere($districtId, $fy, "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.category')) = 'SHG'");
$cbo = countWhere($districtId, $fy, "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.category')) = 'CBO'");
$lakhpati = countWhere($districtId, $fy, "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.lakhpati')) = 'Yes'");
$shgMembers = countWhere($districtId, $fy, "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.is_member')) = 'Yes'");
$onboarded = (int) DB::table('onboarding_batch_cfa as obc')
    ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
    ->where('ob.district_id', $districtId)
    ->where('ob.status', 'locked')
    ->count();

$highlights = [
    ['Women entrepreneurs', $female, pct($female, $total)],
    ['Men entrepreneurs', $male, pct($male, $total)],
    ['Agri Allied sector', $agri, pct($agri, $total)],
    ['SHG applications', $shg, pct($shg, $total)],
    ['CBO applications', $cbo, pct($cbo, $total)],
    ['Lakhpati Didi (Yes)', $lakhpati, pct($lakhpati, $total)],
    ['SHG/CBO members (is_member=Yes)', $shgMembers, pct($shgMembers, $total)],
    ['Onboarded (locked batches)', $onboarded, ''],
];
$sheet->fromArray(['Metric', 'Count', '% of Total'], null, 'A'.$r);
styleHeaderRow($sheet, $r, 3);
$r++;
foreach ($highlights as $row) {
    $sheet->fromArray($row, null, 'A'.$r);
    $r++;
}
autoWidth($sheet, 'A', 'C');

// ── Sheet 2: Business Sectors ────────────────────────────────────────────
$sectors = groupedCounts($districtId, $fy, '$.business_category', 'sector');
writeCountSheet($spreadsheet, 'Business Sectors', 'Business Sector', $sectors, $total);

// ── Sheet 3: Top Products ──────────────────────────────────────────────────
$products = groupedCounts($districtId, $fy, '$.product', 'product');
writeCountSheet($spreadsheet, 'Top Products', 'Product', $products, $total);

// ── Sheet 4: Applicant Category ────────────────────────────────────────────
$categories = groupedCounts($districtId, $fy, '$.category', 'category');
writeCountSheet($spreadsheet, 'Applicant Category', 'Category', $categories, $total);

// ── Sheet 5: Lakhpati Didi ─────────────────────────────────────────────────
$lakhpatiRows = groupedCounts($districtId, $fy, '$.lakhpati', 'status');
writeCountSheet($spreadsheet, 'Lakhpati Didi', 'Lakhpati Status', $lakhpatiRows, $total);

$lakSheet = $spreadsheet->getSheetByName('Lakhpati Didi');
$start = count($lakhpatiRows) + 4;
writeSectionHeader($lakSheet, 'Lakhpati Didi (Yes) — Category & Sector', $start, 4);
$start++;
$lakSheet->fromArray(['Category', 'Sector', 'Count'], null, 'A'.$start);
styleHeaderRow($lakSheet, $start, 3);
$start++;
$lakDetail = baseQuery($districtId, $fy)
    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.lakhpati')) = 'Yes'")
    ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.category')) as cat")
    ->selectRaw("COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.business_category'))), ''), 'Not specified') as sector")
    ->selectRaw('COUNT(*) as cnt')
    ->groupBy('cat', 'sector')
    ->orderByDesc('cnt')
    ->get();
foreach ($lakDetail as $row) {
    $lakSheet->fromArray([(string) $row->cat, (string) $row->sector, (int) $row->cnt], null, 'A'.$start);
    $start++;
}
autoWidth($lakSheet, 'A', 'C');

// ── Sheet 6: Gender Summary ────────────────────────────────────────────────
$genders = groupedCounts($districtId, $fy, '$.gender', 'gender');
writeCountSheet($spreadsheet, 'Gender Summary', 'Gender', $genders, $total);

// ── Sheet 7: Gender by Sector ──────────────────────────────────────────────
$gxSheet = $spreadsheet->createSheet();
$gxSheet->setTitle('Gender by Sector');
writeTitle($gxSheet, 'Gender × Business Sector — Bageshwar', 1, 5);
$gxSheet->fromArray(['Sector', 'Female', 'Male', 'Other/NA', 'Total', 'Women %'], null, 'A3');
styleHeaderRow($gxSheet, 3, 6);
$r = 4;
$sectorList = groupedCounts($districtId, $fy, '$.business_category', 'sector');
foreach ($sectorList as $s) {
    $sector = $s['label'];
    $f = countWhere($districtId, $fy, sectorExpr($sector)." AND JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.gender')) = 'Female'");
    $m = countWhere($districtId, $fy, sectorExpr($sector)." AND JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.gender')) = 'Male'");
    $t = (int) $s['count'];
    $other = $t - $f - $m;
    $gxSheet->fromArray([$sector, $f, $m, $other, $t, pct($f, $t)], null, 'A'.$r);
    $r++;
}
autoWidth($gxSheet, 'A', 'F');

// ── Sheet 8: SHG Detail ────────────────────────────────────────────────────
$shgSheet = $spreadsheet->createSheet();
$shgSheet->setTitle('SHG Detail');
writeTitle($shgSheet, 'SHG Applications — Sector & Products', 1, 3);
$shgSheet->fromArray(['Sector', 'Count', '% of SHG'], null, 'A3');
styleHeaderRow($shgSheet, 3, 3);
$r = 4;
$shgSectors = groupedCounts($districtId, $fy, '$.business_category', 'sector', "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.category')) = 'SHG'");
foreach ($shgSectors as $row) {
    $shgSheet->fromArray([$row['label'], $row['count'], pct($row['count'], $shg)], null, 'A'.$r);
    $r++;
}
$r += 2;
writeSectionHeader($shgSheet, 'SHG — Products', $r, 3);
$r++;
$shgSheet->fromArray(['Product', 'Count'], null, 'A'.$r);
styleHeaderRow($shgSheet, $r, 2);
$r++;
$shgProducts = groupedCounts($districtId, $fy, '$.product', 'product', "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.category')) = 'SHG'");
foreach ($shgProducts as $row) {
    $shgSheet->fromArray([$row['label'], $row['count']], null, 'A'.$r);
    $r++;
}
autoWidth($shgSheet, 'A', 'C');

// ── Sheet 9: CBO Detail ────────────────────────────────────────────────────
$cboSheet = $spreadsheet->createSheet();
$cboSheet->setTitle('CBO Detail');
writeTitle($cboSheet, 'CBO Applications — Sector & Products', 1, 3);
$cboSheet->fromArray(['Sector', 'Count', '% of CBO'], null, 'A3');
styleHeaderRow($cboSheet, 3, 3);
$r = 4;
$cboSectors = groupedCounts($districtId, $fy, '$.business_category', 'sector', "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.category')) = 'CBO'");
foreach ($cboSectors as $row) {
    $cboSheet->fromArray([$row['label'], $row['count'], pct($row['count'], $cbo)], null, 'A'.$r);
    $r++;
}
$r += 2;
writeSectionHeader($cboSheet, 'CBO — Products', $r, 3);
$r++;
$cboSheet->fromArray(['Product', 'Count'], null, 'A'.$r);
styleHeaderRow($cboSheet, $r, 2);
$r++;
$cboProducts = groupedCounts($districtId, $fy, '$.product', 'product', "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.category')) = 'CBO'");
foreach ($cboProducts as $row) {
    $cboSheet->fromArray([$row['label'], $row['count']], null, 'A'.$r);
    $r++;
}
autoWidth($cboSheet, 'A', 'C');

// ── Sheet 10: Business Stage ───────────────────────────────────────────────
$stages = groupedCounts($districtId, $fy, '$.form_stage', 'stage');
writeCountSheet($spreadsheet, 'Business Stage', 'Stage', $stages, $total);

// ── Sheet 11: Block-wise Summary ───────────────────────────────────────────
$blkSum = $spreadsheet->createSheet();
$blkSum->setTitle('Block Summary');
writeTitle($blkSum, 'Block-wise Summary — Bageshwar', 1, 6);
$blkSum->fromArray(['Block', 'Total', '% of District', 'Female', 'Women %', 'Agri Allied', 'Agri %', 'Seed', 'Early', 'Growth'], null, 'A3');
styleHeaderRow($blkSum, 3, 10);
$r = 4;
foreach (BLOCKS as $block) {
    $bt = countWhere($districtId, $fy, blockExpr($block));
    $bf = countWhere($districtId, $fy, blockExpr($block)." AND JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.gender')) = 'Female'");
    $ba = countWhere($districtId, $fy, blockExpr($block)." AND JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.business_category')) = 'Agri Allied'");
    $bSeed = countWhere($districtId, $fy, blockExpr($block)." AND JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.form_stage')) = 'Seed'");
    $bEarly = countWhere($districtId, $fy, blockExpr($block)." AND JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.form_stage')) = 'Early'");
    $bGrowth = countWhere($districtId, $fy, blockExpr($block)." AND JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.form_stage')) = 'Growth'");
    $blkSum->fromArray([
        $block, $bt, pct($bt, $total), $bf, pct($bf, $bt), $ba, pct($ba, $bt), $bSeed, $bEarly, $bGrowth,
    ], null, 'A'.$r);
    $r++;
}
$blkSum->fromArray([
    'Total', $total, '100%', $female, pct($female, $total), $agri, pct($agri, $total),
    countWhere($districtId, $fy, "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.form_stage')) = 'Seed'"),
    countWhere($districtId, $fy, "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.form_stage')) = 'Early'"),
    countWhere($districtId, $fy, "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.form_stage')) = 'Growth'"),
], null, 'A'.$r);
styleSubtotalRow($blkSum, $r, 10);
autoWidth($blkSum, 'A', 'J');

// ── Sheets 12–14: Per-block detail ─────────────────────────────────────────
foreach (BLOCKS as $block) {
    $safeTitle = mb_substr(preg_replace('/[^\w\s\-]/', '', $block) ?: $block, 0, 31);
    $bSheet = $spreadsheet->createSheet();
    $bSheet->setTitle('Block '.$safeTitle);
    writeBlockDetailSheet($bSheet, $block, $districtId, $fy);
}

$spreadsheet->setActiveSheetIndex(0);
(new Xlsx($spreadsheet))->save($outPath);
$spreadsheet->disconnectWorksheets();

echo "Excel created: {$outPath}".PHP_EOL;
echo "Total applications: {$total}".PHP_EOL;

// ── Helpers ────────────────────────────────────────────────────────────────

function baseQuery(int $districtId, ?FiscalYear $fy): \Illuminate\Database\Query\Builder
{
    return DB::table('cfa_submissions as cs')
        ->where('cs.district_id', $districtId)
        ->when($fy, fn ($q) => $q->where('cs.fiscal_year_id', $fy->id))
        ->where(function ($q): void {
            $q->whereNull('cs.source')->orWhere('cs.source', '<>', 'legacy_phase2');
        });
}

function countWhere(int $districtId, ?FiscalYear $fy, string $extraWhere): int
{
    return (int) baseQuery($districtId, $fy)->whereRaw($extraWhere)->count();
}

function sectorExpr(string $sector): string
{
    if ($sector === 'Not specified') {
        return "(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.business_category')) IS NULL OR TRIM(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.business_category'))) = '')";
    }
    $escaped = addslashes($sector);

    return "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.business_category')) = '{$escaped}'";
}

function blockExpr(string $block): string
{
    $escaped = addslashes($block);

    return "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.block')) = '{$escaped}'";
}

function pct(int $part, int $whole): string
{
    if ($whole <= 0) {
        return '0%';
    }

    return round($part * 100 / $whole, 1).'%';
}

/** @return list<array{label: string, count: int, pct: string}> */
function groupedCounts(int $districtId, ?FiscalYear $fy, string $jsonPath, string $alias, ?string $filter = null): array
{
    $q = baseQuery($districtId, $fy);
    if ($filter) {
        $q->whereRaw($filter);
    }
    $total = (int) $q->count();
    $rows = (clone $q)
        ->selectRaw("COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '{$jsonPath}'))), ''), 'Not specified') as {$alias}")
        ->selectRaw('COUNT(*) as cnt')
        ->groupBy($alias)
        ->orderByDesc('cnt')
        ->get();

    $out = [];
    foreach ($rows as $row) {
        $cnt = (int) $row->cnt;
        $out[] = [
            'label' => (string) $row->{$alias},
            'count' => $cnt,
            'pct' => pct($cnt, $total),
        ];
    }

    return $out;
}

function writeCountSheet(Spreadsheet $ss, string $title, string $colLabel, array $rows, int $total): void
{
    $sheet = $ss->createSheet();
    $sheet->setTitle(mb_substr($title, 0, 31));
    writeTitle($sheet, $title.' — Bageshwar (FY 2026-27)', 1, 3);
    $sheet->fromArray([$colLabel, 'Count', '%'], null, 'A3');
    styleHeaderRow($sheet, 3, 3);
    $r = 4;
    foreach ($rows as $row) {
        $sheet->fromArray([$row['label'], $row['count'], $row['pct']], null, 'A'.$r);
        $r++;
    }
    $sheet->fromArray(['Total', $total, '100%'], null, 'A'.$r);
    styleSubtotalRow($sheet, $r, 3);
    autoWidth($sheet, 'A', 'C');
}

function writeBlockDetailSheet(Worksheet $sheet, string $block, int $districtId, ?FiscalYear $fy): void
{
    $filter = blockExpr($block);
    $bt = countWhere($districtId, $fy, $filter);

    writeTitle($sheet, "Block: {$block} — Detailed Breakdown", 1, 4);
    $sheet->setCellValue('A3', 'Total Applications');
    $sheet->setCellValue('B3', $bt);
    $sheet->getStyle('A3')->getFont()->setBold(true);

    $sections = [
        ['Business Sector', '$.business_category', 'sector'],
        ['Top Products', '$.product', 'product'],
        ['Applicant Category', '$.category', 'category'],
        ['Gender', '$.gender', 'gender'],
        ['Business Stage', '$.form_stage', 'stage'],
        ['Lakhpati Didi', '$.lakhpati', 'lakhpati'],
    ];

    $r = 5;
    foreach ($sections as [$heading, $path, $alias]) {
        writeSectionHeader($sheet, $heading, $r, 3);
        $r++;
        $sheet->fromArray(['Item', 'Count', '% of Block'], null, 'A'.$r);
        styleHeaderRow($sheet, $r, 3);
        $r++;
        $rows = groupedCounts($districtId, $fy, $path, $alias, $filter);
        foreach ($rows as $row) {
            $sheet->fromArray([$row['label'], $row['count'], pct($row['count'], max(1, $bt))], null, 'A'.$r);
            $r++;
        }
        $r += 2;
    }

    autoWidth($sheet, 'A', 'C');
}

function writeTitle(Worksheet $sheet, string $text, int $row, int $mergeCols): void
{
    $col = chr(ord('A') + $mergeCols - 1);
    $sheet->mergeCells("A{$row}:{$col}{$row}");
    $sheet->setCellValue('A'.$row, $text);
    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle("A{$row}")->getFill()
        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('312E81');
    $sheet->getStyle("A{$row}")->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
}

function writeSectionHeader(Worksheet $sheet, string $text, int $row, int $mergeCols): void
{
    $col = chr(ord('A') + $mergeCols - 1);
    $sheet->mergeCells("A{$row}:{$col}{$row}");
    $sheet->setCellValue('A'.$row, $text);
    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
    $sheet->getStyle("A{$row}")->getFill()
        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');
}

function styleHeaderRow(Worksheet $sheet, int $row, int $cols): void
{
    $col = chr(ord('A') + $cols - 1);
    $range = "A{$row}:{$col}{$row}";
    $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($range)->getFill()
        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

function styleSubtotalRow(Worksheet $sheet, int $row, int $cols): void
{
    $col = chr(ord('A') + $cols - 1);
    $range = "A{$row}:{$col}{$row}";
    $sheet->getStyle($range)->getFont()->setBold(true);
    $sheet->getStyle($range)->getFill()
        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C7D2FE');
}

function autoWidth(Worksheet $sheet, string $from, string $to): void
{
    foreach (range($from, $to) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}
