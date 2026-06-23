<?php

namespace App\Services\Deliverables;

use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\StaffMonthlyTarget;
use App\Models\StateDeliverableTarget;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * MIS setup guide: target cascade (state → district → staff), input module, achievement wiring.
 */
class ProgramDeliverablesActivityGuideService
{
    public function __construct(
        private readonly ProgramDeliverableCodeLookup $codeLookup,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $reportRows
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function build(array $reportRows, string $role, int $fiscalYearId): array
    {
        $this->codeLookup->boot();

        $bySerial = collect($reportRows)->keyBy('serial');
        $rows = [];
        $pillarIndex = 0;

        foreach (config('program_deliverables.matrix', []) as $pillar) {
            $pillarIndex++;
            $pillarName = (string) ($pillar['name'] ?? '');
            $this->walkNode($pillar, [(string) $pillarIndex], $pillarName, $bySerial, $role, $fiscalYearId, $rows);
        }

        $summary = [
            'total' => count($rows),
            'ready' => 0,
            'target_gap' => 0,
            'input_missing' => 0,
            'tracking_na' => 0,
        ];

        foreach ($rows as $row) {
            match ($row['overall_status']) {
                'ready' => $summary['ready']++,
                'target_gap' => $summary['target_gap']++,
                'input_missing' => $summary['input_missing']++,
                'tracking_na' => $summary['tracking_na']++,
                default => null,
            };
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
        int $fiscalYearId,
        array &$out,
    ): void {
        $rowType = (string) ($node['row_type'] ?? 'leaf');
        $serial = implode('.', $serialParts);

        if ($rowType === 'leaf' && isset($node['source'])) {
            $report = $bySerial->get($serial, []);
            $source = $node['source'] ?? ['type' => 'none'];
            $sourceType = (string) ($source['type'] ?? 'none');
            $indicatorName = (string) ($node['name'] ?? '');
            $target = is_array($report) ? ($report['target'] ?? null) : null;
            $achievement = is_array($report) ? (int) ($report['achievement'] ?? 0) : 0;
            $module = $this->loggingModule($role, $sourceType, $source, (string) ($node['level'] ?? ''));
            $input = $this->assessInputMechanism($sourceType, $module);
            $tracking = $this->assessAchievementTracking($sourceType, is_array($report) && ($report['drilldown'] ?? false));
            $deliverableIds = $this->codeLookup->deliverableIdsForSource($source, $indicatorName);
            $targetSetup = $fiscalYearId > 0
                ? $this->assessTargetCascade($fiscalYearId, $deliverableIds, $sourceType)
                : $this->emptyTargetSetup('Select a fiscal year to check targets.');
            $overall = $this->resolveOverallStatus($input, $tracking, $targetSetup, $sourceType);

            $out[] = [
                'serial' => $serial,
                'pillar' => $pillarName,
                'name' => $indicatorName,
                'indicator_type' => (string) ($report['indicator_type'] ?? ProgramDeliverableReportingTier::indicatorTypeLabel($node)),
                'level' => (string) ($report['level'] ?? ($node['level'] ?? '')),
                'target' => $target !== null ? (int) $target : null,
                'achievement' => $achievement,
                'source_type' => $sourceType,
                'achievement_source' => $this->achievementSourceLabel($sourceType),
                'logging_module' => $module['label'],
                'logging_route' => $module['route'],
                'logging_note' => $module['note'],
                'input_status' => $input['status'],
                'input_status_label' => $input['label'],
                'target_state_label' => $targetSetup['state_label'],
                'target_district_label' => $targetSetup['district_label'],
                'target_staff_label' => $targetSetup['staff_label'],
                'target_note' => $targetSetup['note'],
                'achievement_status' => $tracking['status'],
                'achievement_status_label' => $tracking['label'],
                'overall_status' => $overall['status'],
                'overall_status_label' => $overall['label'],
                'drilldown' => is_array($report) && ($report['drilldown'] ?? false),
            ];
        }

        $childIndex = 0;
        foreach ($node['children'] ?? [] as $child) {
            $childIndex++;
            $this->walkNode($child, [...$serialParts, (string) $childIndex], $pillarName, $bySerial, $role, $fiscalYearId, $out);
        }
    }

    /**
     * @return array{status: string, label: string}
     */
    private function assessInputMechanism(string $sourceType, array $module): array
    {
        if (in_array($sourceType, ['none', 'target_name'], true)) {
            return ['status' => 'missing', 'label' => 'No input module'];
        }

        if ($module['route'] !== null) {
            return ['status' => 'complete', 'label' => 'Input module ready'];
        }

        return ['status' => 'partial', 'label' => 'Module mapped, route missing'];
    }

    /**
     * @return array{status: string, label: string}
     */
    private function assessAchievementTracking(string $sourceType, bool $drilldown): array
    {
        if (in_array($sourceType, ['none', 'target_name'], true)) {
            return ['status' => 'na', 'label' => 'Not auto-tracked'];
        }

        if ($drilldown) {
            return ['status' => 'active', 'label' => 'Achievement wired'];
        }

        return ['status' => 'partial', 'label' => 'Counted, no drilldown'];
    }

    /**
     * @param  list<int>  $deliverableIds
     * @return array{
     *     state_label: string,
     *     district_label: string,
     *     staff_label: string,
     *     note: string,
     *     state_ok: bool,
     *     district_ok: bool,
     *     staff_ok: bool
     * }
     */
    private function assessTargetCascade(int $fiscalYearId, array $deliverableIds, string $sourceType): array
    {
        if (in_array($sourceType, ['none'], true)) {
            return $this->emptyTargetSetup('Indicator not mapped to FY targets.');
        }

        if ($deliverableIds === []) {
            return $this->emptyTargetSetup('No deliverable row linked — check MIS name / service catalog.');
        }

        $stateTotal = (int) StateDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('deliverable_id', $deliverableIds)
            ->sum('target_total');

        $districtTotal = (int) DistrictDeliverableTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('deliverable_id', $deliverableIds)
            ->sum('target_total');

        $staffTotal = (int) StaffMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('deliverable_id', $deliverableIds)
            ->sum('target_count');

        $stateOk = $stateTotal > 0;
        $districtOk = $stateOk && $districtTotal === $stateTotal;
        $staffOk = $districtOk && $staffTotal === $districtTotal && $this->allDistrictsStaffAligned($fiscalYearId, $deliverableIds);

        $stateLabel = $stateOk ? 'State ✓ ('.number_format($stateTotal).')' : 'State ✗ not set';
        $districtLabel = ! $stateOk
            ? 'District —'
            : ($districtOk ? 'District ✓ ('.number_format($districtTotal).')' : 'District ✗ gap ('.number_format($districtTotal).' ≠ '.number_format($stateTotal).')');
        $staffLabel = ! $districtOk
            ? 'Staff —'
            : ($staffOk ? 'Staff ✓ ('.number_format($staffTotal).')' : 'Staff ✗ gap ('.number_format($staffTotal).' ≠ '.number_format($districtTotal).')');

        $note = '';
        if ($stateOk && $districtOk && $staffOk) {
            $note = 'Targets aligned: state → district → staff.';
        } elseif ($stateOk && ! $districtOk) {
            $note = 'District allocation does not match state total.';
        } elseif ($districtOk && ! $staffOk) {
            $note = 'Staff monthly targets do not match district allocation in one or more districts.';
        } elseif (! $stateOk) {
            $note = 'Set state FY target first, then district and staff monthlies.';
        }

        return [
            'state_label' => $stateLabel,
            'district_label' => $districtLabel,
            'staff_label' => $staffLabel,
            'note' => $note,
            'state_ok' => $stateOk,
            'district_ok' => $districtOk,
            'staff_ok' => $staffOk,
        ];
    }

    /**
     * @return array{
     *     state_label: string,
     *     district_label: string,
     *     staff_label: string,
     *     note: string,
     *     state_ok: bool,
     *     district_ok: bool,
     *     staff_ok: bool
     * }
     */
    private function emptyTargetSetup(string $note): array
    {
        return [
            'state_label' => 'State —',
            'district_label' => 'District —',
            'staff_label' => 'Staff —',
            'note' => $note,
            'state_ok' => false,
            'district_ok' => false,
            'staff_ok' => false,
        ];
    }

    /**
     * @param  list<int>  $deliverableIds
     */
    private function allDistrictsStaffAligned(int $fiscalYearId, array $deliverableIds): bool
    {
        foreach (District::query()->pluck('id') as $districtId) {
            $districtTarget = (int) DistrictDeliverableTarget::query()
                ->where('fiscal_year_id', $fiscalYearId)
                ->where('district_id', $districtId)
                ->whereIn('deliverable_id', $deliverableIds)
                ->sum('target_total');

            if ($districtTarget === 0) {
                continue;
            }

            $userIds = User::query()->where('district_id', $districtId)->pluck('id');
            $staffSum = (int) StaffMonthlyTarget::query()
                ->where('fiscal_year_id', $fiscalYearId)
                ->whereIn('deliverable_id', $deliverableIds)
                ->whereIn('user_id', $userIds)
                ->sum('target_count');

            if ($staffSum !== $districtTarget) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{state_ok: bool, district_ok: bool, staff_ok: bool}  $targetSetup
     * @return array{status: string, label: string}
     */
    private function resolveOverallStatus(array $input, array $tracking, array $targetSetup, string $sourceType): array
    {
        if (in_array($sourceType, ['none', 'target_name'], true)) {
            return ['status' => 'tracking_na', 'label' => 'Not configured in app'];
        }

        if ($input['status'] === 'missing') {
            return ['status' => 'input_missing', 'label' => 'Input not wired'];
        }

        if ($tracking['status'] === 'na') {
            return ['status' => 'tracking_na', 'label' => 'Achievement not tracked'];
        }

        if ($targetSetup['state_ok'] && $targetSetup['district_ok'] && $targetSetup['staff_ok']
            && $input['status'] === 'complete' && $tracking['status'] === 'active') {
            return ['status' => 'ready', 'label' => 'Fully configured'];
        }

        if (! $targetSetup['state_ok'] || ! $targetSetup['district_ok'] || ! $targetSetup['staff_ok']) {
            return ['status' => 'target_gap', 'label' => 'Target setup incomplete'];
        }

        return ['status' => 'target_gap', 'label' => 'Review setup'];
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
                    ? 'Target may exist in MIS plan; no staff logging module linked yet.'
                    : 'Placeholder indicator — pending MIS wiring.',
            ];
        }

        return $this->moduleBySourceType($sourceType, $source, 'state_admin');
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

        $pick = function (string $suffix, string $label, string $note = '', ?string $adminSuffix = null) use ($prefix): array {
            if ($prefix === 'admin.' && $adminSuffix !== null) {
                $adminRoute = 'admin.'.$adminSuffix;
                if (Route::has($adminRoute)) {
                    return ['label' => $label, 'route' => $adminRoute, 'note' => $note];
                }
            }

            $route = $prefix.$suffix;
            if (! Route::has($route)) {
                $staffNote = $prefix === 'admin.'
                    ? ($note !== '' ? $note.' ' : '').'District staff log this in their portal.'
                    : ($note ?: 'Module route not registered.');

                return ['label' => $label, 'route' => null, 'note' => trim($staffNote)];
            }

            return ['label' => $label, 'route' => $route, 'note' => $note];
        };

        if ($sourceType === 'deliverable') {
            $code = strtolower(trim((string) ($source['code'] ?? '')));
            if ($code === 'case_studies') {
                return $pick('case-study-entries.dashboard', '10.2 Case Studies & Testimonials', 'Sanjna Mishra logs case studies / testimonials with incubatee search.', 'case-study-entries.dashboard');
            }
            if ($code === 'social_media') {
                return $pick('social-media-posts.dashboard', 'Social Media Post', 'Sanjna Mishra logs social media posts.', 'social-media-posts.dashboard');
            }
        }

        return match ($sourceType) {
            'cfa_count' => $pick('applications', 'CFA → Applications', 'Referral-linked CFA applications.', 'cfa.index'),
            'onboarding_count' => $pick('batches.index', 'CFA → Batches', 'Lock onboarding batches.', 'onboarded.index'),
            'potential_lakhpati_onboarding_count' => $pick('batches.index', 'CFA → Batches', 'Lakhpati / SHG / CBO onboarding subset.', 'onboarded.index'),
            'field_work_workshops', 'field_visit_sessions' => $pick('attendance.index', 'Field work → Block workshop', 'Field visit / block workshop reports.'),
            'field_work_participants', 'field_visit_participants' => $pick('attendance.index', 'Field work → Block workshop', 'Female participants from field work form.'),
            'district_workshop_sessions' => $pick('district-workshop-sessions.dashboard', 'Field work → District workshop', 'District-level workshops.'),
            'edp_sessions' => $pick('eap-edp-sessions.dashboard', 'Field work → EAP / EDP', 'EAP/EDP session attendance.'),
            'bst_sessions', 'bst_participants' => $pick('training-packages.dashboard', 'Field work → Training package', 'BST sessions & participants.'),
            'technical_training_sessions' => $pick('technical-trainings.dashboard', 'Field work → Technical training', 'Technical training to incubatees (3.3).'),
            'technical_training_potential_lakhpati_sessions' => $pick('lakhpati-technical-trainings.dashboard', '3.3.1 Technical Trainings to Potential Lakhpati Didis/ SHG Members/ CBOs', 'Partner-requested technical training sessions.'),
            'capacity_building_stakeholder_sessions' => $pick('capacity-building-stakeholders.dashboard', '3.4 Capacity building of stakeholders', 'Stakeholder capacity-building sessions (REAP, USRLM, line dept).', 'capacity-building-stakeholders.dashboard'),
            'stakeholder_consultation_workshop_sessions' => $pick('stakeholder-consultation-workshops.dashboard', '12.1 Stakeholder Consultation Workshop', 'Log stakeholder consultation workshops (Aadil Ishrat).', 'stakeholder-consultation-workshops.dashboard'),
            'line_department_meeting_sessions' => $pick('line-department-meetings.dashboard', '12.2 Line Department Meeting', 'Log meetings with line departments at Spoke/Hub/State level.', 'line-department-meetings.dashboard'),
            'reap_support_services' => $pick('services.create', 'Service → New service case', 'Staff tick Through REAP when submitting a convergence service case.', 'phase3-services.index'),
            'pitch_deck_preparations', 'pitch_deck_combined' => $pick('pitch-deck-preparations.dashboard', '8.3 Pitch deck preparation', 'State team entries on pitch deck module; district pitch deck service cases.', 'pitch-deck-preparations.dashboard'),
            'deliverable', 'service', 'services' => $pick('services.index', 'Service → Service cases', 'Approved service cases count toward achievement.', 'phase3-services.index'),
            'market_linkage_unique_partners', 'market_linkage_incubatees' => $pick('market-linkages.dashboard', 'Service → Market linkage', 'Market linkage partners & incubatees.'),
            'marketing_partner_outreach_count', 'marketing_partner_onboarded_count' => $pick('partner-outreach.dashboard', 'Partner outreach (6.1 / 6.2)', 'Sanjna logs partner outreach; onboard via LoA/LoI/MoU on detail page.', 'partner-outreach.dashboard'),
            'business_acceleration_partners_outreach_count' => $pick('business-acceleration-partners-outreach.dashboard', '7.1 BA Partners outreach', 'Ankur Rawat logs acceleration partner outreach (unique partners count).', 'business-acceleration-partners-outreach.dashboard'),
            'demo_days_count' => $pick('demo-days.dashboard', '8.4 Demo Days', 'Govind Singh Dhami logs demo day events (onboarded incubatee + audience counts).', 'demo-days.dashboard'),
            'funding_schematic_partners_outreach_count' => $pick('funding-partners-outreach.dashboard', '8.5 Partners outreach (Funding)', 'Govind Singh Dhami logs funding / schematic partner outreach.', 'funding-partners-outreach.dashboard'),
            'muy_newsletter_count' => $pick('muy-newsletters.dashboard', '10.3 MUY Newsletter', 'Sanjna Mishra logs newsletter issues with PDF or link.', 'muy-newsletters.dashboard'),
            'media_campaigns_count' => $pick('media-campaigns.dashboard', '10.4 Newspaper / Radio campaigns', 'Sanjna Mishra logs newspaper ads and radio promotions with document + multimedia proof.', 'media-campaigns.dashboard'),
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
            'technical_training_potential_lakhpati_sessions' => '3.3.1 Technical Trainings to Potential Lakhpati Didis/ SHG Members/ CBOs',
            'capacity_building_stakeholder_sessions' => '3.4 Capacity building of stakeholders',
            'stakeholder_consultation_workshop_sessions' => '12.1 Stakeholder consultation workshops',
            'line_department_meeting_sessions' => '12.2 Line department meetings',
            'reap_support_services' => 'Convergence service cases (Through REAP)',
            'pitch_deck_preparations', 'pitch_deck_combined' => '8.3 Incubatees Pitch Deck Preparation (pitch deck services + state team)',
            'market_linkage_unique_partners' => 'Market linkage partners',
            'market_linkage_incubatees' => 'Market-linked incubatees',
            'marketing_partner_outreach_count' => 'Marketing partner outreach entries',
            'marketing_partner_onboarded_count' => 'Marketing partners onboarded (LoA/LoI/MoU)',
            'business_acceleration_partners_outreach_count' => '7.1 BA partners outreach (unique)',
            'demo_days_count' => '8.4 Demo Days (state team)',
            'funding_schematic_partners_outreach_count' => '8.5 Funding partners outreach (unique)',
            'muy_newsletter_count' => 'MUY newsletter entries (state team)',
            'media_campaigns_count' => 'Newspaper / radio campaign entries (state team)',
            'none' => 'Not wired',
            'target_name' => 'Name-matched deliverable target',
            default => 'System achievement count',
        };
    }
}
