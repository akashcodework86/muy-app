<?php

namespace App\Support;

use App\Models\LakhpatiTechnicalTraining;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Route;

final class StateAdminServiceNavIndex
{
    /**
     * Searchable Service-nav destinations for state admin.
     *
     * @return list<array{id: string, label: string, group: string, kind: string, keywords: string, url: string}>
     */
    public static function forUser(?User $user): array
    {
        if (! $user || $user->role !== 'state_admin') {
            return [];
        }

        $items = [];

        self::push($items, 'menu:catalog', 'Service catalog', 'Service module', 'menu', self::url('admin.service-catalog.index'), ['catalog']);
        self::push($items, 'menu:all-services', 'All services', 'Service module', 'menu', self::url('admin.phase3-services.index'), ['phase 3', 'service cases']);
        self::push($items, 'menu:bills', 'Bills', 'Bills', 'menu', self::url('admin.bills.dashboard'), ['invoice', 'dashboard']);
        self::push(
            $items,
            'menu:community-org-outreach',
            'Community organization outreach (1.5)',
            'Outreach and Mobilisation',
            'menu',
            self::url('admin.community-org-outreach.dashboard'),
            ['1.5', 'outreach', 'mobilisation']
        );

        self::push($items, 'menu:training-packages', 'Training Package Attendance', 'Training and Capacity Building', 'menu', self::url('admin.training-packages.dashboard'));
        self::push($items, 'menu:technical-trainings', 'Technical training to incubatees', 'Training and Capacity Building', 'menu', self::url('admin.technical-trainings.dashboard'));
        self::push($items, 'menu:lakhpati-trainings', LakhpatiTechnicalTraining::MODULE_LABEL, 'Training and Capacity Building', 'menu', self::url('admin.lakhpati-technical-trainings.dashboard'), ['3.3.1', 'lakhpati']);
        self::push($items, 'menu:eap-edp', 'EAP / EDP sessions', 'Training and Capacity Building', 'menu', self::url('admin.eap-edp-sessions.dashboard'), ['eap', 'edp']);
        self::push($items, 'menu:district-workshop', 'District level workshop', 'Training and Capacity Building', 'menu', self::url('admin.district-workshop-sessions.dashboard'));
        self::push($items, 'menu:block-workshop', 'Block level workshop', 'Training and Capacity Building', 'menu', self::url('admin.block-workshops.index'));

        if (CapacityBuildingStakeholdersAccess::canViewDashboard($user)) {
            self::push($items, 'menu:capacity-building', 'Capacity building (3.4)', 'Training and Capacity Building', 'menu', self::url('admin.capacity-building-stakeholders.dashboard'), ['3.4']);
        }

        if (StakeholderConsultationWorkshopAccess::canViewDashboard($user)) {
            self::push($items, 'menu:stakeholder-consultation', 'Stakeholder consultation (12.1)', 'Synergies Across Line Departments', 'menu', self::url('admin.stakeholder-consultation-workshops.dashboard'), ['12.1']);
        }
        if (LineDepartmentMeetingAccess::canViewDashboard($user)) {
            self::push($items, 'menu:line-dept-meetings', 'Line dept meetings (12.2)', 'Synergies Across Line Departments', 'menu', self::url('admin.line-department-meetings.dashboard'), ['12.2']);
        }
        if (MentorshipRequestAccess::canViewDashboard($user)) {
            self::push($items, 'menu:mentorship', 'Mentorship requests (5.2)', 'Synergies Across Line Departments', 'menu', self::url('admin.mentorship-requests.dashboard'), ['5.2']);
        }

        self::push($items, 'menu:social-media', 'Social Media Post (10.1)', 'Branding, Communication & Knowledge Management', 'menu', self::url('admin.social-media-posts.dashboard'), ['10.1']);
        if (BrandingCommunicationAccess::canViewDashboard($user)) {
            self::push($items, 'menu:case-studies', 'Case Studies & Testimonials (10.2)', 'Branding, Communication & Knowledge Management', 'menu', self::url('admin.case-study-entries.dashboard'), ['10.2']);
            self::push($items, 'menu:newsletter', 'MUY Newsletter (10.3)', 'Branding, Communication & Knowledge Management', 'menu', self::url('admin.muy-newsletters.dashboard'), ['10.3']);
            self::push($items, 'menu:media-campaigns', 'IEC & Promotional Activities for MUY (10.4)', 'Branding, Communication & Knowledge Management', 'menu', self::url('admin.media-campaigns.dashboard'), ['10.4', 'iec']);
        }

        if (PitchDeckPreparationAccess::canViewDashboard($user)) {
            self::push($items, 'menu:pitch-deck', 'Incubatees Pitch Deck Preparation (8.3)', 'Funding & Schematic Convergence', 'menu', self::url('admin.pitch-deck-preparations.dashboard'), ['8.3']);
        }
        if (FundingSchematicConvergenceAccess::canViewDashboard($user)) {
            self::push($items, 'menu:demo-days', 'Demo Days (8.4)', 'Funding & Schematic Convergence', 'menu', self::url('admin.demo-days.dashboard'), ['8.4']);
            self::push($items, 'menu:funding-partners', 'Partners outreach (8.5)', 'Funding & Schematic Convergence', 'menu', self::url('admin.funding-partners-outreach.dashboard'), ['8.5']);
        }

        self::push($items, 'menu:market-linkage', 'Market Linkage', 'Forward Linkages', 'menu', self::url('admin.market-linkages.dashboard'));
        self::push($items, 'menu:market-linkage-partners', 'All partners (all phases)', 'Forward Linkages', 'menu', self::url('admin.market-linkages.partners'), ['partners']);
        if (PartnerOutreachAccess::canViewDashboard($user)) {
            self::push($items, 'menu:partner-outreach', 'Partner outreach (6.1 / 6.2)', 'Forward Linkages', 'menu', self::url('admin.partner-outreach.dashboard'), ['6.1', '6.2']);
        }
        if (BusinessAccelerationPartnersOutreachAccess::canViewDashboard($user)) {
            self::push($items, 'menu:ba-partners', 'BA partners outreach (7.1)', 'Forward Linkages', 'menu', self::url('admin.business-acceleration-partners-outreach.dashboard'), ['7.1']);
        }
        if (AccelerationServicesAccess::canViewDashboard($user)) {
            self::push($items, 'menu:acceleration', 'Acceleration services (7.2)', 'Forward Linkages', 'menu', self::url('admin.acceleration-services.dashboard'), ['7.2']);
        }

        if (Route::has('admin.phase3-services.index')) {
            $services = Service::query()
                ->where('is_active', true)
                ->with(['category:id,name,parent_id', 'category.parent:id,name'])
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'code', 'service_category_id']);

            foreach ($services as $service) {
                $category = $service->category;
                $group = trim((string) ($category?->parent?->name ?: $category?->name ?: 'Catalog'));
                $code = str_replace('_', ' ', (string) $service->code);
                self::push(
                    $items,
                    'catalog:'.$service->id,
                    (string) $service->name,
                    $group,
                    'catalog',
                    self::url('admin.phase3-services.index', ['service_id' => $service->id]),
                    [$code, 'service cases']
                );
            }
        }

        return $items;
    }

    /**
     * @param  list<array{id: string, label: string, group: string, kind: string, keywords: string, url: string}>  $items
     * @param  list<string>  $extraKeywords
     */
    private static function push(array &$items, string $id, string $label, string $group, string $kind, string $url, array $extraKeywords = []): void
    {
        $label = trim($label);
        $url = trim($url);
        if ($label === '' || $url === '') {
            return;
        }

        $haystack = strtolower(trim(implode(' ', array_filter([
            $label,
            $group,
            $kind,
            ...$extraKeywords,
        ]))));

        $items[] = [
            'id' => $id,
            'label' => $label,
            'group' => $group,
            'kind' => $kind,
            'keywords' => $haystack,
            'url' => $url,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function url(string $name, array $params = []): string
    {
        return Route::has($name) ? route($name, $params) : '';
    }
}
