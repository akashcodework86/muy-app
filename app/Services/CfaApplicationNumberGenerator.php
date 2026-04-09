<?php

namespace App\Services;

use App\Models\CfaApplicationSequence;
use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fixed 8-character application number:
 * [month:1][district_id:2 base36][block_index:2 base36][serial:3 base36]
 *
 * For government alignment, `cfa_submissions` stores MoPR LGD state/district/block codes separately;
 * full LGD numerics do not fit in two base36 slots. Block order follows LGD when `lgd_block_code` is set.
 */
class CfaApplicationNumberGenerator
{
    private const MONTH_CHARS = '123456789ABC';

    private const SERIAL_MAX = 46655;

    private const ALLOC_RETRIES = 8;

    public function generate(User $staff, string $block, ?Carbon $at = null): string
    {
        $at = $at ?? now();
        $districtId = (int) $staff->district_id;
        if ($districtId < 1) {
            throw new RuntimeException('Referral staff has no district; cannot assign application number.');
        }

        $districtName = $staff->district?->name ?? '';
        $blocks = DistrictBlock::orderedNamesForDistrict($districtId);
        if ($blocks === []) {
            $blocks = config('cfa.blocks_by_district.'.$districtName, []);
        }
        $blockIndex = 0;
        if ($blocks !== []) {
            $idx = array_search($block, $blocks, true);
            $blockIndex = $idx === false ? 0 : (int) $idx;
        }

        $mChar = self::MONTH_CHARS[$at->month - 1] ?? 'C';
        $dPart = $this->toBase36Padded($districtId, 2);
        $bPart = $this->toBase36Padded($blockIndex, 2);

        $serial = $this->nextSerial($districtId, (int) $at->year, (int) $at->month, $block);
        if ($serial > self::SERIAL_MAX) {
            throw new RuntimeException('Application serial overflow for this district/month/block; extend format.');
        }
        $sPart = $this->toBase36Padded($serial, 3);

        return $mChar.$dPart.$bPart.$sPart;
    }

    /**
     * Generate an application number from a District model directly (no staff required).
     * Used by the public walk-in CFA form.
     */
    public function generateForDistrict(District $district, string $block, ?Carbon $at = null): string
    {
        $at = $at ?? now();
        $districtId = (int) $district->id;
        if ($districtId < 1) {
            throw new RuntimeException('District has no valid ID; cannot assign application number.');
        }

        $blocks = DistrictBlock::orderedNamesForDistrict($districtId);
        if ($blocks === []) {
            $blocks = config('cfa.blocks_by_district.'.$district->name, []);
        }
        $blockIndex = 0;
        if ($blocks !== []) {
            $idx = array_search($block, $blocks, true);
            $blockIndex = $idx === false ? 0 : (int) $idx;
        }

        $mChar = self::MONTH_CHARS[$at->month - 1] ?? 'C';
        $dPart = $this->toBase36Padded($districtId, 2);
        $bPart = $this->toBase36Padded($blockIndex, 2);

        $serial = $this->nextSerial($districtId, (int) $at->year, (int) $at->month, $block);
        if ($serial > self::SERIAL_MAX) {
            throw new RuntimeException('Application serial overflow for this district/month/block; extend format.');
        }
        $sPart = $this->toBase36Padded($serial, 3);

        return $mChar.$dPart.$bPart.$sPart;
    }

    private function nextSerial(int $districtId, int $year, int $month, string $blockName): int
    {
        $last = 0;
        for ($i = 0; $i < self::ALLOC_RETRIES; $i++) {
            try {
                $last = DB::transaction(function () use ($districtId, $year, $month, $blockName) {
                    $row = CfaApplicationSequence::query()
                        ->where('district_id', $districtId)
                        ->where('year', $year)
                        ->where('month', $month)
                        ->where('block_name', $blockName)
                        ->lockForUpdate()
                        ->first();

                    if ($row === null) {
                        CfaApplicationSequence::query()->create([
                            'district_id' => $districtId,
                            'year' => $year,
                            'month' => $month,
                            'block_name' => $blockName,
                            'last_serial' => 1,
                        ]);

                        return 1;
                    }

                    $row->increment('last_serial');

                    return (int) $row->fresh()->last_serial;
                });

                break;
            } catch (QueryException $e) {
                if ($this->isDuplicateKey($e) && $i < self::ALLOC_RETRIES - 1) {
                    continue;
                }
                throw $e;
            }
        }

        return $last;
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return $e->errorInfo[1] === 1062;
    }

    private function toBase36Padded(int $value, int $length): string
    {
        $value = max(0, $value);
        $s = strtoupper(base_convert((string) $value, 10, 36));

        return str_pad($s, $length, '0', STR_PAD_LEFT);
    }
}
