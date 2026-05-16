<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GramPanchayat;
use App\Services\GramPanchayatCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class GramPanchayatImportController extends Controller
{
    public function show(Request $request): View
    {
        $report = $request->session()->get('gram_panchayat_import_report');

        return view('admin.gram-panchayats.import', [
            'tableReady' => Schema::hasTable('gram_panchayats'),
            'totalGramPanchayats' => Schema::hasTable('gram_panchayats')
                ? GramPanchayat::query()->count()
                : 0,
            'report' => is_array($report) ? $report : null,
        ]);
    }

    public function store(Request $request, GramPanchayatCsvImporter $importer): RedirectResponse
    {
        if (! Schema::hasTable('gram_panchayats')) {
            return redirect()
                ->route('admin.gram-panchayats.import')
                ->withErrors(['csv' => 'Run database migrations first (gram_panchayats table missing).']);
        }

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $path = $request->file('csv')?->getRealPath();
        if ($path === false || $path === null) {
            return redirect()
                ->route('admin.gram-panchayats.import')
                ->withErrors(['csv' => 'Could not read the uploaded file.']);
        }

        $result = $importer->importFromPath($path);

        if (! $result['success']) {
            return redirect()
                ->route('admin.gram-panchayats.import')
                ->withErrors(['csv' => (string) $result['error']]);
        }

        return redirect()
            ->route('admin.gram-panchayats.import')
            ->with('gram_panchayat_import_report', $result);
    }
}
