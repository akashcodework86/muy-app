<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DataCentre\ProgramDataCentreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataCentreController extends Controller
{
    public function __construct(
        private readonly ProgramDataCentreService $service,
    ) {}

    public function index(): View
    {
        $data = $this->service->build();

        return view('admin.data-centre.index', $data);
    }

    /**
     * Bust the page-level cache and redirect back to the Data Centre.
     */
    public function refresh(Request $request): RedirectResponse
    {
        $this->service->bustCache();

        return redirect()->route('admin.data-centre.index')
            ->with('flash_success', 'Data refreshed — latest counts loaded from the database.');
    }

    /**
     * Export a single section as a UTF-8 CSV (Excel-compatible with BOM).
     */
    public function export(Request $request, string $section): StreamedResponse
    {
        $allowed = ['summary', 'cfa-by-district', 'gender-state', 'gender-district', 'education-state', 'education-district'];
        if (! in_array($section, $allowed, true)) {
            abort(404, 'Unknown section.');
        }

        $rows     = $this->service->csvForSection($section);
        $filename = 'data-centre-'.$section.'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Export all sections as a single CSV workbook (multiple blocks separated by blank lines).
     */
    public function exportAll(): StreamedResponse
    {
        $districts = [];
        $data      = $this->service->build();
        $filename  = 'data-centre-all-sections-'.now()->format('Ymd_His').'.csv';

        $sections = [
            'Program Summary'               => 'summary',
            'CFA Applications by District'  => 'cfa-by-district',
            'Gender - State Totals'         => 'gender-state',
            'Gender - By District'          => 'gender-district',
            'Education - State Totals'      => 'education-state',
            'Education - By District'       => 'education-district',
        ];

        return response()->streamDownload(function () use ($sections): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");

            // Metadata header
            fputcsv($out, ['Program Data Centre — MUY MIS']);
            fputcsv($out, ['Generated at', now()->timezone('Asia/Kolkata')->format('d M Y, g:i A IST')]);
            fputcsv($out, ['Note: Combined counts exclude Phase 2 rows copied into Phase 3 (source=legacy_phase2).']);
            fputcsv($out, []);

            foreach ($sections as $label => $key) {
                fputcsv($out, ['=== '.$label.' ===']);
                $rows = $this->service->csvForSection($key);
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fputcsv($out, []);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
