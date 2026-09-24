<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (FY 2025-26) market linkages live in rbiphase2 — no Phase 3 approval workflow.
 */
final class LegacyPhase2MarketLinkageCoverageSupport
{
    /**
     * Valid market-link partner rows per legacy application_id (deliverable 6.3 / 24.php rules).
     *
     * @param  list<int>  $legacyApplicationIds
     * @return array<int, list<string>>
     */
    public function linkedModeMapForLegacyApplicationIds(array $legacyApplicationIds): array
    {
        $legacyApplicationIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $legacyApplicationIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($legacyApplicationIds === [] || ! $this->legacyPartnersAvailable()) {
            return [];
        }

        $map = [];
        foreach (array_chunk($legacyApplicationIds, 400) as $chunk) {
            $query = DB::connection('legacy')
                ->table('rbi_service_partners as sp')
                ->whereIn('sp.application_id', $chunk)
                ->whereRaw("COALESCE(sp.status, 'Active') = 'Active'")
                ->whereRaw("(
                    COALESCE(NULLIF(TRIM(sp.partner_link), ''), '') <> ''
                    OR (
                        LOWER(COALESCE(sp.partner_type, 'online')) = 'offline'
                        AND EXISTS (
                            SELECT 1 FROM rbi_service_partner_products spp WHERE spp.partner_id = sp.id
                        )
                    )
                )");

            foreach ($query->get(['sp.application_id', 'sp.partner_type']) as $row) {
                $applicationId = (int) ($row->application_id ?? 0);
                if ($applicationId < 1) {
                    continue;
                }

                $mode = $this->normalizeMode((string) ($row->partner_type ?? ''));
                $map[$applicationId] ??= [];
                if (! in_array($mode, $map[$applicationId], true)) {
                    $map[$applicationId][] = $mode;
                }
            }
        }

        return $map;
    }

    private function legacyPartnersAvailable(): bool
    {
        try {
            if ((string) config('database.connections.legacy.database', '') === '') {
                return false;
            }

            return Schema::connection('legacy')->hasTable('rbi_service_partners');
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeMode(string $raw): string
    {
        $mode = strtolower(trim($raw));

        return $mode === 'offline' ? 'offline' : 'online';
    }
}
