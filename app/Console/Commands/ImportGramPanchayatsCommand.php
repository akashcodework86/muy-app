<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\GramPanchayat;
use Illuminate\Console\Command;

class ImportGramPanchayatsCommand extends Command
{
    protected $signature = 'gram-panchayats:import
                            {path : Path to CSV (Sr.No, State/UT, District, Block, Gram Panchayat)}';

    protected $description = 'Import gram panchayats from a flat CSV master file';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error('Could not open CSV file.');

            return self::FAILURE;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->error('CSV is empty.');

            return self::FAILURE;
        }

        $header = array_map(fn ($h) => $this->normalizeKey((string) $h), $header);
        $districtIdx = $this->columnIndex($header, ['district']);
        $blockIdx = $this->columnIndex($header, ['block']);
        $gpIdx = $this->columnIndex($header, ['gram panchayat', 'gram_panchayat', 'grampanchayat']);

        if ($districtIdx === null || $blockIdx === null || $gpIdx === null) {
            fclose($handle);
            $this->error('CSV must include District, Block, and Gram Panchayat columns.');

            return self::FAILURE;
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
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
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

        $this->info("Import complete. Inserted: {$inserted}, updated: {$updated}, skipped: {$skipped}.");

        if ($unmatchedDistricts !== []) {
            $this->warn('Unmatched districts ('.count($unmatchedDistricts).'):');
            foreach (array_keys($unmatchedDistricts) as $name) {
                $this->line('  - '.$name);
            }
        }

        if ($unmatchedBlocks !== []) {
            $this->warn('Unmatched blocks ('.count($unmatchedBlocks).'):');
            foreach (array_keys($unmatchedBlocks) as $name) {
                $this->line('  - '.$name);
            }
        }

        return self::SUCCESS;
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
