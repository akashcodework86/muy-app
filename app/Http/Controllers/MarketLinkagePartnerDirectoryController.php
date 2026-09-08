<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Services\Deliverables\Exports\DeliverablesExcelSupport as XL;
use App\Services\MarketLinkages\AllPhasePartnerDirectoryService;
use App\Support\MarketLinkageAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketLinkagePartnerDirectoryController extends Controller
{
    public function __construct(
        private AllPhasePartnerDirectoryService $directory,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);

        $filters = $this->filtersFromRequest($request);
        $scope = $this->directory->scopeForUser($user, $filters['district_id']);
        $query = $this->directoryFilters($filters, $scope);

        $partners = $this->directory->partners($query);
        $stats = $this->directory->stats($query);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 40;
        $paginator = new LengthAwarePaginator(
            array_slice($partners, ($page - 1) * $perPage, $perPage),
            count($partners),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $routes = $this->routesFor($user);

        return view('market-linkages.directory', [
            'partners' => $paginator,
            'stats' => $stats,
            'filters' => $filters,
            'years' => AllPhasePartnerDirectoryService::YEARS,
            'districts' => $this->districtsFor($user),
            'showDistrictScope' => in_array($user->role, ['state_admin', 'hub_admin'], true),
            'routes' => $routes,
        ]);
    }

    public function show(Request $request, string $partner): View
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);

        $filters = $this->filtersFromRequest($request);
        $scope = $this->directory->scopeForUser($user, $filters['district_id']);
        $query = $this->directoryFilters($filters, $scope);
        $key = AllPhasePartnerDirectoryService::decodeKey($partner);
        $pack = $this->directory->partner($key, $query);
        abort_if($pack === null, 404, 'Partner not found.');

        $routes = $this->routesFor($user);

        return view('market-linkages.directory-show', [
            'partner' => $pack['partner'],
            'links' => $pack['links'],
            'filters' => $filters,
            'years' => AllPhasePartnerDirectoryService::YEARS,
            'districts' => $this->districtsFor($user),
            'showDistrictScope' => in_array($user->role, ['state_admin', 'hub_admin'], true),
            'routes' => $routes,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(MarketLinkageAccess::canViewDashboard($user), 403);

        $filters = $this->filtersFromRequest($request);
        $scope = $this->directory->scopeForUser($user, $filters['district_id']);
        $query = $this->directoryFilters($filters, $scope);
        $partnerParam = trim((string) $request->input('partner', ''));
        if ($partnerParam !== '') {
            $query['partner_key'] = AllPhasePartnerDirectoryService::decodeKey($partnerParam);
        }

        $links = $this->directory->filteredLinks($query);
        $stats = $this->directory->stats($query);

        XL::ensureAvailable();
        $ss = new Spreadsheet;

        if ($partnerParam !== '') {
            $pack = $this->directory->partner($query['partner_key'] ?? '', $query);
            abort_if($pack === null, 404, 'Partner not found.');
            $partnerName = (string) ($pack['partner']['name'] ?? 'Partner');
            $this->writeIncubateesWorkbook($ss, $pack['links'], $stats, $filters, $partnerName);
            $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($partnerName)), '-') ?: 'partner';

            return XL::streamDownload(
                $ss,
                $slug.'-incubatees-'.now()->format('Ymd_His').'.xlsx',
            );
        }

        $partners = $this->directory->partners($query);
        $summary = $ss->getActiveSheet();
        $summary->setTitle('Summary');
        $summary->setCellValue('A1', 'Market linkage partners — all phases');
        $summary->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        XL::writeMetaBlock($summary, 3, [
            ['Generated', now()->timezone('Asia/Kolkata')->format('d M Y, g:i A').' IST'],
            ['Search', $filters['q'] !== '' ? $filters['q'] : 'All'],
            ['FY', $filters['fy'] !== '' ? $filters['fy'] : 'All years'],
            ['Phase', $filters['phase'] !== '' ? 'Phase '.$filters['phase'] : 'All phases'],
            ['Onboarding', $filters['onboard'] !== '' ? ($filters['onboard'] === 'onboarded' ? 'Onboarded' : 'Not onboarded') : 'All'],
            ['Unique partners', (string) $stats['partners']],
            ['Unique incubatees', (string) $stats['incubatees']],
            ['Linkage records', (string) $stats['links']],
        ]);
        XL::writeTableHeader($summary, 12, ['Partner', 'Incubatees', 'Linkages', 'Online', 'Offline', 'Phases', 'Years']);
        $r = 13;
        foreach ($partners as $row) {
            $summary->setCellValue('A'.$r, $row['name']);
            $summary->setCellValue('B'.$r, $row['incubatee_count']);
            $summary->setCellValue('C'.$r, $row['link_count']);
            $summary->setCellValue('D'.$r, $row['online']);
            $summary->setCellValue('E'.$r, $row['offline']);
            $summary->setCellValue('F'.$r, implode(', ', $row['phases']));
            $summary->setCellValue('G'.$r, implode(', ', $row['years']));
            $r++;
        }
        if ($r > 13) {
            XL::applyDataRowBorders($summary, 'A13:G'.($r - 1));
        }
        $summary->getColumnDimension('A')->setWidth(42);
        foreach (['B', 'C', 'D', 'E'] as $col) {
            $summary->getColumnDimension($col)->setWidth(14);
        }
        $summary->getColumnDimension('F')->setWidth(22);
        $summary->getColumnDimension('G')->setWidth(28);

        $detail = $ss->createSheet();
        $detail->setTitle('Incubatees');
        XL::writeTableHeader($detail, 1, [
            'Partner', 'Incubatee', 'Application no', 'District', 'Phone', 'Onboarding',
            'Mode', 'Date', 'FY', 'Phase', 'Link', 'Source',
        ]);
        $dr = 2;
        foreach ($links as $link) {
            $detail->setCellValue('A'.$dr, $link['partner_name']);
            $detail->setCellValue('B'.$dr, $link['incubatee_name']);
            $detail->setCellValue('C'.$dr, $link['application_no']);
            $detail->setCellValue('D'.$dr, $link['district']);
            $detail->setCellValue('E'.$dr, $link['phone']);
            $detail->setCellValue('F'.$dr, $link['onboard_status'] ?? 'Not onboarded');
            $detail->setCellValue('G'.$dr, $link['mode_label']);
            $detail->setCellValue('H'.$dr, $link['date']);
            $detail->setCellValue('I'.$dr, $link['fy']);
            $detail->setCellValue('J'.$dr, $link['phase_label']);
            $detail->setCellValue('K'.$dr, $link['link_url']);
            $detail->setCellValue('L'.$dr, $link['source']);
            $dr++;
        }
        if ($dr > 2) {
            XL::applyDataRowBorders($detail, 'A2:L'.($dr - 1));
            $detail->setAutoFilter('A1:L'.($dr - 1));
        }
        $detail->getColumnDimension('A')->setWidth(32);
        $detail->getColumnDimension('B')->setWidth(28);
        $detail->getColumnDimension('C')->setWidth(18);
        $detail->getColumnDimension('D')->setWidth(18);
        $detail->getColumnDimension('E')->setWidth(14);
        $detail->getColumnDimension('F')->setWidth(16);
        $detail->getColumnDimension('G')->setWidth(12);
        $detail->getColumnDimension('H')->setWidth(14);
        $detail->getColumnDimension('I')->setWidth(12);
        $detail->getColumnDimension('J')->setWidth(12);
        $detail->getColumnDimension('K')->setWidth(36);
        $detail->getColumnDimension('L')->setWidth(14);
        $detail->freezePane('A2');

        $ss->setActiveSheetIndex(0);

        return XL::streamDownload($ss, 'market-linkage-partners-'.now()->format('Ymd_His').'.xlsx');
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @param  array{partners: int, incubatees: int, links: int, online: int, offline: int, phase1: int, phase2: int, phase3: int}  $stats
     * @param  array{q: string, fy: string, phase: string, mode: string, onboard: string, district_id: int}  $filters
     */
    private function writeIncubateesWorkbook(
        Spreadsheet $ss,
        array $links,
        array $stats,
        array $filters,
        string $partnerName,
    ): void {
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Incubatees');
        $sheet->setCellValue('A1', $partnerName.' — linked incubatees');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        XL::writeMetaBlock($sheet, 3, [
            ['Generated', now()->timezone('Asia/Kolkata')->format('d M Y, g:i A').' IST'],
            ['Partner', $partnerName],
            ['Search', $filters['q'] !== '' ? $filters['q'] : 'All'],
            ['FY', $filters['fy'] !== '' ? $filters['fy'] : 'All years'],
            ['Phase', $filters['phase'] !== '' ? 'Phase '.$filters['phase'] : 'All phases'],
            ['Mode', $filters['mode'] !== '' ? ucfirst($filters['mode']) : 'All'],
            ['Onboarding', $filters['onboard'] !== '' ? ($filters['onboard'] === 'onboarded' ? 'Onboarded' : 'Not onboarded') : 'All'],
            ['Incubatees', (string) $stats['incubatees']],
            ['Rows in list', (string) count($links)],
        ]);
        $this->fillIncubateesSheet($sheet, $links, 14);
    }

    /**
     * @param  list<array<string, mixed>>  $links
     */
    private function fillIncubateesSheet(Worksheet $sheet, array $links, int $headerRow = 1): void
    {
        XL::writeTableHeader($sheet, $headerRow, [
            'S.No.', 'Incubatee', 'Application no', 'District', 'Phone', 'Onboarding',
            'Mode', 'Date', 'FY', 'Phase', 'Link',
        ]);
        $dr = $headerRow + 1;
        $n = 1;
        foreach ($links as $link) {
            $sheet->setCellValue('A'.$dr, $n++);
            $sheet->setCellValue('B'.$dr, $link['incubatee_name']);
            $sheet->setCellValue('C'.$dr, $link['application_no']);
            $sheet->setCellValue('D'.$dr, $link['district']);
            $sheet->setCellValue('E'.$dr, $link['phone']);
            $sheet->setCellValue('F'.$dr, $link['onboard_status'] ?? 'Not onboarded');
            $sheet->setCellValue('G'.$dr, $link['mode_label']);
            $sheet->setCellValue('H'.$dr, $link['date']);
            $sheet->setCellValue('I'.$dr, $link['fy']);
            $sheet->setCellValue('J'.$dr, $link['phase_label']);
            $sheet->setCellValue('K'.$dr, $link['link_url']);
            $dr++;
        }
        $lastCol = 'K';
        if ($dr > $headerRow + 1) {
            XL::applyDataRowBorders($sheet, 'A'.($headerRow + 1).':'.$lastCol.($dr - 1));
            $sheet->setAutoFilter('A'.$headerRow.':'.$lastCol.($dr - 1));
        }
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(16);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(12);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(36);
    }

    /**
     * @return array{q: string, fy: string, phase: string, mode: string, onboard: string, district_id: int}
     */
    private function filtersFromRequest(Request $request): array
    {
        $fy = trim((string) $request->input('fy', ''));
        if ($fy !== '' && ! in_array($fy, AllPhasePartnerDirectoryService::YEARS, true)) {
            $fy = '';
        }
        $phase = trim((string) $request->input('phase', ''));
        if (! in_array($phase, ['1', '2', '3'], true)) {
            $phase = '';
        }
        $mode = strtolower(trim((string) $request->input('mode', '')));
        if (! in_array($mode, ['online', 'offline'], true)) {
            $mode = '';
        }
        $onboard = trim((string) $request->input('onboard', ''));
        if (! in_array($onboard, ['onboarded', 'not_onboarded'], true)) {
            $onboard = '';
        }

        return [
            'q' => trim((string) $request->input('q', '')),
            'fy' => $fy,
            'phase' => $phase,
            'mode' => $mode,
            'onboard' => $onboard,
            'district_id' => (int) $request->input('district_id', 0),
        ];
    }

    /**
     * @param  array{q: string, fy: string, phase: string, mode: string, onboard: string, district_id: int}  $filters
     * @param  array{district_ids: list<int>|null, district_names: list<string>|null}  $scope
     * @return array<string, mixed>
     */
    private function directoryFilters(array $filters, array $scope): array
    {
        return [
            'q' => $filters['q'],
            'fy' => $filters['fy'],
            'phase' => $filters['phase'],
            'mode' => $filters['mode'],
            'onboard' => $filters['onboard'],
            'district_ids' => $scope['district_ids'],
            'district_names' => $scope['district_names'],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, District>
     */
    private function districtsFor($user)
    {
        if ($user->role === 'state_admin') {
            return District::query()->orderBy('name')->get(['id', 'name']);
        }
        if ($user->role === 'hub_admin' && (int) ($user->hub_id ?: 0) > 0) {
            return District::query()->where('hub_id', (int) $user->hub_id)->orderBy('name')->get(['id', 'name']);
        }

        return collect();
    }

    /**
     * @return array{index: string, show: string, export: string, dashboard: string}
     */
    private function routesFor($user): array
    {
        return match ($user->role) {
            'state_admin' => [
                'index' => 'admin.market-linkages.partners',
                'show' => 'admin.market-linkages.partners.show',
                'export' => 'admin.market-linkages.partners.export',
                'dashboard' => 'admin.market-linkages.dashboard',
            ],
            'hub_admin' => [
                'index' => 'hub.market-linkages.partners',
                'show' => 'hub.market-linkages.partners.show',
                'export' => 'hub.market-linkages.partners.export',
                'dashboard' => 'hub.market-linkages.dashboard',
            ],
            default => [
                'index' => 'staff.market-linkages.partners',
                'show' => 'staff.market-linkages.partners.show',
                'export' => 'staff.market-linkages.partners.export',
                'dashboard' => 'staff.market-linkages.dashboard',
            ],
        };
    }
}
