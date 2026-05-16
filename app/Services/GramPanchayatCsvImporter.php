<?php

namespace App\Services;

use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\GramPanchayat;

class GramPanchayatCsvImporter
{
    /**
     * @return array{
     *     success: bool,
     *     error: string|null,
     *     inserted: int,
     *     updated: int,
     *     skipped: int,
     *     unmatched_districts: list<string>,
     *     unmatched_blocks: list<string>
     * }
     */
    public function importFromPath(string $path): array
    {
        $empty = [
            'success' => false,
            'error' => null,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'unmatched_districts' => [],
            'unmatched_blocks' => [],
        ];

        if (! is_readable($path)) {
            return array_merge($empty, ['error' => "File not readable: {$path}"]);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return array_merge($empty, ['error' => 'Could not open CSV file.']);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return array_merge($empty, ['error' => 'CSV is empty.']);
        }

        $header = array_map(fn ($h) => $this->normalizeKey((string) $h), $header);
        $districtIdx = $this->columnIndex($header, ['district']);
        $blockIdx = $this->columnIndex($header, ['block']);
        $gpIdx = $this->columnIndex($header, ['gram panchayat', 'gram_panchayat', 'grampanchayat']);

        if ($districtIdx === null || $blockIdx === null || $gpIdx === null) {
            fclose($handle);

            return array_merge($empty, ['error' => 'CSV must include District, Block, and Gram Panchayat columns.']);
        }

        $districtsByName = District::query()->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [$this->normalizeKey($name) => (int) $id])
            ->all();

        $blocksByDistrict = [];
        DistrictBlock::query()
            ->select(['id', 'district_id', 'name'])
            ->orderBy('district_id')
            ->orderBy('name')
            ->each(function (DistrictBlock $block) use (&$blocksByDistrict): void {
                $key = $block->district_id.'|'.$this->normalizeKey($block->name);
                $blocksByDistrict[$key] = (int) $block->id;
            });

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $unmatchedDistricts = [];
        $unmatchedBlocks = [];

        while (($row = fgetcsv($handle)) !== false) {
            $districtName = trim((string) ($row[$districtIdx] ?? ''));
            $blockName = trim((string) ($row[$blockIdx] ?? ''));
            $gpName = trim((string) ($row[$gpIdx] ?? ''));

            if ($districtName === '' || $blockName === '' || $gpName === '') {
                $skipped++;

                continue;
            }

            $districtId = $districtsByName[$this->normalizeKey($districtName)] ?? null;
            if ($districtId === null) {
                $unmatchedDistricts[$districtName] = true;
                $skipped++;

                continue;
            }

            $blockKey = $districtId.'|'.$this->normalizeKey($blockName);
            $blockId = $blocksByDistrict[$blockKey] ?? null;
            if ($blockId === null) {
                $unmatchedBlocks[$districtName.' → '.$blockName] = true;
                $skipped++;

                continue;
            }

            $gp = GramPanchayat::query()->updateOrCreate(
                [
                    'district_block_id' => $blockId,
                    'name' => $gpName,
                ],
                [
                    'district_id' => $districtId,
                ]
            );

            if ($gp->wasRecentlyCreated) {
                $inserted++;
            } else {
                $updated++;
            }
        }

        fclose($handle);

        return [
            'success' => true,
            'error' => null,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'unmatched_districts' => array_keys($unmatchedDistricts),
            'unmatched_blocks' => array_keys($unmatchedBlocks),
        ];
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $candidates
     */
    private function columnIndex(array $header, array $candidates): ?int
    {
        foreach ($header as $index => $label) {
            if (in_array($label, $candidates, true)) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeKey(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value);
    }
}
