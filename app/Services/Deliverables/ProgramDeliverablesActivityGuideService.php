<?php

namespace App\Services\Deliverables;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Maps each deliverables-matrix indicator to its logging module, target, and progress status.
 */
class ProgramDeliverablesActivityGuideService
{
    /**
     * @param  list<array<string, mixed>>  $reportRows
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function build(array $reportRows, string $role): array
    {
        $bySerial = collect($reportRows)->keyBy('serial');
        $rows = [];
        $pillarIndex = 0;

        foreach (config('program_deliverables.matrix', []) as $pillar) {
            $pillarIndex++;
            $pillarName = (string) ($pillar['name'] ?? '');
            $this->walkNode($pillar, [(string) $pillarIndex], $pillarName, $bySerial, $role, $rows);
        }

        $summary = [
            'total' => count($rows),
            'target_set' => 0,
            'met' => 0,
            'in_progress' => 0,
            'pending' => 0,
            'no_target' => 0,
            'not_wired' => 0,
            'tracking_only' => 0,
        ];

        foreach ($rows as $row) {
            match ($row['status']) {
                'met' => $summary['met']++,
                'in_progress' => $summary['in_progress']++,
                'pending' => $summary['pending']++,
                'no_target' => $summary['no_target']++,
                'not_wired' => $summary['not_wired']++,
                'tracking_only' => $summary['tracking_only']++,
                default => null,
            };
            if ($row['target'] !== null && (int) $row['target'] > 0) {
                $summary['target_set']++;
            }
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $serialParts
     * @param  Collection<string, array<string, mixed>>  $bySerial
     * @param  list<array<string, mixed>>  $out
     */
    private function walkNode(
        array $node,
        array $serialParts,
        string $pillarName,
        Collection $bySerial,
        string $role,
        array &$out,
    ): void {
        $rowType = (string) ($node['row_type'] ?? 'leaf');
        $serial = implode('.', $serialParts);

        if ($rowType === 'leaf' && isset($node['source'])) {
            $report = $bySerial->get($serial, []);
            $source = $node['source'] ?? ['type' => 'none'];
            $sourceType = (string) ($source['type'] ?? 'none');
            $target = is_array($report) ? ($report['target'] ?? null) : null;
            $achievement = is_array($report) ? (int) ($report['achievement'] ?? 0) : 0;
            $achievementPct = is_array($report) ? ($report['achievement_pct'] ?? null) : null;
            $module = $this->loggingModule($role, $sourceType, $source, (string) ($node['level'] ?? ''));

            $out[] = [
                'serial' => $serial,
                'pillar' => $pillarName,
                'name' => (string) ($node['name'] ?? ''),
                'indicator_type' => ProgramDeliverableReportingTier::indicatorTypeLabel($node),
                'level' => (string) ($node['level'] ?? ''),
                'target' => $target !== null ? (int) $target : null,
                'achievement' => $achievement,
                'achievement_pct' => $achievementPct,
                'gap' => ($target !== null && (int) $target > 0) ? max(0, (int) $target - $achievement) : null,
                'source_type' => $sourceType,
                'achievement_source' => $this->achievementSourceLabel($sourceType),
                'logging_module' => $module['label'],
                'logging_route' => $module['route'],
                'logging_note' => $module['note'],
                'status' => $this->resolveStatus($sourceType, $target, $achievement, $achievementPct),
                'status_label' => $this->statusLabel($sourceType, $target, $achievement, $achievementPct),
                'drilldown' => is_array($report) && ($report['drilldown'] ?? false),
            ];
        }

        $childIndex = 0;
        foreach ($node['children'] ?? [] as $child) {
            $childIndex++;
            $this->walkNode($child, [...$serialParts, (string) $childIndex], $pillarName, $bySerial, $role, $out);
        }
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{label: string, route: string|null, note: string}
     */
    private function loggingModule(string $role, string $sourceType, array $source, string $level): array
    {
        if (in_array($sourceType, ['none', 'target_name'], true)) {
            return [
                'label' => 'Not auto-tracked in app',
                'route' => null,
                'note' => $sourceType === 'target_name'
                    ? 'Target may exist in MIS plan; achievement is not wired to a staff module yet.'
                    : 'Placeholder indicator — log manually or pending MIS wiring.',
            ];
        }

        if ($level === 'State' && $role === 'district_staff') {
            $module = $this->moduleBySourceType($sourceType, $source, 'state_admin');

            return [
                'label' => $module['label'],
                'route' => null,
                'note' => 'State-level indicator — usually logged by state / hub team, not district staff.',
            ];
        }

        return $this->moduleBySourceType($sourceType, $source, $role);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{label: string, route: string|null, note: string}
     */
    private function moduleBySourceType(string $sourceType, array $source, string $role): array
    {
        $prefix = match ($role) {
            'district_staff' => 'staff.',
            'hub_admin' => 'hub.',
            'state_staff' => 'spoc.',
            default => 'admin.',
        };

        $pick = function (string $suffix, string $label, string $note = '') use ($prefix): array {
            $route = $prefix.$suffix;
            if (! Route::has($route)) {
                return ['label' => $label, 'route' => null, 'note' => $note ?: 'Module not available for this role.'];
            }

            return ['label' => $label, 'route' => $route, 'note' => $note];
        };

        return match ($sourceType) {
            'cfa_count' => $pick('applications', 'CFA → Applications', 'Add referral-linked CFA applications.'),
            'onboarding_count' => $pick('batches.index', 'CFA → Batches', 'Lock onboarding batches to count incubatees onboarded.'),
            'potential_lakhpati_onboarding_count' => $pick('batches.index', 'CFA → Batches', 'Subset of onboarded incubatees (Potential Lakhpati / SHG / CBO).'),
            'field_work_workshops', 'field_visit_sessions' => $pick('attendance.index', 'Field work → Block level workshop', 'Submit field visit / outreach workshop reports.'),
            'field_work_participants', 'field_visit_participants' => $pick('attendance.index', 'Field work → Block level workshop', 'Female participant count comes from the same field work form.'),
            'district_workshop_sessions' => $pick('district-workshop-sessions.dashboard', 'Field work → District workshop', 'District-level workshop sessions.'),
            'edp_sessions' => $pick('eap-edp-sessions.dashboard', 'Field work → EAP / EDP', 'Log EAP/EDP session attendance.'),
            'bst_sessions', 'bst_participants' => $pick('training-packages.dashboard', 'Field work → Training package', 'Business skills training sessions & participants.'),
            'technical_training_sessions', 'technical_training_potential_lakhpati_participations' => $pick('technical-trainings.dashboard', 'Field work → Technical training', 'Technical training sessions & participations.'),
            'deliverable', 'service', 'services' => $pick('services.index', 'Service → Add service case', 'Approved service cases count toward achievement.'),
            'market_linkage_unique_partners', 'market_linkage_incubatees' => $pick('market-linkages.dashboard', 'Service → Market linkage', 'Market linkage partners & linked incubatees.'),
            default => [
                'label' => 'Achievement records',
                'route' => Route::has($prefix.'deliverables.index') ? $prefix.'deliverables.index' : null,
                'note' => 'See deliverables breakdown for source records.',
            ],
        };
    }

    private function achievementSourceLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'deliverable', 'service', 'services' => 'Approved service cases',
            'cfa_count' => 'CFA submissions (referral-linked)',
            'onboarding_count' => 'Locked onboarding batches',
            'potential_lakhpati_onboarding_count' => 'Onboarding subset (Lakhpati / SHG / CBO)',
            'field_work_workshops', 'field_visit_sessions' => 'Field work / block workshops',
            'field_work_participants', 'field_visit_participants' => 'Outreach participants (field work)',
            'district_workshop_sessions' => 'District workshop sessions',
            'edp_sessions' => 'EAP/EDP sessions',
            'bst_sessions' => 'BST sessions conducted',
            'bst_participants' => 'BST unique participants',
            'technical_training_sessions' => 'Technical training sessions',
            'technical_training_potential_lakhpati_participations' => 'Technical training (Lakhpati subset)',
            'market_linkage_unique_partners' => 'Market linkage partners',
            'market_linkage_incubatees' => 'Market-linked incubatees',
            'none' => 'Not wired',
            'target_name' => 'Name-matched deliverable target',
            default => 'System achievement count',
        };
    }

    private function resolveStatus(string $sourceType, mixed $target, int $achievement, mixed $achievementPct): string
    {
        if (in_array($sourceType, ['none', 'target_name'], true)) {
            return 'not_wired';
        }

        $targetN = $target !== null ? (int) $target : null;

        if ($targetN === null || $targetN <= 0) {
            return $achievement > 0 ? 'tracking_only' : 'no_target';
        }

        if ($achievement >= $targetN) {
            return 'met';
        }

        if ($achievement > 0) {
            return 'in_progress';
        }

        return 'pending';
    }

    private function statusLabel(string $sourceType, mixed $target, int $achievement, mixed $achievementPct): string
    {
        return match ($this->resolveStatus($sourceType, $target, $achievement, $achievementPct)) {
            'met' => 'Target met',
            'in_progress' => 'In progress',
            'pending' => 'Pending — start logging',
            'no_target' => 'Target not set',
            'tracking_only' => 'Tracking only (no FY target)',
            'not_wired' => 'Not wired in app',
            default => '—',
        };
    }
}
