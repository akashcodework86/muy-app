<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockWorkshop;
use App\Models\District;
use App\Models\Hub;
use App\Services\FieldVisitAttendanceSheetService;
use App\Services\FieldVisitMediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BlockWorkshopAdminController extends Controller
{
    public function __construct(
        private readonly FieldVisitMediaStorage $mediaStorage,
        private readonly FieldVisitAttendanceSheetService $attendanceSheetService,
    ) {}

    public function index(Request $request): View
    {
        if (! Schema::hasTable('block_workshops')) {
            return view('admin.block-workshops.index', [
                'reports' => collect(),
                'hubs' => collect(),
                'districts' => collect(),
                'migrationMissing' => true,
                'totalWorkshops' => 0,
                'totalMale' => 0,
                'totalFemale' => 0,
                'totalParticipants' => 0,
            ]);
        }

        $hubs = Hub::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $hubId = $request->integer('hub') ?: null;
        $districtId = $request->integer('district') ?: null;

        $districts = District::query()
            ->when($hubId, fn ($q) => $q->where('hub_id', $hubId))
            ->orderBy('name')
            ->get(['id', 'name', 'hub_id']);

        $baseQuery = function () use ($hubId, $districtId): \Illuminate\Database\Eloquent\Builder {
            return BlockWorkshop::query()
                ->join('districts as d', 'd.id', '=', 'block_workshops.district_id')
                ->when($hubId, fn ($q) => $q->where('d.hub_id', $hubId))
                ->when($districtId, fn ($q) => $q->where('block_workshops.district_id', $districtId))
                ->submitted();
        };

        $query = $baseQuery()
            ->with(['district:id,name', 'gramPanchayat:id,name'])
            ->select('block_workshops.*');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('block_workshops.field_coordinator_name', 'like', $like)
                    ->orWhere('block_workshops.block', 'like', $like)
                    ->orWhere('block_workshops.remark', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('block_workshops.visit_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('block_workshops.visit_date', '<=', $request->query('to'));
        }
        if ($request->filled('block')) {
            $query->where('block_workshops.block', $request->query('block'));
        }

        $reports = $query
            ->orderByDesc('block_workshops.visit_date')
            ->orderByDesc('block_workshops.id')
            ->paginate(30)
            ->withQueryString();

        $statsQuery = $baseQuery()->select(
            DB::raw('COUNT(*) as total_workshops'),
            DB::raw('COALESCE(SUM(block_workshops.participants_male_count), 0) as total_male'),
            DB::raw('COALESCE(SUM(block_workshops.participants_female_count), 0) as total_female'),
            DB::raw('COALESCE(SUM(block_workshops.participants_total), 0) as total_participants'),
        );

        if ($request->filled('from')) {
            $statsQuery->whereDate('block_workshops.visit_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $statsQuery->whereDate('block_workshops.visit_date', '<=', $request->query('to'));
        }
        if ($request->filled('block')) {
            $statsQuery->where('block_workshops.block', $request->query('block'));
        }

        $stats = $statsQuery->first();

        $blockOptions = $baseQuery()
            ->whereNotNull('block_workshops.block')
            ->where('block_workshops.block', '!=', '')
            ->distinct()
            ->orderBy('block_workshops.block')
            ->pluck('block_workshops.block')
            ->all();

        return view('admin.block-workshops.index', [
            'reports' => $reports,
            'hubs' => $hubs,
            'districts' => $districts,
            'blockOptions' => $blockOptions,
            'migrationMissing' => false,
            'searchQuery' => $search,
            'totalWorkshops' => (int) ($stats->total_workshops ?? 0),
            'totalMale' => (int) ($stats->total_male ?? 0),
            'totalFemale' => (int) ($stats->total_female ?? 0),
            'totalParticipants' => (int) ($stats->total_participants ?? 0),
        ]);
    }

    public function show(BlockWorkshop $blockWorkshop, Request $request): View
    {
        $blockWorkshop->load(['district', 'gramPanchayat', 'districtBlock', 'coordinator']);

        return view('admin.block-workshops.show', [
            'report' => $blockWorkshop,
            'participantRows' => $blockWorkshop->participantRows(),
            'mediaItems' => $blockWorkshop->visitMediaItems(),
        ]);
    }

    public function downloadAttachment(BlockWorkshop $blockWorkshop, Request $request): StreamedResponse
    {
        $index = $request->query('index');
        if ($index !== null && $index !== '') {
            return $this->mediaStorage->download($blockWorkshop, (int) $index, $request->boolean('inline'));
        }

        return $this->mediaStorage->legacyDownload($blockWorkshop);
    }

    public function exportParticipants(BlockWorkshop $blockWorkshop): StreamedResponse
    {
        $blockWorkshop->load(['district', 'gramPanchayat', 'districtBlock']);
        $rows = $blockWorkshop->participantRows();

        $filename = 'participants-workshop-'.$blockWorkshop->id.'-'
            .($blockWorkshop->visit_date?->format('Y-m-d') ?? 'unknown');

        if (class_exists(Spreadsheet::class)) {
            return $this->streamXlsx($rows, $filename.'.xlsx');
        }

        return $this->streamCsv($rows, $filename.'.csv');
    }

    /** @param list<array<string, mixed>> $rows */
    private function streamXlsx(array $rows, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Participants');

        $headers = ['#', 'Name', 'Mobile', 'Gender', 'District', 'Block', 'Gram Panchayat'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.'1', $h);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle($col.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']],
            ]);
            $col++;
        }

        foreach ($rows as $i => $row) {
            $r = $i + 2;
            $sheet->setCellValue('A'.$r, (int) ($row['sr'] ?? $i + 1));
            $sheet->setCellValue('B'.$r, (string) ($row['name'] ?? ''));
            $sheet->setCellValueExplicit('C'.$r, (string) ($row['mobile'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('D'.$r, (string) ($row['gender'] ?? ''));
            $sheet->setCellValue('E'.$r, (string) ($row['district_name'] ?? ''));
            $sheet->setCellValue('F'.$r, (string) ($row['block_name'] ?? ''));
            $sheet->setCellValue('G'.$r, (string) ($row['gram_panchayat_name'] ?? ''));
        }

        return response()->streamDownload(
            static function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
            },
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private function streamCsv(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            static function () use ($rows): void {
                $out = fopen('php://output', 'w');
                if (! $out) {
                    return;
                }
                fputcsv($out, ['#', 'Name', 'Mobile', 'Gender', 'District', 'Block', 'Gram Panchayat']);
                foreach ($rows as $i => $row) {
                    fputcsv($out, [
                        (int) ($row['sr'] ?? $i + 1),
                        (string) ($row['name'] ?? ''),
                        (string) ($row['mobile'] ?? ''),
                        (string) ($row['gender'] ?? ''),
                        (string) ($row['district_name'] ?? ''),
                        (string) ($row['block_name'] ?? ''),
                        (string) ($row['gram_panchayat_name'] ?? ''),
                    ]);
                }
                fclose($out);
            },
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }
}
