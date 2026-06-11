<?php

namespace App\Services\Deliverables;

use App\Models\Deliverable;
use App\Models\Service;
use App\Services\ServiceTargetDeliverableSyncService;

/**
 * Resolves MIS matrix codes to deliverables.id (state / district / staff target rows).
 */
class ProgramDeliverableCodeLookup
{
    /** @var array<string, int> lowercase deliverable.code => id */
    private array $deliverableIdsByCode = [];

    public function __construct(
        private readonly ServiceTargetDeliverableSyncService $serviceTargetDeliverables,
    ) {}

    public function boot(): void
    {
        $this->deliverableIdsByCode = Deliverable::query()
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [strtolower((string) $code) => (int) $id])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $source
     * @return list<int>
     */
    public function deliverableIdsForSource(array $source, string $indicatorName = ''): array
    {
        $ids = [];

        foreach ($this->lookupCodesFromSource($source) as $code) {
            $ids = array_merge($ids, $this->deliverableIdsForLookupCode($code));
        }

        if ($ids === [] && ($source['type'] ?? '') === 'target_name') {
            $match = strtolower(trim((string) ($source['match'] ?? '')));
            if ($match !== '') {
                $ids = $this->deliverableIdsByNameKeyword($match);
            }
        }

        if ($ids === [] && $indicatorName !== '') {
            $ids = $this->deliverableIdsByIndicatorName($indicatorName);
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  array<string, mixed>  $source
     * @return list<string>
     */
    private function lookupCodesFromSource(array $source): array
    {
        return match ($source['type'] ?? 'none') {
            'deliverable', 'service' => array_filter([(string) ($source['code'] ?? '')]),
            'services' => array_map('strval', (array) ($source['codes'] ?? [])),
            'cfa_count', 'onboarding_count', 'potential_lakhpati_onboarding_count',
            'district_workshop_sessions', 'edp_sessions', 'bst_sessions', 'bst_participants',
            'field_work_workshops', 'field_work_participants', 'technical_training_sessions',
            'technical_training_potential_lakhpati_sessions', 'technical_training_potential_lakhpati_participations', 'community_org_outreach_count' => array_filter([(string) ($source['deliverable_code'] ?? '')]),
            'none' => array_filter([(string) ($source['deliverable_code'] ?? '')]),
            default => [],
        };
    }

    /**
     * @return list<int>
     */
    public function deliverableIdsForLookupCode(string $code): array
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return [];
        }

        $ids = [];
        foreach ($this->candidateCodesForLookup($code) as $candidate) {
            if (isset($this->deliverableIdsByCode[$candidate])) {
                $ids[] = $this->deliverableIdsByCode[$candidate];
            }
        }

        $candidateCodes = $this->candidateCodesForLookup($code);
        $serviceDeliverableIds = Service::query()
            ->where('is_active', true)
            ->where(function ($q) use ($candidateCodes): void {
                foreach ($candidateCodes as $candidate) {
                    $q->orWhere('code', $candidate);
                }
                $q->orWhereHas('deliverable', function ($dq) use ($candidateCodes): void {
                    $dq->whereIn('code', $candidateCodes);
                });
            })
            ->pluck('deliverable_id');

        foreach ($serviceDeliverableIds as $id) {
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private function candidateCodesForLookup(string $code): array
    {
        $codes = array_values(array_unique(array_filter([
            $code,
            $this->serviceTargetDeliverables->deliverableCodeForServiceCode($code),
        ])));

        $aliases = config('program_deliverables.target_code_aliases.'.$code, []);
        if (is_array($aliases)) {
            foreach ($aliases as $alias) {
                $alias = strtolower(trim((string) $alias));
                if ($alias === '') {
                    continue;
                }
                $codes[] = $alias;
                $codes[] = $this->serviceTargetDeliverables->deliverableCodeForServiceCode($alias);
            }
        }

        $keywords = config('program_deliverables.achievement_deliverable_keywords.'.$code, []);
        if (is_array($keywords)) {
            foreach ($keywords as $keyword) {
                $keyword = strtolower(trim((string) $keyword));
                if ($keyword === '') {
                    continue;
                }
                $codes[] = $keyword;
                $codes[] = $this->serviceTargetDeliverables->deliverableCodeForServiceCode($keyword);
            }
        }

        return array_values(array_unique(array_filter($codes)));
    }

    /**
     * @return list<int>
     */
    private function deliverableIdsByNameKeyword(string $keyword): array
    {
        $keyword = $this->normalizeLabel($keyword);
        if ($keyword === '') {
            return [];
        }

        $ids = [];
        foreach (Deliverable::query()->get(['id', 'name', 'mis_entry_label']) as $row) {
            foreach ([$row->name, $row->mis_entry_label] as $label) {
                $norm = $this->normalizeLabel((string) $label);
                if ($norm !== '' && str_contains($norm, $keyword)) {
                    $ids[] = (int) $row->id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    private function deliverableIdsByIndicatorName(string $indicatorName): array
    {
        $indicator = $this->normalizeLabel($indicatorName);
        if ($indicator === '') {
            return [];
        }

        $ids = [];
        foreach (Deliverable::query()->get(['id', 'name', 'mis_entry_label', 'code']) as $row) {
            foreach ([$row->name, $row->mis_entry_label, $row->code] as $label) {
                $norm = $this->normalizeLabel((string) $label);
                if ($norm === '') {
                    continue;
                }
                if ($norm === $indicator || str_contains($indicator, $norm) || str_contains($norm, $indicator)) {
                    $ids[] = (int) $row->id;
                    break;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function normalizeLabel(string $value): string
    {
        $value = strtolower(trim($value));

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
