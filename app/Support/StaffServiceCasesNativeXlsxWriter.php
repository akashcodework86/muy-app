<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Dependency-free formatted XLSX writer for the staff services export.
 * Production has ext-zip but may not have PhpSpreadsheet installed.
 */
final class StaffServiceCasesNativeXlsxWriter
{
    /**
     * @param  list<list<string|int>>  $rows
     * @param  array{staff:string,district:string,scope:string,search:string,status:string,service:string,total:int}  $meta
     */
    public function save(string $path, array $rows, array $meta): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP Zip extension (ext-zip) is required to write Excel files.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the Excel workbook.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('docProps/app.xml', $this->appPropertiesXml());
        $zip->addFromString('docProps/core.xml', $this->corePropertiesXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($rows, $meta));
        $zip->close();
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Service Records" sheetId="1" r:id="rId1"/></sheets>'
            .'<calcPr calcId="191029"/></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="5">'
            .'<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="16"/><name val="Calibri"/></font>'
            .'<font><i/><color rgb="FF475569"/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><color rgb="FF334155"/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="11">'
            .'<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF4338CA"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEEF2FF"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFEDD5"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="11">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
            .'<xf numFmtId="0" fontId="4" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="7" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="8" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="9" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="10" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    /**
     * @param  list<list<string|int>>  $rows
     * @param  array{staff:string,district:string,scope:string,search:string,status:string,service:string,total:int}  $meta
     */
    private function worksheetXml(array $rows, array $meta): string
    {
        $headers = ['S.No.', 'Record Type', 'Incubatee / Activity', 'Application No.', 'District', 'Service', 'Service Given By', 'Assigned SPOC', 'Responded By', 'Response / Remark', 'Status', 'Service Date', 'Submitted At', 'Updated At', 'Reference No.'];
        $metaRows = [
            ['Staff', $meta['staff']], ['District', $meta['district']], ['Scope', $meta['scope']],
            ['Search', $meta['search'] !== '' ? $meta['search'] : 'All'], ['Service filter', $meta['service']],
            ['Status filter', $meta['status'] !== '' ? str_replace('_', ' ', ucfirst($meta['status'])) : 'All statuses'],
            ['Total matching records', number_format($meta['total'])],
        ];
        $lastRow = max(12, 12 + count($rows));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:O'.$lastRow.'"/><sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="12" topLeftCell="A13" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>'
            .'<cols>';
        foreach ([8, 19, 34, 19, 18, 34, 23, 23, 22, 34, 18, 16, 23, 23, 18] as $index => $width) {
            $column = $index + 1;
            $xml .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        $xml .= '<row r="1" ht="30" customHeight="1">'.$this->stringCell('A1', 'MUY — Staff Service Records', 1).'</row>';
        $xml .= '<row r="2">'.$this->stringCell('A2', 'Filtered export · generated '.now()->timezone(config('app.timezone'))->format('d M Y, h:i A'), 2).'</row>';
        foreach ($metaRows as $index => [$label, $value]) {
            $rowNumber = 4 + $index;
            $xml .= '<row r="'.$rowNumber.'">'.$this->stringCell('A'.$rowNumber, $label, 3).$this->stringCell('B'.$rowNumber, (string) $value).'</row>';
        }
        $xml .= '<row r="12" ht="26" customHeight="1">';
        foreach ($headers as $index => $label) {
            $xml .= $this->stringCell($this->columnLetter($index).'12', $label, 4);
        }
        $xml .= '</row>';
        foreach ($rows as $index => $values) {
            $rowNumber = 13 + $index;
            $baseStyle = $index % 2 === 1 ? 6 : 5;
            $xml .= '<row r="'.$rowNumber.'">';
            foreach ($values as $columnIndex => $value) {
                $style = $baseStyle;
                if ($columnIndex === 10) {
                    $status = mb_strtolower((string) $value);
                    $style = str_contains($status, 'approved') ? 7
                        : (str_contains($status, 'pending') ? 8
                            : (str_contains($status, 'sent back') ? 9
                                : (str_contains($status, 'rejected') ? 10 : $baseStyle)));
                }
                $ref = $this->columnLetter($columnIndex).$rowNumber;
                $xml .= $columnIndex === 0
                    ? '<c r="'.$ref.'" s="'.$style.'"><v>'.((int) $value).'</v></c>'
                    : $this->stringCell($ref, (string) $value, $style);
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData><autoFilter ref="A12:O'.$lastRow.'"/>'
            .'<mergeCells count="9"><mergeCell ref="A1:O1"/><mergeCell ref="A2:O2"/>';
        foreach (range(4, 10) as $rowNumber) {
            $xml .= '<mergeCell ref="B'.$rowNumber.':E'.$rowNumber.'"/>';
        }
        $xml .= '</mergeCells><pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';

        return $xml;
    }

    private function stringCell(string $reference, string $value, int $style = 0): string
    {
        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml($value).'</t></is></c>';
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        do {
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $letter;
    }

    private function xml(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars(mb_substr($value, 0, 32000), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>MUY</Application></Properties>';
    }

    private function corePropertiesXml(): string
    {
        $created = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>MUY Staff Service Records</dc:title><dc:creator>MUY</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:created></cp:coreProperties>';
    }
}
