<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Services\StateLiveMapService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StateLiveMapController extends Controller
{
    public function __construct(
        private readonly StateLiveMapService $liveMapService,
    ) {}

    public function index(): View
    {
        $activeFy = FiscalYear::query()
            ->where('is_active', true)
            ->orderByDesc('starts_on')
            ->first();

        return view('admin.live-map.index', [
            'activeFy' => $activeFy,
            'geoJsonUrl' => route('admin.live-map.geojson'),
            'dataUrl' => route('admin.live-map.data'),
        ]);
    }

    public function geojson(): BinaryFileResponse
    {
        $path = public_path('geo/uttarakhand-districts.geojson');
        abort_unless(is_file($path), 404, 'District map file missing on server.');

        return response()->file($path, [
            'Content-Type' => 'application/geo+json',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $dateInput = (string) $request->query('date', now()->toDateString());
        try {
            $date = Carbon::parse($dateInput)->startOfDay();
        } catch (\Throwable) {
            $date = now()->startOfDay();
        }

        return response()->json($this->liveMapService->build($date));
    }
}
