<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Deliverables\Exports\DeliverablesExcelSupport;
use App\Services\Exports\Phase3331ShgMembersPackDataService;
use App\Services\Exports\Phase3331ShgMembersPackExcelExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Phase3331ShgMembersPackExportController extends Controller
{
    public function __construct(
        private readonly Phase3331ShgMembersPackDataService $data,
        private readonly Phase3331ShgMembersPackExcelExport $excel,
    ) {}

    public function download(Request $request): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        try {
            if ($issue = DeliverablesExcelSupport::availabilityIssue()) {
                report(new \RuntimeException('Technical trainings SHG Excel unavailable: '.$issue));

                return redirect()
                    ->route('admin.data-centre.index')
                    ->withErrors(['export' => 'Excel export unavailable on server: '.$issue]);
            }

            $pack = $this->data->build();
            $fileName = 'technical-trainings-shg-members-'.now()->format('Ymd_His').'.xlsx';
            $tempPath = storage_path('app/temp/'.$fileName);
            $this->excel->writeToPath($pack, $tempPath);
            unset($pack);

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
