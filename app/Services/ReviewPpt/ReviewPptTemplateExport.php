<?php

namespace App\Services\ReviewPpt;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class ReviewPptTemplateExport
{
    private const DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    public function __construct(private readonly ?string $templatePath = null) {}

    /** @param array{districts: array<string, string>, targets: array<string, array<string, int>>, achievements: array<string, array<string, int>>} $data */
    public function write(array $data, Carbon $asOf, int $quarter, string $outputPath): void
    {
        $template = $this->templatePath ?? resource_path('templates/review-ppt/muy-review-2026.pptx');
        if (! is_file($template)) {
            throw new RuntimeException('MUY review PowerPoint template is missing.');
        }
        $source = new ZipArchive;
        $destination = new ZipArchive;
        if ($source->open($template) !== true || $destination->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not open the review PowerPoint.');
        }

        try {
            for ($i = 0; $i < $source->numFiles; $i++) {
                $name = $source->getNameIndex($i);
                $content = $source->getFromIndex($i);
                if ($name === false || $content === false) {
                    throw new RuntimeException('Could not read the PowerPoint template.');
                }
                if (preg_match('~^ppt/slides/slide([1-5])\.xml$~', $name, $match)) {
                    $content = $this->updateSlide((int) $match[1], $content, $data, $asOf, $quarter);
                }
                $destination->addFromString($name, $content);
            }
        } finally {
            $destination->close();
            $source->close();
        }
    }

    /** @param array<string, mixed> $data */
    private function updateSlide(int $slide, string $xml, array $data, Carbon $asOf, int $quarter): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        if (! $dom->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Invalid XML in review slide '.$slide.'.');
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('a', self::DRAWING_NS);
        $tables = iterator_to_array($xp->query('//a:tbl'));
        if (count($tables) !== ($slide === 1 ? 2 : 1)) {
            throw new RuntimeException('Review slide '.$slide.' no longer matches the supplied layout.');
        }

        foreach ($xp->query('//a:t') as $textNode) {
            if (str_starts_with(trim((string) $textNode->textContent), 'Target Q2 vs Achievement')) {
                $textNode->nodeValue = 'Target Q'.$quarter.' vs Achievement ('.$asOf->format('d-m-Y').')';
            }
        }

        if ($slide === 1) {
            $this->updateStateTable($xp, $tables[0], '1.1', $data);
            $this->updateStateTable($xp, $tables[1], '2.1', $data);
        } else {
            $region = in_array($slide, [2, 3], true) ? 'kumaon' : 'garhwal';
            $indicators = in_array($slide, [2, 4], true) ? 'key_indicators' : 'non_key_indicators';
            $this->updateRegionTable($xp, $tables[0], config('review_ppt.'.$region), config('review_ppt.'.$indicators), $data);
        }

        return $dom->saveXML();
    }

    /** @param array<string, mixed> $data */
    private function updateStateTable(DOMXPath $xp, DOMElement $table, string $serial, array $data): void
    {
        $rows = $this->tableRows($xp, $table);
        if (count($rows) !== 15) {
            throw new RuntimeException('Statewide review table must contain 13 districts and one total.');
        }
        $slugs = array_keys($data['districts']);
        usort($slugs, function ($a, $b) use ($data, $serial): int {
            $aTarget = $data['targets'][$a][$serial] ?? 0;
            $bTarget = $data['targets'][$b][$serial] ?? 0;
            $aPct = $aTarget > 0 ? ($data['achievements'][$a][$serial] ?? 0) / $aTarget : 0;
            $bPct = $bTarget > 0 ? ($data['achievements'][$b][$serial] ?? 0) / $bTarget : 0;

            return ($bPct <=> $aPct) ?: strcmp($data['districts'][$a], $data['districts'][$b]);
        });
        $totalTarget = 0;
        $totalAchievement = 0;
        foreach ($slugs as $index => $slug) {
            $cells = $this->rowCells($xp, $rows[$index + 1]);
            $target = (int) $data['targets'][$slug][$serial];
            $achievement = (int) $data['achievements'][$slug][$serial];
            $totalTarget += $target;
            $totalAchievement += $achievement;
            $this->setCellText($xp, $cells[0], (string) ($index + 1));
            $this->setCellText($xp, $cells[1], $data['districts'][$slug]);
            $this->setCellText($xp, $cells[2], (string) $target);
            $this->setCellText($xp, $cells[3], (string) $achievement);
            $this->setCellText($xp, $cells[4], $this->percent($target, $achievement));
            $tone = $this->tone($target, $achievement);
            $this->setFill($xp, $cells[4], $tone === 'green' ? 'state-green' : $tone);
        }
        $totalCells = $this->rowCells($xp, $rows[14]);
        $this->setCellText($xp, $totalCells[2], (string) $totalTarget);
        $this->setCellText($xp, $totalCells[3], (string) $totalAchievement);
        $this->setCellText($xp, $totalCells[4], $this->percent($totalTarget, $totalAchievement));
        $totalTone = $this->tone($totalTarget, $totalAchievement);
        $this->setFill($xp, $totalCells[4], $totalTone === 'green' ? 'state-green' : $totalTone);
    }

    /** @param list<string> $slugs @param list<string> $serials @param array<string, mixed> $data */
    private function updateRegionTable(DOMXPath $xp, DOMElement $table, array $slugs, array $serials, array $data): void
    {
        $rows = $this->tableRows($xp, $table);
        if (count($rows) !== count($serials) + 2 || count($this->rowCells($xp, $rows[0])) !== count($slugs) * 2 + 2) {
            throw new RuntimeException('Regional review table no longer matches the supplied layout.');
        }
        foreach ($serials as $rowIndex => $serial) {
            $cells = $this->rowCells($xp, $rows[$rowIndex + 2]);
            foreach ($slugs as $districtIndex => $slug) {
                $targetCell = $cells[$districtIndex * 2 + 2];
                $achievementCell = $cells[$districtIndex * 2 + 3];
                $target = (int) ($data['targets'][$slug][$serial] ?? 0);
                $achievement = (int) ($data['achievements'][$slug][$serial] ?? 0);
                $originalTarget = trim($targetCell->textContent);
                $originalAchievement = trim($achievementCell->textContent);
                $needBased = in_array($serial, config('review_ppt.need_based', []), true);
                $targetText = $needBased
                    ? 'Need Based'
                    : ($target > 0 ? (string) $target : (in_array($originalTarget, ['-', '–', '0'], true) ? $originalTarget : '0'));
                $achievementText = $achievement > 0 ? (string) $achievement
                    : (in_array($originalAchievement, ['-', '–'], true) ? $originalAchievement : '0');
                $this->setCellText($xp, $targetCell, $targetText);
                $this->setCellText($xp, $achievementCell, $achievementText);
                $this->setFill($xp, $achievementCell, $needBased ? 'white' : $this->tone($target, $achievement));
            }
        }
    }

    /** @return list<DOMElement> */
    private function tableRows(DOMXPath $xp, DOMElement $table): array
    {
        return iterator_to_array($xp->query('./a:tr', $table));
    }

    /** @return list<DOMElement> */
    private function rowCells(DOMXPath $xp, DOMElement $row): array
    {
        return iterator_to_array($xp->query('./a:tc', $row));
    }

    private function setCellText(DOMXPath $xp, DOMElement $cell, string $value): void
    {
        $nodes = $xp->query('./a:txBody//a:t', $cell);
        if ($nodes->length === 0) {
            throw new RuntimeException('Expected editable text in a review table cell.');
        }
        foreach ($nodes as $index => $node) {
            $node->nodeValue = $index === 0 ? $value : '';
        }
    }

    private function setFill(DOMXPath $xp, DOMElement $cell, string $tone): void
    {
        $properties = $xp->query('./a:tcPr', $cell)->item(0);
        if (! $properties instanceof DOMElement) {
            return;
        }
        $oldFill = $xp->query('./a:solidFill', $properties)->item(0);
        $fill = $cell->ownerDocument->createElementNS(self::DRAWING_NS, 'a:solidFill');
        if ($tone === 'green') {
            $color = $cell->ownerDocument->createElementNS(self::DRAWING_NS, 'a:schemeClr');
            $color->setAttribute('val', 'accent6');
            $mod = $cell->ownerDocument->createElementNS(self::DRAWING_NS, 'a:lumMod');
            $mod->setAttribute('val', '20000');
            $off = $cell->ownerDocument->createElementNS(self::DRAWING_NS, 'a:lumOff');
            $off->setAttribute('val', '80000');
            $color->appendChild($mod);
            $color->appendChild($off);
        } elseif (in_array($tone, ['state-green', 'yellow', 'red'], true)) {
            $color = $cell->ownerDocument->createElementNS(self::DRAWING_NS, 'a:srgbClr');
            $color->setAttribute('val', match ($tone) {
                'state-green' => 'D9F2D0',
                'yellow' => 'F6F4AA',
                default => 'FEA67E',
            });
        } else {
            $color = $cell->ownerDocument->createElementNS(self::DRAWING_NS, 'a:schemeClr');
            $color->setAttribute('val', 'bg1');
        }
        $fill->appendChild($color);
        if ($oldFill) {
            $properties->replaceChild($fill, $oldFill);
        } else {
            $properties->insertBefore($fill, $properties->firstChild);
        }
    }

    private function tone(int $target, int $achievement): string
    {
        if ($target <= 0) {
            return 'white';
        }
        $percent = (int) round($achievement * 100 / $target);

        return $percent > 90 ? 'green' : ($percent >= 70 ? 'yellow' : 'red');
    }

    private function percent(int $target, int $achievement): string
    {
        return $target > 0 ? round($achievement * 100 / $target).'%' : '—';
    }
}
