<?php

namespace App\Services\ReviewPpt;

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
    public function write(array $data, ReviewPptSelection $selection, string $outputPath): void
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

        $hasKumaon = count(array_intersect($selection->districtSlugs, config('review_ppt.kumaon', []))) > 0;
        $hasGarhwal = count(array_intersect($selection->districtSlugs, config('review_ppt.garhwal', []))) > 0;
        $excludedSlides = $hasKumaon && $hasGarhwal ? [] : ($hasKumaon ? [4, 5] : [2, 3]);

        try {
            for ($i = 0; $i < $source->numFiles; $i++) {
                $name = $source->getNameIndex($i);
                $content = $source->getFromIndex($i);
                if ($name === false || $content === false) {
                    throw new RuntimeException('Could not read the PowerPoint template.');
                }
                if (preg_match('~^ppt/slides/(?:_rels/)?slide([1-5])\.xml(?:\.rels)?$~', $name, $match)
                    && in_array((int) $match[1], $excludedSlides, true)) {
                    continue;
                }
                if (preg_match('~^ppt/slides/slide([1-5])\.xml$~', $name, $match)) {
                    $content = $this->updateSlide((int) $match[1], $content, $data, $selection);
                } elseif ($excludedSlides !== [] && in_array($name, ['ppt/presentation.xml', 'ppt/_rels/presentation.xml.rels', '[Content_Types].xml'], true)) {
                    $content = $this->removeSlideReferences($name, $content, $excludedSlides);
                } elseif ($name === 'docProps/app.xml' && $excludedSlides !== []) {
                    $content = preg_replace('~<Slides>\d+</Slides>~', '<Slides>'.(5 - count($excludedSlides)).'</Slides>', $content);
                }
                $destination->addFromString($name, $content);
            }
        } finally {
            $destination->close();
            $source->close();
        }
    }

    /** @param array<string, mixed> $data */
    private function updateSlide(int $slide, string $xml, array $data, ReviewPptSelection $selection): string
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
                $textNode->nodeValue = 'Target vs Achievement: '.$selection->slideLabel();
            } elseif (count($selection->districtSlugs) === 1 && $slide > 1) {
                $districtName = $data['districts'][$selection->districtSlugs[0]];
                $textNode->nodeValue = str_replace(['(Kumaon Region)', '(Garhwal Region)'],
                    '('.$districtName.')', (string) $textNode->textContent);
            }
        }

        if ($slide === 1) {
            $this->updateStateTable($xp, $tables[0], '1.1', $data);
            $this->updateStateTable($xp, $tables[1], '2.1', $data);
        } else {
            $region = in_array($slide, [2, 3], true) ? 'kumaon' : 'garhwal';
            $indicators = in_array($slide, [2, 4], true) ? 'key_indicators' : 'non_key_indicators';
            $this->updateRegionTable($xp, $tables[0], config('review_ppt.'.$region), config('review_ppt.'.$indicators), $data);
            if (count($selection->districtSlugs) === 1) {
                $this->retainDistrictColumns($xp, $tables[0], config('review_ppt.'.$region), $selection->districtSlugs);
            }
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
        for ($index = 13; $index > count($slugs); $index--) {
            $table->removeChild($rows[$index]);
        }
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

    /** Narrow the supplied editable regional table to one district without changing its branding or row order. */
    private function retainDistrictColumns(DOMXPath $xp, DOMElement $table, array $regionSlugs, array $selectedSlugs): void
    {
        $rows = $this->tableRows($xp, $table);
        $grid = iterator_to_array($xp->query('./a:tblGrid/a:gridCol', $table));
        $selectedIndex = array_search($selectedSlugs[0], $regionSlugs, true);
        if ($selectedIndex === false || count($grid) !== count($regionSlugs) * 2 + 2) {
            throw new RuntimeException('Selected district does not match its regional review table.');
        }
        $originalWidth = array_sum(array_map(fn (DOMElement $col) => (int) $col->getAttribute('w'), $grid));
        for ($districtIndex = count($regionSlugs) - 1; $districtIndex >= 0; $districtIndex--) {
            if ($districtIndex === $selectedIndex) {
                continue;
            }
            foreach ($rows as $row) {
                $cells = $this->rowCells($xp, $row);
                $row->removeChild($cells[$districtIndex * 2 + 3]);
                $row->removeChild($cells[$districtIndex * 2 + 2]);
            }
            $grid[$districtIndex * 2 + 3]->parentNode->removeChild($grid[$districtIndex * 2 + 3]);
            $grid[$districtIndex * 2 + 2]->parentNode->removeChild($grid[$districtIndex * 2 + 2]);
        }
        $remaining = iterator_to_array($xp->query('./a:tblGrid/a:gridCol', $table));
        $districtWidth = (int) floor(($originalWidth - (int) $remaining[0]->getAttribute('w')
            - (int) $remaining[1]->getAttribute('w')) / 2);
        $remaining[2]->setAttribute('w', (string) $districtWidth);
        $remaining[3]->setAttribute('w', (string) ($originalWidth - (int) $remaining[0]->getAttribute('w')
            - (int) $remaining[1]->getAttribute('w') - $districtWidth));
    }

    /** Remove the unused regional slides from the PPTX package manifest and presentation order. */
    private function removeSlideReferences(string $name, string $xml, array $excludedSlides): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        if (! $dom->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Invalid PowerPoint package manifest.');
        }
        $xp = new DOMXPath($dom);
        if ($name === 'ppt/presentation.xml') {
            $xp->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
            $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            foreach (iterator_to_array($xp->query('//p:sldIdLst/p:sldId')) as $index => $node) {
                if (in_array($index + 1, $excludedSlides, true)) {
                    $node->parentNode->removeChild($node);
                }
            }
        } elseif ($name === 'ppt/_rels/presentation.xml.rels') {
            $xp->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
            foreach (iterator_to_array($xp->query('//rel:Relationship')) as $node) {
                if (preg_match('~^slides/slide([1-5])\.xml$~', $node->getAttribute('Target'), $match)
                    && in_array((int) $match[1], $excludedSlides, true)) {
                    $node->parentNode->removeChild($node);
                }
            }
        } else {
            $xp->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');
            foreach (iterator_to_array($xp->query('//ct:Override')) as $node) {
                if (preg_match('~^/ppt/slides/slide([1-5])\.xml$~', $node->getAttribute('PartName'), $match)
                    && in_array((int) $match[1], $excludedSlides, true)) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        return $dom->saveXML();
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
