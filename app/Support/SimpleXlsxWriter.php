<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal multi-sheet XLSX writer using only ext-zip (no PhpSpreadsheet).
 * Used as fallback when vendor/phpoffice/phpspreadsheet is missing on the server.
 */
final class SimpleXlsxWriter
{
    /** @var list<array{title: string, rows: list<list<string|int|float|null>>}> */
    private array $sheets = [];

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function addSheet(string $title, array $rows): self
    {
        $safe = preg_replace('/[\\\\\/\*\?\:\[\]]+/', ' ', $title) ?? $title;
        $safe = trim($safe);
        if ($safe === '') {
            $safe = 'Sheet'.(count($this->sheets) + 1);
        }
        $safe = mb_substr($safe, 0, 31);

        $this->sheets[] = [
            'title' => $safe,
            'rows' => $rows,
        ];

        return $this;
    }

    public function save(string $absolutePath): void
    {
        if ($this->sheets === []) {
            throw new RuntimeException('SimpleXlsxWriter: no sheets to write.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP Zip extension (ext-zip) is required to write Excel files.');
        }

        $dir = dirname($absolutePath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot create export directory: '.$dir);
        }

        $zip = new ZipArchive;
        $tmp = $absolutePath.'.tmp-'.bin2hex(random_bytes(4));
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot open temp zip for Excel write.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet'.($i + 1).'.xml',
                $this->sheetXml($sheet['rows'])
            );
        }

        $zip->close();

        if (! rename($tmp, $absolutePath)) {
            @unlink($absolutePath);
            if (! rename($tmp, $absolutePath)) {
                @unlink($tmp);
                throw new RuntimeException('Failed to finalize Excel file at '.$absolutePath);
            }
        }
    }

    private function contentTypesXml(): string
    {
        $overrides = '';
        foreach ($this->sheets as $i => $_) {
            $n = $i + 1;
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
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

    private function workbookXml(): string
    {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $sheets .= '<sheet name="'.$this->xml($sheet['title']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        $rels = '';
        foreach ($this->sheets as $i => $_) {
            $n = $i + 1;
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }
        $styleId = count($this->sheets) + 1;
        $rels .= '<Relationship Id="rId'.$styleId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="1"><xf/></cellXfs>'
            .'</styleSheet>';
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $xml .= '<row r="'.$rowNum.'">';
            foreach ($row as $cIdx => $value) {
                $col = $this->columnLetter($cIdx);
                $ref = $col.$rowNum;
                if ($value === null || $value === '') {
                    continue;
                }
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.$this->xml((string) $value).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private function columnLetter(int $index): string
    {
        $index = max(0, $index);
        $letter = '';
        while ($index >= 0) {
            $letter = chr(($index % 26) + 65).$letter;
            $index = intdiv($index, 26) - 1;
        }

        return $letter;
    }

    private function xml(string $value): string
    {
        // Excel rejects control chars in shared/inline strings.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, 32000);
        } else {
            $value = substr($value, 0, 32000);
        }

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
