<?php

namespace App\Services;

/**
 * Incubatee catalog for MIS 8.4 Demo Days — same search scope as pitch deck (all CFA + legacy applicants).
 */
class DemoDayIncubateeCatalogService
{
    public function __construct(
        private readonly PitchDeckIncubateeCatalogService $pitchDeckCatalog,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, int $limit = 5): array
    {
        return $this->pitchDeckCatalog->search($term, $limit);
    }

    /**
     * @return array{name: string, application_no: ?string, district_id: int}|null
     */
    public function resolveSelection(int $cfaId, int $legacyId): ?array
    {
        return $this->pitchDeckCatalog->resolveSelection($cfaId, $legacyId);
    }

    /**
     * @param  list<array{cfa_submission_id?: int|string|null, legacy_application_id?: int|string|null}>  $entries
     * @return list<array<string, mixed>>
     */
    public function resolveParticipatingEntries(array $entries): array
    {
        $cfaIds = [];
        $legacyIds = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $cfaId = (int) ($entry['cfa_submission_id'] ?? 0);
            $legacyId = (int) ($entry['legacy_application_id'] ?? 0);

            if ($cfaId > 0) {
                $cfaIds[] = $cfaId;
            } elseif ($legacyId > 0) {
                $legacyIds[] = $legacyId;
            }
        }

        $profiles = $this->pitchDeckCatalog->profilesByIncubateeIds(
            array_values(array_unique($cfaIds)),
            array_values(array_unique($legacyIds)),
        );

        $resolved = [];
        $seen = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $cfaId = (int) ($entry['cfa_submission_id'] ?? 0);
            $legacyId = (int) ($entry['legacy_application_id'] ?? 0);

            if (($cfaId > 0) === ($legacyId > 0)) {
                continue;
            }

            $key = $cfaId > 0 ? 'cfa:'.$cfaId : 'legacy:'.$legacyId;
            if (isset($seen[$key])) {
                continue;
            }

            $snapshot = $this->resolveSelection($cfaId, $legacyId);
            if ($snapshot === null || (int) ($snapshot['district_id'] ?? 0) < 1) {
                continue;
            }

            $profile = $cfaId > 0
                ? ($profiles['cfa'][$cfaId] ?? null)
                : ($profiles['legacy'][$legacyId] ?? null);

            $gender = trim((string) ($entry['gender'] ?? ''));
            if ($gender === '' && is_array($profile)) {
                $gender = trim((string) ($profile['gender'] ?? ''));
            }

            $seen[$key] = true;
            $resolved[] = array_merge($snapshot, [
                'key' => $key,
                'cfa_submission_id' => $cfaId > 0 ? $cfaId : null,
                'legacy_application_id' => $legacyId > 0 ? $legacyId : null,
                'gender' => $gender !== '' ? $gender : null,
            ]);
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profileForDemoDay(\App\Models\DemoDay $row): ?array
    {
        $cfaId = (int) ($row->cfa_submission_id ?? 0);
        $legacyId = (int) ($row->legacy_application_id ?? 0);

        if ($cfaId < 1 && $legacyId < 1) {
            return null;
        }

        $profiles = $this->pitchDeckCatalog->profilesByIncubateeIds(
            $cfaId > 0 ? [$cfaId] : [],
            $legacyId > 0 ? [$legacyId] : [],
        );

        if ($cfaId > 0 && isset($profiles['cfa'][$cfaId])) {
            return $profiles['cfa'][$cfaId];
        }

        if ($legacyId > 0 && isset($profiles['legacy'][$legacyId])) {
            return $profiles['legacy'][$legacyId];
        }

        return null;
    }
}
