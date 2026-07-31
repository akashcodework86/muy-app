<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Exports\Phase3ShgCboReapPackDataService;
use App\Services\Exports\Phase3ShgCboReapPackExcelExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Phase3ShgCboReapPackExportController extends Controller
{
    public function __construct(
        private readonly Phase3ShgCboReapPackDataService $data,
        private readonly Phase3ShgCboReapPackExcelExport $excel,
    ) {}

    public function download(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $pack = $this->data->build();
        $fileName = 'phase3-shg-cbo-reap-6_3-'.now()->format('Ymd_His').'.xlsx';

        return $this->excel->download($pack, $fileName);
    }
}
