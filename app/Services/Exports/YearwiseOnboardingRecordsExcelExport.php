<?php

namespace App\Services\Exports;

use App\Services\DataCentre\YearwiseIndicatorsPlusRecordsService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Streams onboarding records as XLSX (Summary + Combined + year sheets)
 * without holding PhpSpreadsheet cell objects in memory.
 */
final class YearwiseOnboardingRecordsExcelExport
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function download(array $rows, array $filters, YearwiseIndicatorsPlusRecordsService $records): StreamedResponse
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('Excel export is unavailable: PHP Zip extension missing.');
        }

        @set_time_limit(0);
        $fileName = 'yearwise-plus-onboarding-'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($rows, $filters, $records): void {
            $tmp = tempnam(sys_get_temp_dir(), 'yi-onb-xlsx-');
            if ($tmp === false) {
                throw new \RuntimeException('Could not create a temporary Excel file.');
            }
            try {
                $this->writeToPath($tmp, $rows, $filters, $records);
                $fh = fopen($tmp, 'rb');
                if ($fh === false) {
                    throw new \RuntimeException('Could not read the Excel file.');
                }
                fpassthru($fh);
                fclose($fh);
            } finally {
                @unlink($tmp);
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function writeToPath(string $absolutePath, array $rows, array $filters, YearwiseIndicatorsPlusRecordsService $records): void
    {
        $workDir = $absolutePath.'_parts';
        if (is_dir($workDir)) {
            $this->removeDir($workDir);
        }
        mkdir($workDir.'/xl/worksheets', 0755, true);
        mkdir($workDir.'/xl/_rels', 0755, true);
        mkdir($workDir.'/_rels', 0755, true);

        $headers = $records->onboardingExportHeaders();
        $years = $records->yearsForFilters($filters);
        $yearSet = array_fill_keys($years, true);
        foreach ($rows as $row) {
            $fy = (string) ($row['year'] ?? '');
            if ($fy !== '' && ! isset($yearSet[$fy])) {
                $years[] = $fy;
                $yearSet[$fy] = true;
            }
        }

        $sheetMetas = [];
        $n = 1;

        $this->writeSummarySheetXml($workDir.'/xl/worksheets/sheet'.$n.'.xml', $rows, $years, $filters);
        $sheetMetas[] = ['name' => 'Summary', 'id' => $n, 'file' => 'worksheets/sheet'.$n.'.xml'];
        $n++;

        $this->writeListSheetXml($workDir.'/xl/worksheets/sheet'.$n.'.xml', $headers, $rows, $records, null);
        $sheetMetas[] = ['name' => 'Combined', 'id' => $n, 'file' => 'worksheets/sheet'.$n.'.xml'];
        $n++;

        foreach ($years as $fy) {
            $this->writeListSheetXml(
                $workDir.'/xl/worksheets/sheet'.$n.'.xml',
                $headers,
                $rows,
                $records,
                (string) $fy,
            );
            $sheetMetas[] = ['name' => $this->sheetTitle((string) $fy), 'id' => $n, 'file' => 'worksheets/sheet'.$n.'.xml'];
            $n++;
        }

        file_put_contents($workDir.'/[Content_Types].xml', $this->contentTypesXml($sheetMetas));
        file_put_contents($workDir.'/_rels/.rels', $this->rootRelsXml());
        file_put_contents($workDir.'/xl/workbook.xml', $this->workbookXml($sheetMetas));
        file_put_contents($workDir.'/xl/_rels/workbook.xml.rels', $this->workbookRelsXml($sheetMetas));
        file_put_contents($workDir.'/xl/styles.xml', $this->stylesXml());

        $zip = new ZipArchive;
        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->removeDir($workDir);
            throw new \RuntimeException('Could not create the Excel zip.');
        }
        $zip->addFile($workDir.'/[Content_Types].xml', '[Content_Types].xml');
        $zip->addFile($workDir.'/_rels/.rels', '_rels/.rels');
        $zip->addFile($workDir.'/xl/workbook.xml', 'xl/workbook.xml');
        $zip->addFile($workDir.'/xl/_rels/workbook.xml.rels', 'xl/_rels/workbook.xml.rels');
        $zip->addFile($workDir.'/xl/styles.xml', 'xl/styles.xml');
        foreach ($sheetMetas as $meta) {
            $zip->addFile($workDir.'/xl/'.$meta['file'], 'xl/'.$meta['file']);
        }
        $zip->close();
        $this->removeDir($workDir);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $years
     * @param  array<string, mixed>  $filters
     */
    private function writeSummarySheetXml(string $path, array $rows, array $years, array $filters): void
    {
        $missing = YearwiseIndicatorsPlusRecordsService::MISSING_LABEL;
        $byYear = [];
        foreach ($years as $fy) {
            $byYear[$fy] = ['n' => 0, 'sector' => 0, 'product' => 0, 'gst' => 0, 'fssai' => 0, 'market' => 0];
        }
        foreach ($rows as $row) {
            $fy = (string) ($row['year'] ?? '');
            if ($fy === '') {
                continue;
            }
            if (! isset($byYear[$fy])) {
                $byYear[$fy] = ['n' => 0, 'sector' => 0, 'product' => 0, 'gst' => 0, 'fssai' => 0, 'market' => 0];
            }
            $byYear[$fy]['n']++;
            if ($this->hasValue($row['sector'] ?? '', $missing)) {
                $byYear[$fy]['sector']++;
            }
            if ($this->hasValue($row['product'] ?? '', $missing)) {
                $byYear[$fy]['product']++;
            }
            if ($this->hasValue($row['gst_number'] ?? '', $missing)) {
                $byYear[$fy]['gst']++;
            }
            if ($this->hasValue($row['fssai_number'] ?? '', $missing)) {
                $byYear[$fy]['fssai']++;
            }
            if ($this->hasValue($row['market_partners'] ?? '', $missing)) {
                $byYear[$fy]['market']++;
            }
        }

        $totals = ['n' => 0, 'sector' => 0, 'product' => 0, 'gst' => 0, 'fssai' => 0, 'market' => 0];
        $fh = $this->openSheet($path, 'A7');
        $this->writeRow($fh, 1, [
            'Onboarded applicants — year-wise indicators',
            '', '', '', '', '', '',
        ], 2);
        $this->writeRow($fh, 2, ['Generated: '.now()->timezone('Asia/Kolkata')->format('d M Y, g:i A').' IST']);
        $this->writeRow($fh, 3, [
            'Scope: '.($filters['scope'] ?? 'grand')
            .((trim((string) ($filters['year'] ?? '')) !== '') ? ' · FY '.$filters['year'] : '')
            .((trim((string) ($filters['phase'] ?? '')) !== '') ? ' · phase '.$filters['phase'] : '')
            .((trim((string) ($filters['district'] ?? '')) !== '') ? ' · '.$filters['district'] : '')
            .((trim((string) ($filters['source'] ?? 'all')) !== 'all') ? ' · source '.$filters['source'] : ''),
        ]);
        $this->writeRow($fh, 4, ['GST / FSSAI / market linkage show "'.$missing.'" when the incubatee has no register number or partner.']);
        $this->writeRow($fh, 6, ['Year', 'Onboarded', 'With sector', 'With product', 'With GSTIN', 'With FSSAI', 'With market linkage'], 1);

        $r = 7;
        foreach ($byYear as $fy => $counts) {
            $this->writeRow($fh, $r, [
                (string) $fy,
                (string) $counts['n'],
                (string) $counts['sector'],
                (string) $counts['product'],
                (string) $counts['gst'],
                (string) $counts['fssai'],
                (string) $counts['market'],
            ]);
            foreach ($totals as $k => $_) {
                $totals[$k] += $counts[$k];
            }
            $r++;
        }
        $this->writeRow($fh, $r, [
            'Total',
            (string) ($totals['n'] ?: count($rows)),
            (string) $totals['sector'],
            (string) $totals['product'],
            (string) $totals['gst'],
            (string) $totals['fssai'],
            (string) $totals['market'],
        ], 1);
        $this->closeSheet($fh, 'A6:G'.max(6, $r));
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeListSheetXml(
        string $path,
        array $headers,
        array $rows,
        YearwiseIndicatorsPlusRecordsService $records,
        ?string $onlyYear,
    ): void {
        $colCount = count($headers);
        $lastCol = $this->colLetter($colCount);
        $fh = $this->openSheet($path, 'A2');
        $this->writeRow($fh, 1, $headers, 1);
        $r = 2;
        foreach ($rows as $row) {
            if ($onlyYear !== null && (string) ($row['year'] ?? '') !== $onlyYear) {
                continue;
            }
            $this->writeRow($fh, $r, $records->onboardingExportRow($row));
            $r++;
        }
        $lastDataRow = max(1, $r - 1);
        $this->closeSheet($fh, 'A1:'.$lastCol.$lastDataRow);
    }

    /**
     * @return resource
     */
    private function openSheet(string $path, string $freezeCell)
    {
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Could not write worksheet '.$path);
        }
        fwrite($fh, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'
            .(str_starts_with($freezeCell, 'A7') ? '6' : '1')
            .'" topLeftCell="'.$freezeCell.'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetData>');

        return $fh;
    }

    /**
     * @param  resource  $fh
     * @param  list<string>  $values
     */
    private function writeRow($fh, int $rowNum, array $values, int $style = 0): void
    {
        fwrite($fh, '<row r="'.$rowNum.'">');
        foreach ($values as $i => $value) {
            $cell = $this->colLetter($i + 1).$rowNum;
            $styleAttr = $style > 0 ? ' s="'.$style.'"' : '';
            fwrite($fh, '<c r="'.$cell.'" t="inlineStr"'.$styleAttr.'><is><t xml:space="preserve">'
                .$this->xml($value).'</t></is></c>');
        }
        fwrite($fh, '</row>');
    }

    /**
     * @param  resource  $fh
     */
    private function closeSheet($fh, string $autoFilterRef): void
    {
        fwrite($fh, '</sheetData><autoFilter ref="'.$this->xml($autoFilterRef).'"/></worksheet>');
        fclose($fh);
    }

    /**
     * @param  list<array{name: string, id: int, file: string}>  $sheets
     */
    private function contentTypesXml(array $sheets): string
    {
        $overrides = '';
        foreach ($sheets as $sheet) {
            $overrides .= '<Override PartName="/xl/'.$sheet['file'].'" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  list<array{name: string, id: int, file: string}>  $sheets
     */
    private function workbookXml(array $sheets): string
    {
        $used = [];
        $entries = '';
        foreach ($sheets as $i => $sheet) {
            $name = $this->uniqueSheetName($sheet['name'], $used);
            $used[$name] = true;
            $entries .= '<sheet name="'.$this->xml($name).'" sheetId="'.$sheet['id'].'" r:id="rId'.($i + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$entries.'</sheets></workbook>';
    }

    /**
     * @param  list<array{name: string, id: int, file: string}>  $sheets
     */
    private function workbookRelsXml(array $sheets): string
    {
        $rels = '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        foreach ($sheets as $i => $sheet) {
            $rels .= '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="'.$sheet['file'].'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="16"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1F4E79"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    /**
     * @param  array<string, true>  $used
     */
    private function uniqueSheetName(string $name, array $used): string
    {
        $name = $this->sheetTitle($name);
        if (! isset($used[$name])) {
            return $name;
        }
        $i = 2;
        while (isset($used[mb_substr($name, 0, 28).'_'.$i])) {
            $i++;
        }

        return mb_substr($name, 0, 28).'_'.$i;
    }

    private function sheetTitle(string $fy): string
    {
        $title = preg_replace('/[\[\]\*\/\\\\?:]/', '-', $fy) ?: $fy;

        return mb_substr($title, 0, 31);
    }

    private function colLetter(int $index): string
    {
        $s = '';
        while ($index > 0) {
            $index--;
            $s = chr(65 + ($index % 26)).$s;
            $index = intdiv($index, 26);
        }

        return $s;
    }

    private function xml(mixed $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value) ?? '';
        if (mb_strlen($value) > 32767) {
            $value = mb_substr($value, 0, 32767);
        }

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function hasValue(mixed $value, string $missing): bool
    {
        $v = trim((string) $value);

        return $v !== '' && strcasecmp($v, $missing) !== 0 && $v !== '—';
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $path = $file->getPathname();
            $file->isDir() ? @rmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
