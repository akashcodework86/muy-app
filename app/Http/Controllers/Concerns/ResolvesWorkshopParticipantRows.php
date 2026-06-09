<?php

namespace App\Http\Controllers\Concerns;

use App\Models\DistrictBlock;
use App\Models\GramPanchayat;
use App\Models\User;
use App\Services\BlockWorkshopParticipantRowsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

trait ResolvesWorkshopParticipantRows
{
    /**
     * @return array{
     *     blockRows: Collection<int, DistrictBlock>,
     *     gramPanchayatsEnabled: bool,
     *     districtLabel: string,
     * }
     */
    protected function workshopParticipantFormContext(User $user): array
    {
        $districtId = (int) ($user->district_id ?: 0);
        $blockRows = $districtId > 0
            ? DistrictBlock::query()
                ->where('district_id', $districtId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return [
            'blockRows' => $blockRows,
            'gramPanchayatsEnabled' => Schema::hasTable('gram_panchayats'),
            'districtLabel' => (string) ($user->district?->name ?? '—'),
        ];
    }

    public function workshopGramPanchayats(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(Schema::hasTable('gram_panchayats'), 404);

        $blockId = (int) $request->query('district_block_id', 0);
        abort_if($blockId <= 0, 422);

        $block = DistrictBlock::query()->findOrFail($blockId);
        abort_unless((int) $block->district_id === (int) ($user->district_id ?: 0), 403);

        $items = GramPanchayat::listForBlock($blockId, (string) $request->query('q', ''));

        return response()->json([
            'items' => $items->all(),
            'total' => $items->count(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>|null  $existing
     * @return list<array<string, mixed>>
     */
    protected function resolveWorkshopParticipantsFromRequest(
        Request $request,
        User $user,
        int $maleCount,
        int $femaleCount,
        ?array $existing = null,
    ): array {
        $total = max(0, $maleCount + $femaleCount);
        $districtName = (string) ($user->district?->name ?? '');
        $block = null;
        $gramPanchayat = null;

        $blockId = (int) $request->input('district_block_id', 0);
        if ($blockId > 0) {
            $block = DistrictBlock::query()->find($blockId);
            abort_unless($block && (int) $block->district_id === (int) ($user->district_id ?: 0), 403);
        }

        $gpId = (int) $request->input('gram_panchayat_id', 0);
        if ($gpId > 0 && Schema::hasTable('gram_panchayats')) {
            $gramPanchayat = GramPanchayat::query()->find($gpId);
            if ($gramPanchayat && $block) {
                abort_if((int) $gramPanchayat->district_block_id !== (int) $block->id, 422);
            }
        }

        $blockName = (string) ($block?->name ?? '');
        $defaultGpId = $gramPanchayat?->id;
        $defaultGpName = (string) ($gramPanchayat?->name ?? '');

        $service = app(BlockWorkshopParticipantRowsService::class);

        $rows = $service->syncRowCount(
            $existing,
            $maleCount,
            $femaleCount,
            $districtName,
            $blockName,
            $defaultGpId ? (int) $defaultGpId : null,
            $defaultGpName !== '' ? $defaultGpName : null,
        );

        if ($request->has('participants') && is_array($request->input('participants'))) {
            $rows = $service->sanitizeIncoming($request->input('participants'), $total);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    protected function workshopParticipantValidationRules(): array
    {
        return [
            'district_block_id' => ['nullable', 'integer', 'min:1'],
            'gram_panchayat_id' => ['nullable', 'integer', 'min:1'],
            'participants' => ['nullable', 'array', 'max:'.BlockWorkshopParticipantRowsService::MAX_ROWS],
            'participants.*' => ['array'],
            'participants.*.name' => ['nullable', 'string', 'max:191'],
            'participants.*.mobile' => ['nullable', 'string', 'max:15'],
            'participants.*.gender' => ['nullable', 'string', 'in:M,F'],
            'participants.*.gram_panchayat_id' => ['nullable', 'integer', 'min:1'],
            'participants.*.gram_panchayat_name' => ['nullable', 'string', 'max:191'],
        ];
    }

    protected function assertWorkshopParticipantLocation(Request $request, int $maleCount, int $femaleCount): void
    {
        if ($maleCount + $femaleCount < 1) {
            return;
        }

        abort_if((int) $request->input('district_block_id', 0) <= 0, 422, 'Select a block when recording participants.');
    }

    /**
     * @param  Collection<int, DistrictBlock>  $blockRows
     * @param  array<string, mixed>  $firstRow
     */
    protected function defaultBlockIdFromParticipantRows(Collection $blockRows, array $firstRow): int
    {
        $blockName = trim((string) ($firstRow['block_name'] ?? ''));
        if ($blockName === '') {
            return 0;
        }

        $match = $blockRows->first(fn (DistrictBlock $block): bool => strcasecmp($block->name, $blockName) === 0);

        return $match ? (int) $match->id : 0;
    }
}
