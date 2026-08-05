<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Exports\OnboardedShgCboDistrictPackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OnboardedShgCboDistrictExportController extends Controller
{
    public function __construct(
        private readonly OnboardedShgCboDistrictPackService $pack,
    ) {}

    public function download(Request $request): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $districtId = $request->integer('district_id') ?: null;
        $district = trim((string) $request->query('district', ''));

        try {
            if (! class_exists(\ZipArchive::class)) {
                return redirect()
                    ->route('admin.data-centre.index')
                    ->withErrors(['export' => 'Excel export unavailable: PHP Zip extension (ext-zip) is not enabled on the server.']);
            }

            $data = $this->pack->build($districtId ?: null, $district !== '' ? $district : null);
            $slug = (string) ($data['meta']['district_slug'] ?? 'all');
            $fileName = 'onboarded-shg-cbo-'.($slug !== '' ? $slug : 'all').'-'.now()->format('Ymd_His').'.xlsx';
            $tempPath = storage_path('app/temp/'.$fileName);
            $this->pack->writeToPath($data, $tempPath);
            unset($data);

            return response()
                ->download($tempPath, $fileName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.data-centre.index')
                ->withErrors(['export' => 'Excel export failed: '.$e->getMessage()]);
        }
    }
}
